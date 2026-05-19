<?php
include ROOT_PATH . '/connection/connect.php';

// Session restoration from remember_token (KEEP THIS)
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


$where_conditions = ['p.is_archived = 0']; // ✅ ADD THIS - Start with archive filter

if (!empty($selected_categories)) {
    $placeholders = str_repeat('?,', count($selected_categories) - 1) . '?';
    $where_conditions[] = "p.codename IN ($placeholders)";
    $params = array_merge($params, $selected_categories);
    $types .= str_repeat('s', count($selected_categories));
}

if (!empty($search_keyword)) {
    $where_conditions[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
    $search_param = '%' . $search_keyword . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$sort_options = [
    'name_asc' => 'p.product_name ASC',
    'name_desc' => 'p.product_name DESC',
    'newest' => 'p.id DESC',
    'oldest' => 'p.id ASC'
];
$order_by = $sort_options[$sort_by] ?? 'p.product_name ASC';

$where_clause = implode(' AND ', $where_conditions);

// ✅ FIX #2: Get total count - Add table alias
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products p WHERE $where_clause");
if (!empty($params))
    $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// ✅ FIX #3: Get products - Add table alias and archive filter
$stmt = $conn->prepare("SELECT p.id, p.product_name, p.main_image, p.description, p.codename FROM products p WHERE $where_clause ORDER BY $order_by LIMIT ? OFFSET ?");
$final_params = array_merge($params, [$per_page, $offset]);
$final_types = $types . 'ii';
if (!empty($final_params))
    $stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

$total_pages = ceil($total_products / $per_page);

$all_categories = [
    'furniture' => 'Furniture',
    'buildingmaterials' => 'Building Materials',
    'lightingfixture' => 'Lighting',
    'bedfurniture' => 'Bedroom Furniture',
    'aircon' => 'Air Conditioners',
    'doors' => 'Doors',
    'Tiles' => 'Tiles',
    'windows' => 'Windows',
    'bathroomFixtures' => 'Bathroom Fixtures',
    'kitchenFixture' => 'Kitchen Fixtures',
    'pipes' => 'Pipes',
    'aacblock' => 'AAC BLOCKS'
];

// ✅ FIX #4: Get category counts - Add archive filter
$category_counts = [];
foreach ($all_categories as $cat_key => $cat_name) {
    $cat_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products p WHERE p.codename = ? AND p.is_archived = 0");
    $cat_stmt->bind_param("s", $cat_key);
    $cat_stmt->execute();
    $category_counts[$cat_key] = $cat_stmt->get_result()->fetch_assoc()['count'];
    $cat_stmt->close();
}

$active_filters = count($selected_categories) + (!empty($search_keyword) ? 1 : 0);

// ===== CHECK IF GUEST =====
$is_guest = !isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Products - Noble Home</title>
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <meta name="description" content="Explore our premium collection of furniture, materials, and home décor items.">
    <script src="https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.19/bundled/lenis.min.js"></script>
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .pagination-btn {
            transition: all 0.3s ease;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .pagination-btn:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #000000ff 0%rgba(0, 0, 0, 1)0c 100%);
            color: white;
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #000000ff 0%, #000000ff 100%);
            color: white;

        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f1f5f9;
            color: #010202ff;
        }

        /* Mobile Sidebar Filter */
        .mobile-filter-sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 85%;
            max-width: 380px;
            height: 100vh;
            background: white;
            z-index: 1000;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }

        .mobile-filter-sidebar.active {
            left: 0;
        }

        .mobile-filter-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            backdrop-filter: blur(4px);
        }

        .mobile-filter-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .sidebar-header {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            padding: 1.5rem;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-content {
            padding: 1.5rem;
        }

        .filter-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 1rem;
        }

        .filter-section-header:hover {
            background: #f1f5f9;
        }

        .filter-section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .filter-section-content.expanded {
            max-height: 500px;
            margin-bottom: 1.5rem;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .category-item:hover {
            border-color: #f97316;
            background: #fff7ed;
        }

        .category-item input:checked~.category-label {
            color: #f97316;
            font-weight: 600;
        }

        .sidebar-footer {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            background: #fef3c7;
            color: #92400e;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .mobile-filter-toggle {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 50;
            display: none;
        }

        .desktop-sidebar-filter {
            display: none;
        }

        @media (min-width: 1024px) {
            .desktop-sidebar-filter {
                display: block;
            }

            .mobile-filter-toggle,
            .mobile-filter-sidebar,
            .mobile-filter-overlay {
                display: none !important;
            }

            .sticky-filter {
                position: sticky;
                top: 2rem;
            }
        }

        @media (max-width: 1023px) {
            .mobile-filter-toggle {
                display: block;
            }

            .desktop-sidebar-filter {
                display: none !important;
            }
        }

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

        /* Hide Scrollbar */
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        /* Carousel Dots */
        .carousel-dot.active {
            background: #f97316;
            width: 1rem;
        }
    </style>

    <script>


        function changeSort(value) {
            updateUrl({
                sort: value,
                page: 1
            });
        }

        function clearAllFilters() {
            window.location.href = window.location.pathname;
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

        // Carousel Navigation
        function scrollCarousel(index) {
            const carousel = document.getElementById('actionCarousel');
            const dots = document.querySelectorAll('.carousel-dot');
            const itemWidth = carousel.children[0].offsetWidth;
            const gap = 12; // gap-3 = 12px

            carousel.scrollTo({
                left: index * (itemWidth + gap),
                behavior: 'smooth'
            });

            // Update dots
            dots.forEach((dot, i) => {
                if (i === index) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }

        // Auto-update dots on scroll
        document.addEventListener('DOMContentLoaded', function () {
            const carousel = document.getElementById('actionCarousel');
            const dots = document.querySelectorAll('.carousel-dot');

            if (carousel && dots.length > 0) {
                carousel.addEventListener('scroll', function () {
                    const scrollLeft = carousel.scrollLeft;
                    const itemWidth = carousel.children[0].offsetWidth;
                    const gap = 12;
                    const index = Math.round(scrollLeft / (itemWidth + gap));

                    dots.forEach((dot, i) => {
                        if (i === index) {
                            dot.classList.add('active');
                        } else {
                            dot.classList.remove('active');
                        }
                    });
                });
            }
        });
    </script>
</head>

<body class="font-roboto">
    
    
    <section class="bg-white relative mb-12">
        <div class="w-full px-4 sm:px-6 lg:px-8 py-2">
            <!-- Two Container Buttons with Background Images -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                <!-- Explore Products Container -->
                <a href="<?= BASE_URL ?>/product-normal-and-discounted"
                    class="group relative overflow-hidden h-40 sm:h-52 lg:h-64 shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-[1.02]">
                    <!-- Background Image with Overlay -->
                    <div class="absolute inset-0 bg-cover bg-center"
                        style="background-image: url('<?= BASE_URL ?>/user/img/saleandexplore/a.png');">
                        <div
                            class="absolute inset-0 bg-black/40 group-hover:from-black/50 group-hover:via-black/60 group-hover:to-black/80 transition-all duration-300">
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="relative h-full flex flex-col items-center justify-center p-4 sm:p-6 text-white">
                        <h3 class="text-lg sm:text-xl lg:text-3xl uppercase mb-1 sm:mb-2 tracking-wide font-semibold"
                            style="font-family: 'Montserrat', sans-serif;">Explore Products</h3>
                        <p class="text-white/90 text-xs sm:text-sm mb-2 sm:mb-4"
                            style="font-family: 'Montserrat', sans-serif;">Browse our complete collection</p>
                        <span
                            class="inline-flex items-center gap-1 sm:gap-2 text-[10px] sm:text-xs bg-white/10 backdrop-blur-sm px-3 sm:px-4 py-1.5 sm:py-2 rounded-full border border-white/20 group-hover:bg-white/20 transition-all duration-300">
                            View Collection
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 transform group-hover:translate-x-1 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </span>
                    </div>
                </a>

                <!-- On Sale Products Container -->
                <a href="<?= BASE_URL ?>/sale"
                    class="group relative overflow-hidden h-40 sm:h-52 lg:h-64 shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-[1.02]">
                    <!-- Background Image with Overlay -->
                    <div class="absolute inset-0 bg-cover bg-center"
                        style="background-image: url('<?= BASE_URL ?>/user/img/saleandexplore/b.png');">
                        <div
                            class="absolute inset-0 bg-black/40 group-hover:from-black/50 group-hover:via-black/60 group-hover:to-black/80 transition-all duration-300">
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="relative h-full flex flex-col items-center justify-center p-4 sm:p-6 text-white">
                        <h3 class="text-lg sm:text-xl lg:text-3xl uppercase mb-1 sm:mb-2 tracking-wide font-semibold"
                            style="font-family: 'Montserrat', sans-serif;">On Sale Products</h3>
                        <p class="text-white/90 text-xs sm:text-sm mb-2 sm:mb-4"
                            style="font-family: 'Montserrat', sans-serif;">Limited time offers</p>
                        <span
                            class="inline-flex items-center gap-1 sm:gap-2 text-[10px] sm:text-xs bg-white/10 backdrop-blur-sm px-3 sm:px-4 py-1.5 sm:py-2 rounded-full border border-white/20 group-hover:bg-white/20 transition-all duration-300">
                            Shop Deals
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 transform group-hover:translate-x-1 transition-transform"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- Mobile Filter Overlay -->
    <div id="mobileFilterOverlay" class="mobile-filter-overlay"></div>

    <!-- Mobile Filter Sidebar -->
    <div id="mobileFilterSidebar" class="mobile-filter-sidebar">
        <div class="sidebar-header">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl flex items-center text-white" style="font-family: 'Montserrat', sans-serif; ">
                        <i class="fas fa-sliders-h mr-2"></i>
                        Filter Products
                    </h2>
                    <?php if ($active_filters > 0): ?>
                        <span class="filter-badge mt-2" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                            <?= $active_filters ?> active filter<?= $active_filters > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <button id="closeMobileSidebar"
                    class="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <div class="sidebar-content">
            <form method="GET" id="mobileFilterForm">
                <!-- Search Section -->
                <div class="mb-4">
                    <div class="filter-section-header" onclick="toggleMobileSection('search')">
                        <div class="flex items-center" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                            <i class="fas fa-search  mr-2"></i>
                            <span class="font-semibold">Search Products</span>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 transition-transform"
                            id="mobile-search-chevron"></i>
                    </div>
                    <div class="filter-section-content expanded" id="mobile-search-content">
                        <div class="px-1">
                            <div class="relative">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>"
                                    placeholder="Search by name or description..."
                                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <i
                                    class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Section -->
                <div class="mb-4">
                    <div class="filter-section-header" onclick="toggleMobileSection('categories')">
                        <div class="flex items-center" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                            <i class="fas fa-th-large  mr-2"></i>
                            <span class="font-semibold ">Categories</span>
                            <span
                                class="ml-2 text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full"><?= count($all_categories) ?></span>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 transition-transform"
                            id="mobile-categories-chevron"></i>
                    </div>
                    <div class="filter-section-content expanded" id="mobile-categories-content">
                        <div class="px-1 max-h-80 overflow-y-auto">
                            <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                                <label
                                    class="category-item <?= ($category_counts[$cat_key] ?? 0) == 0 ? 'opacity-50' : '' ?>">
                                    <div class="flex items-center flex-1">
                                        <input type="checkbox" name="category[]" value="<?= $cat_key ?>"
                                            <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?>
                                            <?= ($category_counts[$cat_key] ?? 0) == 0 ? 'disabled' : '' ?>
                                            class="mr-3 text-primary border-gray-300 rounded focus:ring-primary w-4 h-4">
                                        <span class="category-label text-sm"
                                            style="font-family: 'Montserrat', sans-serif; color: #2f1200;"><?= htmlspecialchars($cat_name) ?></span>
                                    </div>
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full font-semibold">
                                        <?= $category_counts[$cat_key] ?? 0 ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Sort Section -->
                <div class="mb-4" style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                    <div class="filter-section-header" onclick="toggleMobileSection('sort')">
                        <div class="flex items-center">
                            <i class="fas fa-sort  mr-2"></i>
                            <span class="font-semibold ">Sort By</span>
                        </div>
                        <i class="fas fa-chevron-down  transition-transform" id="mobile-sort-chevron"></i>
                    </div>
                    <div class="filter-section-content expanded" id="mobile-sort-content">
                        <div class="px-1">
                            <select name="sort"
                                class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary bg-white">
                                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)
                                </option>
                                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)
                                </option>
                                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="sidebar-footer">
            <div class="space-y-3">
                <button type="submit" form="mobileFilterForm"
                    class="w-full bg-black text-white px-6 py-3.5 rounded-lg hover:bg-black font-semibold shadow-lg transition-all"
                    style="font-family: 'Montserrat', sans-serif; ">
                    <i class="fas fa-check mr-2"></i>Apply Filters
                </button>
                <button type="button" onclick="clearAllFilters()"
                    class="w-full bg-gray-100 text-gray-700 px-6 py-3.5 rounded-lg hover:bg-gray-200 font-semibold transition-all"
                    style="font-family: 'Montserrat', sans-serif; ">
                    <i class="fas fa-times mr-2"></i>Clear All
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Filter Toggle Button -->
    <div class="mobile-filter-toggle">
        <button id="mobileFilterToggle"
            class="bg-white hover:bg-white text-black px-4 py-2.5 rounded-full font-semibold flex items-center shadow-lg transition-all hover:scale-105 text-sm">
            <i class="fas fa-sliders-h mr-1.5 text-xs"></i>
            Filters
            <?php if ($active_filters > 0): ?>
                <span
                    class="ml-1.5 bg-white text-primary w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold">
                    <?= $active_filters ?>
                </span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Main Content -->
    <main class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Products Section -->
            <div class="flex-1 lg:order-1">

                <?php if (!empty($selected_categories)): ?>
                    <div class="mb-8">
                        <!-- Filter Bar Container -->
                        <div class=" p-4 shadow-sm ">
                            <!-- Header with Count and Clear All -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-black rounded-lg flex items-center justify-center">
                                        <i class="fas fa-filter text-white text-sm"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold "
                                            style="font-family: 'Montserrat', sans-serif; color: #2f1200;">Active Filters
                                        </h3>
                                        <p class="text-xs text-gray-500"><?= count($selected_categories) ?> selected</p>
                                    </div>
                                </div>

                                <button onclick="clearAllFilters()"
                                    class="flex items-center gap-1.5 bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-all shadow-sm hover:shadow">
                                    <i class="fas fa-trash-alt"></i>
                                    <span class="hidden sm:inline">Clear All</span>
                                </button>
                            </div>

                            <!-- Pills Container with Scroll -->
                            <div class="overflow-x-auto scrollbar-hide -mx-2 px-2">
                                <div class="flex gap-2 min-w-max sm:flex-wrap sm:min-w-0">
                                    <?php foreach ($selected_categories as $cat_key): ?>
                                        <?php if (isset($all_categories[$cat_key])): ?>
                                            <div
                                                class="inline-flex items-center gap-2 bg-white border-2 border-gray-300 hover:border-black px-4 py-2 rounded-xl text-sm font-medium text-gray-900 shadow-sm hover:shadow transition-all group whitespace-nowrap">
                                                <i
                                                    class="fas fa-tag text-xs text-gray-400 group-hover:text-black transition-colors"></i>
                                                <span class="uppercase"><?= htmlspecialchars($all_categories[$cat_key]) ?></span>
                                                <a href="?<?= http_build_query(array_merge($_GET, ['category' => array_diff($selected_categories, [$cat_key]), 'page' => 1])) ?>"
                                                    class="flex items-center justify-center w-5 h-5 bg-gray-100 hover:bg-black rounded-full transition-colors group-hover:bg-red-500">
                                                    <i class="fas fa-times text-[10px] text-gray-600 group-hover:text-white"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <style>
                    /* Hide scrollbar pero functional pa rin */
                    .scrollbar-hide {
                        -ms-overflow-style: none;
                        scrollbar-width: none;
                    }

                    .scrollbar-hide::-webkit-scrollbar {
                        display: none;
                    }

                    /* Smooth transitions */
                    .transition-all {
                        transition: all 0.2s ease;
                    }

                    /* Mobile adjustments */
                    @media (max-width: 640px) {
                        .inline-flex {
                            padding: 0.5rem 0.75rem;
                            font-size: 0.75rem;
                        }

                        .w-8.h-8 {
                            width: 1.75rem;
                            height: 1.75rem;
                        }
                    }
                </style>

                <!-- Controls -->
                <div class="p-4 sm:p-6 mb-8 shadow-sm">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <!-- Sort Dropdown -->
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <label class="text-sm font-medium text-black whitespace-nowrap">Sort by:</label>
                            <select onchange="changeSort(this.value)"
                                class="flex-1 sm:flex-none py-2.5 px-4 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary bg-white">
                                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)
                                </option>
                                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)
                                </option>
                                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            </select>
                        </div>


                    </div>
                </div>

                <!-- Product Grid -->
                <div
                    class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-2 sm:gap-6 mb-12">
                    <?php while ($row = $products->fetch_assoc()): ?>
                        <?php
                        $product_id = (int) $row['id'];

                        // Get variant count
                        $variant_stmt = $conn->prepare("SELECT COUNT(*) as total FROM product_variants pv JOIN product_types pt ON pv.type_id = pt.id WHERE pt.product_id = ?");
                        $variant_stmt->bind_param("i", $product_id);
                        $variant_stmt->execute();
                        $variant_count = $variant_stmt->get_result()->fetch_assoc()['total'] ?? 0;
                        $variant_stmt->close();

                        // Get view count
                        $view_stmt = $conn->prepare("SELECT view_count FROM products WHERE id = ?");
                        $view_stmt->bind_param("i", $product_id);
                        $view_stmt->execute();
                        $view_result = $view_stmt->get_result()->fetch_assoc();
                        $view_count = (int) ($view_result['view_count'] ?? 0);
                        $view_stmt->close();

                        // Get sold count
                        $sold_stmt = $conn->prepare("SELECT SUM(quantity) as total_sold FROM sold_items WHERE product_id = ?");
                        $sold_stmt->bind_param("i", $product_id);
                        $sold_stmt->execute();
                        $sold_result = $sold_stmt->get_result()->fetch_assoc();
                        $total_sold = (int) ($sold_result['total_sold'] ?? 0);
                        $sold_stmt->close();

                        // Get rating
                        $rating_stmt = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                        $rating_stmt->bind_param("i", $product_id);
                        $rating_stmt->execute();
                        $rating_result = $rating_stmt->get_result()->fetch_assoc();
                        $avg_rating = $rating_result['avg_rating'] ?? 0;
                        $total_raters = $rating_result['total_raters'] ?? 0;
                        $rating_stmt->close();

                        // Calculate stars
                        $full_stars = floor($avg_rating);
                        $half_star = ($avg_rating - $full_stars >= 0.5) ? 1 : 0;
                        $empty_stars = 5 - $full_stars - $half_star;
                        ?>

                        <div
                            class="card-hover bg-white overflow-hidden flex flex-col group rounded-lg hover:shadow-xl transition-all duration-300">
                            <a href="<?= BASE_URL ?>/productview?id=<?= $product_id ?>" class="flex flex-col h-full">
                                <!-- Image Container - Fixed Square -->
                                <div class="relative w-full " style="padding-bottom: 100%;">
                                    <div class="absolute inset-0 p-2 sm:p-4">
                                        <?php if (!empty($row['main_image'])): ?>
                                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($row['main_image']) ?>"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500"
                                                loading="lazy">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                                <i class="fas fa-image text-2xl sm:text-4xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Hover Overlay (Desktop Only) -->
                                    <div class="hidden sm:flex absolute inset-0 items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300"
                                        style="background: rgba(0,0,0,0.2);">
                                        <span
                                            class="bg-white text-gray-900 px-4 py-2 rounded-full font-semibold shadow-lg text-sm">
                                            <i class="fas fa-eye mr-2"></i>View Details
                                        </span>
                                    </div>
                                </div>

                                <!-- Content Container -->
                                <div class="flex-1 flex flex-col p-2 sm:p-4">
                                    <!-- Product Name - Fixed 2 lines -->
                                    <h3 class="text-[15px] sm:text-sm text-black mb-1 sm:mb-2 line-clamp-2 uppercase font-semibold leading-tight"
                                        style="min-height: 2rem; font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h3>

                                    <!-- Description - Fixed 2 lines -->
                                    <p class="text-[13px] sm:text-xs text-gray-600 line-clamp-2 mb-1.5 sm:mb-2"
                                        style="min-height: 1.5rem; font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                        <?= htmlspecialchars($row['description'] ?? 'No description available.') ?>
                                    </p>

                                    <!-- Rating -->
                                    <div class="flex items-center gap-1 mb-1.5 sm:mb-2"
                                        style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                        <?php if ($total_raters > 0): ?>
                                            <div class="flex text-[9px] sm:text-[11px]">
                                                <?php for ($i = 0; $i < $full_stars; $i++): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php endfor; ?>
                                                <?php if ($half_star): ?>
                                                    <i class="fas fa-star-half-alt"></i>
                                                <?php endif; ?>
                                                <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                                                    <i class="far fa-star "></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span
                                                class="text-[8px] sm:text-[10px] text-gray-500 font-medium">(<?= $total_raters ?>)</span>
                                        <?php else: ?>
                                            <div class="flex text-gray-300 text-[9px] sm:text-[11px]">
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                    <i class="far fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="text-[8px] sm:text-[10px] text-gray-400">No rating</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Bottom Section -->
                                    <div class="mt-auto space-y-1 sm:space-y-2"
                                        style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                        <!-- View Count + Sold Count -->
                                        <?php if ($view_count > 0 || $total_sold > 0): ?>
                                            <div class="text-[8px] sm:text-[10px] font-medium">
                                                <?php if ($view_count > 0): ?>
                                                    <?= number_format($view_count); ?> viewing
                                                <?php endif; ?>
                                                <?php if ($view_count > 0 && $total_sold > 0): ?> | <?php endif; ?>
                                                <?php if ($total_sold > 0): ?>
                                                    <i class="fas fa-shopping-bag mr-0.5"></i>
                                                    <?= number_format($total_sold); ?> sold
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>

                <style>
                    .line-clamp-2 {
                        display: -webkit-box;
                        -webkit-line-clamp: 2;
                        line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                    }

                </style>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="p-4 sm:p-8" data-aos="fade-up">
                        <!-- Navigation Buttons -->
                        <div class="flex items-center justify-between gap-2 sm:gap-4 mb-4">
                            <a href="<?= BASE_URL ?>/shop?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                                class="pagination-btn px-3 py-2 sm:px-6 sm:py-3 text-xs sm:text-sm bg-white text-gray-700 shadow-sm <?= $page <= 1 ? 'disabled' : '' ?>">
                                <i class="fas fa-chevron-left mr-1 sm:mr-2 text-[10px] sm:text-xs"></i>Prev
                            </a>

                            <div class="flex items-center gap-1 sm:gap-2">
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <a href="<?= BASE_URL ?>/shop?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                        class="pagination-btn w-8 h-8 sm:w-12 sm:h-12 flex items-center justify-center text-xs sm:text-sm <?= $page === $i ? 'active' : 'bg-white text-black' ?> shadow-sm">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>

                            <a href="<?= BASE_URL ?>/shop?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>"
                                class="pagination-btn px-3 py-2 sm:px-6 sm:py-3 text-xs sm:text-sm bg-white text-gray-700 shadow-sm<?= $page >= $total_pages ? 'disabled' : '' ?>">
                                Next<i class="fas fa-chevron-right ml-1 sm:ml-2 text-[10px] sm:text-xs"></i>
                            </a>
                        </div>

                        <!-- Jump to Page -->
                        <div class="flex items-center justify-center gap-2 sm:gap-3">
                            <label class="text-xs sm:text-sm font-medium text-gray-700">Jump to page:</label>
                            <input type="number" min="1" max="<?= $total_pages ?>" value="<?= $page ?>"
                                onchange="jumpToPage(this.value)"
                                class="w-16 sm:w-20 px-2 py-1.5 sm:px-3 sm:py-2 border border-gray-300 focus:ring-2 focus:ring-primary text-center text-xs sm:text-sm">
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Desktop Sidebar Filter -->
            <aside class="desktop-sidebar-filter w-80 lg:order-2">
                <div class="sticky-filter p-6 ">
                    <div class="mb-6">
                        <h2 class="text-2xl mb-2 flex items-center"
                            style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                            <i class="fas fa-sliders-h  mr-3"></i>
                            Filters
                        </h2>
                        <?php if ($active_filters > 0): ?>
                            <div class="flex items-center justify-between mt-3">
                                <span class="filter-badge"><?= $active_filters ?> active</span>
                                <button onclick="clearAllFilters()"
                                    class="text-xs text-gray-600 hover:text-primary transition-colors font-medium">
                                    Clear All
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="GET" class="space-y-6">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-semibold  mb-3 items-center"
                                style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                <i class="fas fa-search mr-2"></i>
                                Search Products
                            </label>
                            <div class="relative">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>"
                                    placeholder="Search..."
                                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <i
                                    class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div>
                            <label class="text-sm font-semibold  mb-3 flex items-center justify-between"
                                style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                <span class="flex items-center">
                                    <i class="fas fa-th-large  mr-2"></i>
                                    Categories
                                </span>
                                <span
                                    class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full"><?= count($all_categories) ?></span>
                            </label>
                            <div class="space-y-2 max-h-80 overflow-y-auto pr-2">
                                <?php foreach ($all_categories as $cat_key => $cat_name): ?>
                                    <label
                                        class="flex items-center justify-between p-3 rounded-lg border border-gray-200 hover:border-primary hover:bg-orange-50 transition-all cursor-pointer <?= ($category_counts[$cat_key] ?? 0) == 0 ? 'opacity-50' : '' ?>">
                                        <div class="flex items-center flex-1"
                                            style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                            <input type="checkbox" name="category[]" value="<?= $cat_key ?>"
                                                <?= in_array($cat_key, $selected_categories) ? 'checked' : '' ?>
                                                <?= ($category_counts[$cat_key] ?? 0) == 0 ? 'disabled' : '' ?>
                                                class="mr-3 text-primary border-gray-300 rounded focus:ring-primary">
                                            <span class="text-sm "><?= htmlspecialchars($cat_name) ?></span>
                                        </div>
                                        <span
                                            class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full font-semibold">
                                            <?= $category_counts[$cat_key] ?? 0 ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Sort -->
                        <div>
                            <label class="block text-sm font-semibold  mb-3 items-center"
                                style="font-family: 'Montserrat', sans-serif; color: #2f1200;">
                                <i class="fas fa-sort  mr-2"></i>
                                Sort By
                            </label>
                            <select name="sort"
                                class="w-full py-3 px-4 border border-gray-300  focus:ring-2 focus:ring-primary bg-white">
                                <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)
                                </option>
                                <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)
                                </option>
                                <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            </select>
                        </div>

                        <!-- Actions -->
                        <div class="pt-4 border-t border-gray-200 space-y-3"
                            style="font-family: 'Montserrat', sans-serif; ">
                            <button type="submit"
                                class="w-full bg-black text-white px-6 py-3.5  hover:bg-primary-dark  transition-all shadow-md hover:shadow-lg">
                                <i class="fas fa-check mr-2"></i>Apply Filters
                            </button>
                            <button type="button" onclick="clearAllFilters()"
                                class="w-full bg-gray-100 text-gray-700 px-6 py-3.5  hover:bg-gray-200  transition-all">
                                <i class="fas fa-times mr-2"></i>Clear All
                            </button>
                        </div>
                    </form>
                </div>
            </aside>
        </div>
    </main>

    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>

    <script>
        // Initialize Lenis
        const lenis = new Lenis({
            duration: 3,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            direction: 'vertical',
            smooth: true
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);
        //  Universal Swiper initializer
        function initSwiper(selector, options) {
            if (document.querySelector(selector)) {
                return new Swiper(selector, options);
            }
        }

        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Mobile Filter Sidebar
        const mobileFilterToggle = document.getElementById('mobileFilterToggle');
        const mobileFilterSidebar = document.getElementById('mobileFilterSidebar');
        const mobileFilterOverlay = document.getElementById('mobileFilterOverlay');
        const closeMobileSidebar = document.getElementById('closeMobileSidebar');

        function openMobileFilter() {
            mobileFilterSidebar.classList.add('active');
            mobileFilterOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileFilter() {
            mobileFilterSidebar.classList.remove('active');
            mobileFilterOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        mobileFilterToggle?.addEventListener('click', openMobileFilter);
        closeMobileSidebar?.addEventListener('click', closeMobileFilter);
        mobileFilterOverlay?.addEventListener('click', closeMobileFilter);

        // Toggle mobile sections
        function toggleMobileSection(sectionId) {
            const content = document.getElementById(`mobile-${sectionId}-content`);
            const chevron = document.getElementById(`mobile-${sectionId}-chevron`);

            if (content && chevron) {
                content.classList.toggle('expanded');
                chevron.style.transform = content.classList.contains('expanded') ?
                    'rotate(180deg)' : 'rotate(0deg)';
            }
        }

        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && mobileFilterSidebar?.classList.contains('active')) {
                closeMobileFilter();
            }
        });
    </script>
</body>

</html>