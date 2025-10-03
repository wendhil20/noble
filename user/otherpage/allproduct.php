<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Restore session from remember_token
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
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// Session check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$subcategory_filter = $_GET['sub'] ?? '';
$discount_filter = $_GET['discount'] ?? '';

// Build query
$material_query = "
    SELECT 
        pv.*, 
        pv.origin,
        pt.type_name,
        pt.type_image,
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.sub_images,
        p.description,
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id 
        AND pc.id = (SELECT MIN(id) FROM product_colors WHERE product_id = p.id)
";

$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($category_filter)) {
    $where_conditions[] = "pv.category_name = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

if (!empty($subcategory_filter)) {
    $where_conditions[] = "pv.subcategory_name = ?";
    $params[] = $subcategory_filter;
    $param_types .= "s";
}

if (!empty($discount_filter)) {
    if ($discount_filter == "20") {
        $where_conditions[] = "pv.discount <= ?";
        $params[] = 20;
        $param_types .= "i";
    } elseif ($discount_filter == "30") {
        $where_conditions[] = "pv.discount = ?";
        $params[] = 30;
        $param_types .= "i";
    }
}

if (!empty($where_conditions)) {
    $material_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$material_query .= " ORDER BY pv.discount DESC, pv.percent ASC, p.id, pc.id";

try {
    if (!empty($params)) {
        $stmt = $conn->prepare($material_query);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $material_results = $stmt->get_result();
    } else {
        $material_results = mysqli_query($conn, $material_query);
    }
} catch (Exception $e) {
    error_log("Database query error: " . $e->getMessage());
    $material_results = false;
}

function safe_output($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

function calculate_price($base_price, $percent = 0, $discount = 0) {
    $base = (float)$base_price;
    $markup_percent = (float)$percent;
    $discount_percent = (float)$discount;

    $price_with_markup = $base + ($base * $markup_percent / 100);
    $final_price = $price_with_markup - ($price_with_markup * $discount_percent / 100);

    return [
        'original' => $price_with_markup,
        'final' => $final_price,
        'savings' => $price_with_markup - $final_price
    ];
}

function process_product_images($main_image, $sub_images, $type_image) {
    $images = [];

    if (!empty($main_image)) {
        $images[] = '../../' . $main_image;
    }

    if (!empty($sub_images)) {
        $sub_images_array = json_decode($sub_images, true);
        if (is_array($sub_images_array)) {
            foreach ($sub_images_array as $sub_img) {
                if (!empty($sub_img)) {
                    $clean_path = str_replace('../', '', $sub_img);
                    $images[] = '../../' . $clean_path;
                }
            }
        }
    }

    if (!empty($type_image)) {
        $images[] = '../../' . $type_image;
    }

    $images = array_unique($images);
    if (empty($images)) {
        $images[] = '../img/placeholder.jpg';
    }

    return $images;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/favicon.ico">
    <title>Products - Noble Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
      <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #f97316;
            --secondary-color: #ea580c;
        }

        .product-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }

        .filter-button.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .filter-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .filter-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .mobile-filter-sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 85%;
            max-width: 320px;
            height: 100vh;
            background: white;
            z-index: 50;
            transition: left 0.3s;
            overflow-y: auto;
        }

        .mobile-filter-sidebar.active {
            left: 0;
        }

        .dropdown-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s;
        }

        .dropdown-content.active {
            max-height: 500px;
        }

        .chevron {
            transition: transform 0.3s;
        }

        .chevron.rotate {
            transform: rotate(180deg);
        }

        .range-slider {
            -webkit-appearance: none;
            height: 6px;
            border-radius: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 16px 20px;
            border-radius: 8px;
            color: white;
            transform: translateX(100%);
            transition: transform 0.3s;
        }

        .notification.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .notification.show {
            transform: translateX(0);
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="bg-gray-50 font-roboto">
    <?php include '../navbar/top.php'; ?>

    <!-- Mobile Filter Overlay -->
    <div class="filter-overlay lg:hidden" id="filterOverlay"></div>
    
    <!-- Mobile Sidebar -->
    <aside class="mobile-filter-sidebar" id="mobileFilterSidebar">
          <div class="flex items-center justify-between mb-6 bg-black p-2">
                <h2 class="text-xl text-white"><i class="fas fa-sliders-h mr-2"></i>Filters</h2>
                <button onclick="closeMobileFilters()" class="p-2 hover:bg-gray-100 rounded-full text-white transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <div class="p-3">
          

            <div class="space-y-6">
        
                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-origin">
                        <label class="text-sm font-semibold">Origin</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="mobile-origin" class="dropdown-content mt-3 space-y-2">
                        <button class="filter-button mobile-origin-filter w-full px-3 py-2 rounded-lg border active text-left" data-origin="all">All</button>
                        <button class="filter-button mobile-origin-filter w-full px-3 py-2 rounded-lg border text-left" data-origin="local">Local</button>
                        <button class="filter-button mobile-origin-filter w-full px-3 py-2 rounded-lg border text-left" data-origin="international">International</button>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-price">
                        <label class="text-sm font-semibold">Price</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="mobile-price" class="dropdown-content mt-3 space-y-3">
                        <div>
                            <label class="text-xs">Min: ₱<span id="mobileMinValue">0</span></label>
                            <input type="range" id="mobileMinPrice" class="w-full range-slider" min="0" max="10000" value="0" step="100">
                        </div>
                        <div>
                            <label class="text-xs">Max: ₱<span id="mobileMaxValue">10,000</span></label>
                            <input type="range" id="mobileMaxPrice" class="w-full range-slider" min="0" max="10000" value="10000" step="100">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-discount">
                        <label class="text-sm font-semibold">Discount</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="mobile-discount" class="dropdown-content mt-3 space-y-2">
                        <button class="filter-button mobile-discount-filter w-full px-3 py-2 rounded-lg border active text-left" data-discount="all">All</button>
                        <button class="filter-button mobile-discount-filter w-full px-3 py-2 rounded-lg border text-left" data-discount="discounted">On Sale</button>
                        <button class="filter-button mobile-discount-filter w-full px-3 py-2 rounded-lg border text-left" data-discount="no-discount">Regular Price</button>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-sort">
                        <label class="text-sm font-semibold">Sort</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="mobile-sort" class="dropdown-content mt-3">
                        <select id="mobileSortFilter" class="w-full px-3 py-2 border rounded-lg">
                            <option value="default">Default</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="discount-high">Discount: High to Low</option>
                            <option value="name">Name: A to Z</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Header with Background Image -->
<header class="relative text-center py-10 md:py-16 lg:py-20 overflow-hidden">
    <!-- Background Image -->
    <div class="absolute inset-0 z-0">
        <img src="../img/saleandexplore/a.png" alt="Background" class="w-full h-full object-cover">
        <!-- Dark Overlay -->
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    </div>
    
    <!-- Content -->
    <div class="relative z-10">
        <h1 class="text-3xl md:text-4xl lg:text-5xl text-white">Premium <span class="text-white">Collections</span></h1>
        <p class="text-sm md:text-base lg:text-lg text-white mt-2 md:mt-4 max-w-3xl mx-auto px-4">Discover high-quality products curated for your lifestyle.</p>
        
        <!-- Search Bar -->
        <div class="mt-4 md:mt-6 lg:mt-8 max-w-2xl mx-auto px-4">
            <div class="relative">
                <input 
                    type="text" 
                    id="searchInput" 
                    placeholder="Search products..." 
                    class="w-full px-4 md:px-6 py-2 md:py-3 pr-10 md:pr-12 border-2 border-white rounded-full focus:outline-none focus:border-orange-500 text-gray-700 bg-white text-sm md:text-base"
                >
                <button class="absolute right-2 md:right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-orange-500">
                    <i class="fas fa-search text-base md:text-xl"></i>
                </button>
            </div>
        </div>
    </div>
</header>

<section class="bg-black text-white py-2">
  <div class="max-w-6xl mx-auto px-1 text-center md:text-left">
    <div class="inline-flex flex-wrap gap-3 items-center">
      <p class="text-base md:text-lg text-black">
      
      </p>
    </div>
  </div>
</section>

<!-- Mobile Filter Button - Floating -->
<div class="lg:hidden fixed bottom-4 left-4 right-4 z-30">
    <button onclick="openMobileFilters()" class="w-full px-4 py-3 bg-black text-white rounded-full shadow-lg flex items-center justify-center gap-2 hover:bg-gray-900">
        <i class="fas fa-filter"></i> Filters & Sort
    </button>
</div>
    <!-- Main Layout -->
    <div class="lg:flex">
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:block w-80 p-6 sticky top-0 h-screen overflow-y-auto">
            <h2 class="text-xl  mb-6"><i class="fas fa-sliders-h mr-2"></i>Filters</h2>
            
            <div class="space-y-6">
                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="origin">
                        <label class="text-sm font-semibold">Origin</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="origin" class="dropdown-content mt-3 space-y-2">
                        <button class="filter-button origin-filter w-full px-3 py-2 rounded-lg border active text-left" data-origin="all">All</button>
                        <button class="filter-button origin-filter w-full px-3 py-2 rounded-lg border text-left" data-origin="local">Local</button>
                        <button class="filter-button origin-filter w-full px-3 py-2 rounded-lg border text-left" data-origin="international">International</button>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="price">
                        <label class="text-sm font-semibold">Price</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="price" class="dropdown-content mt-3 space-y-3">
                        <div>
                            <label class="text-xs">Min: ₱<span id="minValue">0</span></label>
                            <input type="range" id="minPrice" class="w-full range-slider" min="0" max="10000" value="0" step="100">
                        </div>
                        <div>
                            <label class="text-xs">Max: ₱<span id="maxValue">10,000</span></label>
                            <input type="range" id="maxPrice" class="w-full range-slider" min="0" max="10000" value="10000" step="100">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="discount">
                        <label class="text-sm font-semibold">Discount</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="discount" class="dropdown-content mt-3 space-y-2">
                        <button class="filter-button discount-filter w-full px-3 py-2 rounded-lg border active text-left" data-discount="all">All</button>
                        <button class="filter-button discount-filter w-full px-3 py-2 rounded-lg border text-left" data-discount="discounted">On Sale</button>
                        <button class="filter-button discount-filter w-full px-3 py-2 rounded-lg border text-left" data-discount="no-discount">Regular Price</button>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="sort">
                        <label class="text-sm font-semibold">Sort</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div id="sort" class="dropdown-content mt-3">
                        <select id="sortFilter" class="w-full px-3 py-2 border rounded-lg">
                            <option value="default">Default</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="discount-high">Discount: High to Low</option>
                            <option value="name">Name: A to Z</option>
                        </select>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Products -->
        <main class="flex-1 p-6">
            <div class="mb-4 text-sm text-gray-600">
                Showing <span id="displayCount">0</span> of <span id="totalCount">0</span> products
            </div>

            <!-- Hidden product data -->
            <div id="productData" style="display: none;">
                <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($material_results)):
                        $pricing = calculate_price($row['price'], $row['percent'] ?? 0, $row['discount'] ?? 0);
                        $all_images = process_product_images($row['main_image'], $row['sub_images'], $row['type_image']);
                        $discount = (float)($row['discount'] ?? 0);
                        
                        $product_json = json_encode([
                            'id' => $row['product_id'],
                            'name' => $row['namevariant'],
                            'price' => $pricing['final'],
                            'original_price' => $pricing['original'],
                            'discount' => $discount,
                            'origin' => $row['origin'] ?? 'local',
                            'color' => $row['color'] ?? 'N/A',
                            'size' => $row['size'] ?? 'N/A',
                            'images' => $all_images,
                            'type_name' => $row['type_name'] ?? '',
                            'variant_id' => $row['id'] ?? 0,
                            'color_id' => $row['color_id'] ?? 0,
                            'color_price' => $row['color_price'] ?? 0,
                            'variant_price' => $row['price'] ?? 0,
                            'percent' => $row['percent'] ?? 0
                        ]);
                    ?>
                        <div class="product-data-item" data-product='<?= $product_json ?>'></div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <div id="productsGrid" class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8"></div>

            <div class="text-center">
                <button id="loadMoreBtn" class="hidden px-8 py-3 bg-black text-white hover:bg-orange-600 ">
                    Load More Products
                </button>
            </div>

            <div id="noResults" class="hidden text-center py-20">
                <h3 class="text-2xl font-bold mb-3">No products found</h3>
                <p class="text-gray-500">Try adjusting your filters</p>
            </div>
        </main>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <div id="notificationContainer"></div>

<script>
'use strict';

const CONFIG = {
    DEBOUNCE_DELAY: 300,
    PRODUCTS_PER_PAGE: 6
};

const Utils = {
    debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    },
    formatNumber(num) {
        return new Intl.NumberFormat('en-US').format(num);
    }
};

// Load products from PHP
const allProducts = [];
document.querySelectorAll('.product-data-item').forEach(item => {
    const productData = JSON.parse(item.getAttribute('data-product'));
    allProducts.push(productData);
});

class MobileFilterManager {
    constructor() {
        this.overlay = document.getElementById('filterOverlay');
        this.sidebar = document.getElementById('mobileFilterSidebar');
        this.bindEvents();
    }

    bindEvents() {
        this.overlay?.addEventListener('click', () => this.close());
    }

    open() {
        this.overlay?.classList.add('active');
        this.sidebar?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.overlay?.classList.remove('active');
        this.sidebar?.classList.remove('active');
        document.body.style.overflow = '';
    }
}

class DropdownManager {
    constructor() {
        this.init();
    }

    init() {
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            const targetId = toggle.getAttribute('data-target');
            const dropdown = document.getElementById(targetId);
            const chevron = toggle.querySelector('.chevron');
            
            if (!dropdown || !chevron) return;

            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                dropdown.classList.toggle('active');
                chevron.classList.toggle('rotate');
            });
        });
    }
}

class ProductFilter {
    constructor() {
        this.allProducts = allProducts;
        this.filteredProducts = [];
        this.displayedCount = 0;
        this.productsPerPage = CONFIG.PRODUCTS_PER_PAGE;
        
        this.filters = {
            search: '',
            origin: 'all',
            discount: 'all',
            minPrice: 0,
            maxPrice: 10000,
            sort: 'default'
        };

        this.grid = document.getElementById('productsGrid');
        this.loadMoreBtn = document.getElementById('loadMoreBtn');
        this.displayCount = document.getElementById('displayCount');
        this.totalCount = document.getElementById('totalCount');
        this.noResults = document.getElementById('noResults');

        this.debouncedFilter = Utils.debounce(() => this.applyFilters(), CONFIG.DEBOUNCE_DELAY);
        this.init();
    }

    init() {
        this.initSearchFilters();
        this.initPriceFilters();
        this.initSortFilters();
        this.initFilterButtons();
        this.initLoadMore();
        this.applyFilters();
    }

    initSearchFilters() {
        // Add the main search input from header
        ['searchInput', 'searchFilter', 'mobileSearchFilter'].forEach(id => {
            const input = document.getElementById(id);
            input?.addEventListener('input', (e) => {
                this.filters.search = e.target.value.toLowerCase().trim();
                this.syncSearchInputs(id, e.target.value);
                this.debouncedFilter();
            });
        });
    }

    syncSearchInputs(sourceId, value) {
        // Sync all search inputs
        ['searchInput', 'searchFilter', 'mobileSearchFilter'].forEach(id => {
            if (id !== sourceId) {
                const input = document.getElementById(id);
                if (input && input.value !== value) input.value = value;
            }
        });
    }

    initPriceFilters() {
        const pairs = [
            ['minPrice', 'minValue', 'minPrice'],
            ['maxPrice', 'maxValue', 'maxPrice'],
            ['mobileMinPrice', 'mobileMinValue', 'minPrice'],
            ['mobileMaxPrice', 'mobileMaxValue', 'maxPrice']
        ];

        pairs.forEach(([sliderId, valueId, filterKey]) => {
            const slider = document.getElementById(sliderId);
            const display = document.getElementById(valueId);
            
            if (slider && display) {
                slider.addEventListener('input', (e) => {
                    const value = parseInt(e.target.value);
                    this.filters[filterKey] = value;
                    display.textContent = Utils.formatNumber(value);
                    this.syncPriceSliders(filterKey, value);
                    this.debouncedFilter();
                });
            }
        });
    }

    initSortFilters() {
        ['sortFilter', 'mobileSortFilter'].forEach(id => {
            const select = document.getElementById(id);
            select?.addEventListener('change', (e) => {
                this.filters.sort = e.target.value;
                this.syncSelects(id, e.target.value);
                this.applyFilters();
            });
        });
    }

    initFilterButtons() {
        document.querySelectorAll('.origin-filter, .mobile-origin-filter').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.setActive('.origin-filter, .mobile-origin-filter', e.target.dataset.origin);
                this.filters.origin = e.target.dataset.origin;
                this.applyFilters();
            });
        });

        document.querySelectorAll('.discount-filter, .mobile-discount-filter').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.setActive('.discount-filter, .mobile-discount-filter', e.target.dataset.discount);
                this.filters.discount = e.target.dataset.discount;
                this.applyFilters();
            });
        });
    }

    initLoadMore() {
        this.loadMoreBtn?.addEventListener('click', () => this.loadMore());
    }

    setActive(selector, value) {
        document.querySelectorAll(selector).forEach(btn => {
            const isActive = btn.dataset.origin === value || btn.dataset.discount === value;
            btn.classList.toggle('active', isActive);
        });
    }

    syncPriceSliders(type, value) {
        const ids = type === 'minPrice' 
            ? ['minPrice', 'mobileMinPrice', 'minValue', 'mobileMinValue']
            : ['maxPrice', 'mobileMaxPrice', 'maxValue', 'mobileMaxValue'];
        
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'INPUT') el.value = value;
            else el.textContent = Utils.formatNumber(value);
        });
    }

    syncSelects(sourceId, value) {
        const targetId = sourceId.includes('mobile') ? 'sortFilter' : 'mobileSortFilter';
        const target = document.getElementById(targetId);
        if (target && target.value !== value) target.value = value;
    }

    applyFilters() {
        this.filteredProducts = this.allProducts.filter(p => 
            this.matchesSearch(p) && this.matchesOrigin(p) && 
            this.matchesDiscount(p) && this.matchesPriceRange(p)
        );

        if (this.filters.sort !== 'default') {
            this.sortProducts();
        }

        this.displayedCount = 0;
        this.grid.innerHTML = '';
        this.updateDisplay();
    }

    matchesSearch(p) {
        if (!this.filters.search) return true;
        
        const searchTerm = this.filters.search;
        return p.name.toLowerCase().includes(searchTerm) ||
               (p.color && p.color.toLowerCase().includes(searchTerm)) ||
               (p.size && p.size.toLowerCase().includes(searchTerm)) ||
               (p.type_name && p.type_name.toLowerCase().includes(searchTerm));
    }

    matchesOrigin(p) {
        return this.filters.origin === 'all' || p.origin === this.filters.origin;
    }

    matchesDiscount(p) {
        const discount = parseFloat(p.discount || 0);
        return this.filters.discount === 'all' || 
               (this.filters.discount === 'discounted' && discount > 0) ||
               (this.filters.discount === 'no-discount' && discount === 0);
    }

    matchesPriceRange(p) {
        return p.price >= this.filters.minPrice && p.price <= this.filters.maxPrice;
    }

    sortProducts() {
        this.filteredProducts.sort((a, b) => {
            switch (this.filters.sort) {
                case 'price-low': return a.price - b.price;
                case 'price-high': return b.price - a.price;
                case 'discount-high': return b.discount - a.discount;
                case 'name': return a.name.localeCompare(b.name);
                default: return 0;
            }
        });
    }

    loadMore() {
        this.displayedCount += this.productsPerPage;
        this.updateDisplay();
    }

    updateDisplay() {
        const toDisplay = this.filteredProducts.slice(0, this.displayedCount + this.productsPerPage);
        const newProducts = toDisplay.slice(this.displayedCount);

        newProducts.forEach(product => {
            this.grid.appendChild(this.createProductCard(product));
        });

        this.displayedCount = toDisplay.length;
        this.displayCount.textContent = this.displayedCount;
        this.totalCount.textContent = this.filteredProducts.length;

        if (this.displayedCount < this.filteredProducts.length) {
            this.loadMoreBtn.classList.remove('hidden');
        } else {
            this.loadMoreBtn.classList.add('hidden');
        }

        if (this.filteredProducts.length === 0) {
            this.noResults.classList.remove('hidden');
            this.grid.classList.add('hidden');
        } else {
            this.noResults.classList.add('hidden');
            this.grid.classList.remove('hidden');
        }
    }

     createProductCard(product) {
        const card = document.createElement('article');
        card.className = 'product-card p-3 relative';
        
        card.innerHTML = `
            ${product.discount > 0 ? `
                <div class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full z-10">
                    -${product.discount}%
                </div>
            ` : ''}
            
            <div class="aspect-square mb-3 overflow-hidden">
                <img src="${product.images[0]}" alt="${product.name}" class="w-full h-full object-contain">
            </div>
            
            <h3 class="text-lg font-semibold mb-2 line-clamp-2">${product.name}</h3>
            
   
            <div class="mb-3 space-y-1">
                <div class="text-xs text-gray-600">
                    <div>Color: ${product.color}</div>
                    <div>Size: ${product.size}</div>
                </div>
            </div>
            <div class="mb-3">
                ${product.discount > 0 ? `
                    <p class="text-xs text-gray-400 line-through">₱${product.original_price.toLocaleString()}</p>
                ` : ''}
                <div class="flex items-center justify-between">
                    <p class="text-xl font-bold text-black">₱${product.price.toLocaleString()}</p>
                    <span class="px-2 py-1 text-xs font-medium bg-black text-white ">
                        ${product.origin}
                    </span>
                </div>
            </div>
            
            <div class="space-y-2">
                <form action="product_view" method="GET">
                    <input type="hidden" name="id" value="${product.id}">
                    <button type="submit" class="w-full bg-black text-white py-2  hover:bg-black text-sm transition">
                        View Product
                    </button>
                </form>
                
                <form class="productForm" data-product-id="${product.id}">
                    <input type="hidden" name="product_id" value="${product.id}">
                    <input type="hidden" name="selected_type" value="${product.type_name}">
                    <input type="hidden" name="selected_variant" value="${product.name}">
                    <input type="hidden" name="variant_id" value="${product.variant_id}">
                    <input type="hidden" name="selected_color_id" value="${product.color_id}">
                    <input type="hidden" name="selected_color_name" value="${product.color}">
                    <input type="hidden" name="color_price" value="${product.color_price}">
                    <input type="hidden" name="variant_price" value="${product.variant_price}">
                    <input type="hidden" name="total_price" value="${product.price}">
                    <input type="hidden" name="discount" value="${product.discount}">
                    <input type="hidden" name="percent" value="${product.percent}">
                    <input type="hidden" name="origin" value="${product.origin}">
                    <input type="hidden" name="return_url" value="index">
                    
                    <button type="submit" class="w-full bg-black text-white py-2     hover:bg-gray-800 text-sm flex items-center justify-center gap-2 transition">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </form>
            </div>
        `;
        
        return card;
    }
}

class CartManager {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('productForm')) {
                this.handleAddToCart(e);
            }
        });
    }

    async handleAddToCart(event) {
        event.preventDefault();
        const form = event.target;
        const button = form.querySelector('button[type="submit"]');
        if (!button || button.disabled) return;

        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<div class="loading-spinner"></div> Adding...';

        try {
            const response = await fetch('../cart/add_to_cart', {
                method: 'POST',
                body: new FormData(form)
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message || 'Added to cart!', 'success');
                button.innerHTML = '✓ Added!';
                setTimeout(() => {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }, 2000);
            } else {
                throw new Error(data.message || 'Failed to add to cart');
            }
        } catch (error) {
            console.error('Cart error:', error);
            this.showNotification(error.message, 'error');
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    }

    showNotification(message, type) {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        container.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

function openMobileFilters() {
    window.mobileFilterManager?.open();
}

function closeMobileFilters() {
    window.mobileFilterManager?.close();
}

document.addEventListener('DOMContentLoaded', () => {
    window.mobileFilterManager = new MobileFilterManager();
    new DropdownManager();
    new ProductFilter();
    new CartManager();
});
</script>
</body>
</html>