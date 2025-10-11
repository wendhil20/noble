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

// Simple query - get ALL products with LEFT JOIN
$material_query = "
    SELECT 
        p.id AS product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.sub_images,
        p.description,
        pt.id AS type_id,
        pt.type_name,
        pt.type_image,
        GROUP_CONCAT(
            DISTINCT
            JSON_OBJECT(
                'variant_id', pv.id,
                'name', pv.namevariant,
                'size', pv.size,
                'price', pv.price,
                'percent', pv.percent,
                'discount', pv.discount,
                'origin', pv.origin,
                'category', pv.category_name,
                'subcategory', pv.subcategory_name
            )
            ORDER BY pv.size
        ) AS variants,
        GROUP_CONCAT(
            DISTINCT
            JSON_OBJECT(
                'color_id', pc.id,
                'color_name', pc.color_name,
                'color_code', pc.color_code,
                'color_price', pc.price
            )
        ) AS colors
    FROM products p
    LEFT JOIN product_types pt ON p.id = pt.product_id
    LEFT JOIN product_variants pv ON pt.id = pv.type_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    GROUP BY p.id, pt.id
    ORDER BY p.id DESC
";

try {
    $material_results = mysqli_query($conn, $material_query);
    
    if (!$material_results) {
        error_log("Query failed: " . mysqli_error($conn));
        $material_results = false;
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

      <!-- In the products section -->
<main class="flex-1 p-6">
    <div class="mb-4 text-sm text-gray-600">
        Showing <span id="displayCount">0</span> of <span id="totalCount">0</span> products
    </div>

    <!-- Hidden product data -->
    <div id="productData" style="display: none;">
        <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
      <?php 
$productsGrouped = [];

while ($row = mysqli_fetch_assoc($material_results)):
    $product_id = $row['product_id'];
    
    // Each row is now ONE unique product
    $all_images = process_product_images($row['main_image'], $row['sub_images'], $row['type_image']);
    
    // Handle variants
    $variants = [];
    if (!empty($row['variants'])) {
        $variants = json_decode('[' . $row['variants'] . ']', true);
    } else {
        $variants = [[
            'variant_id' => 0,
            'name' => 'Standard',
            'size' => 'One Size',
            'price' => 0,
            'percent' => 0,
            'discount' => 0,
            'origin' => 'local',
            'category' => $row['codename'] ?? '',
            'subcategory' => ''
        ]];
    }
    
    // Handle colors - now comes as JSON array
    $colors = [];
    if (!empty($row['colors'])) {
        $colorsData = json_decode('[' . $row['colors'] . ']', true);
        foreach ($colorsData as $colorData) {
            if (!empty($colorData['color_id'])) {
                $colors[] = [
                    'id' => $colorData['color_id'],
                    'name' => $colorData['color_name'] ?? 'Default',
                    'code' => $colorData['color_code'] ?? '',
                    'price' => $colorData['color_price'] ?? 0
                ];
            }
        }
    }
    
    $first_variant = $variants[0] ?? [];
    $first_color = $colors[0] ?? ['id' => 0, 'name' => 'Default', 'code' => '', 'price' => 0];
    
    $pricing = calculate_price(
        $first_variant['price'] ?? 0, 
        $first_variant['percent'] ?? 0, 
        $first_variant['discount'] ?? 0
    );
    
    $product = [
        'id' => $product_id,
        'name' => $row['product_name'],
        'type_name' => $row['type_name'] ?? 'Standard',
        'type_id' => $row['type_id'] ?? 0,
        'images' => $all_images,
        'variants' => $variants,
        'colors' => $colors,
        'initial_variant' => [
            'variant_id' => $first_variant['variant_id'] ?? 0,
            'name' => $first_variant['name'] ?? 'Standard',
            'size' => $first_variant['size'] ?? 'One Size',
            'price' => $pricing['final'],
            'original_price' => $pricing['original'],
            'discount' => $first_variant['discount'] ?? 0,
            'origin' => $first_variant['origin'] ?? 'local',
            'variant_price' => $first_variant['price'] ?? 0,
            'percent' => $first_variant['percent'] ?? 0,
            'color' => $first_color['name'],
            'color_id' => $first_color['id'],
            'color_price' => $first_color['price']
        ]
    ];
    
    $product_json = json_encode($product);
?>
    <div class="product-data-item" data-product='<?= $product_json ?>'></div>
<?php endwhile; ?>
        <?php endif; ?>
    </div>

    <div id="productsGrid" class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8"></div>

    <div class="text-center">
        <button id="loadMoreBtn" class="hidden px-8 py-3 bg-black text-white hover:bg-orange-600">
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

// Load products from PHP and DEDUPLICATE by product ID
const allProductsRaw = [];
const productMap = new Map();

document.querySelectorAll('.product-data-item').forEach(item => {
    const productData = JSON.parse(item.getAttribute('data-product'));
    allProductsRaw.push(productData);
    
    const productId = productData.id;
    
    if (!productMap.has(productId)) {
        // First time seeing this product - create base entry
        productMap.set(productId, {
            id: productData.id,
            name: productData.name,
            type_name: productData.type_name || '',
            type_id: productData.type_id || 0,
            images: productData.images,
            variants: productData.variants || [],
            colors: [],
            initial_variant: productData.initial_variant || {}
        });
    }
    
    const product = productMap.get(productId);
    
    // Add color if not already in array
    if (productData.color && productData.color !== 'N/A') {
        const colorExists = product.colors.some(c => c.id === productData.color_id);
        if (!colorExists) {
            product.colors.push({
                id: productData.color_id,
                name: productData.color,
                code: productData.color_code,
                price: productData.color_price
            });
        }
    }
});

const allProducts = Array.from(productMap.values());

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
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filters.search = e.target.value.toLowerCase().trim();
                this.debouncedFilter();
            });
        }
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
        this.loadMore();
    }

    matchesSearch(p) {
        if (!this.filters.search) return true;
        
        const searchTerm = this.filters.search;
        const initial = p.initial_variant || {};
        
        return p.name.toLowerCase().includes(searchTerm) ||
               (initial.size && initial.size.toLowerCase().includes(searchTerm)) ||
               (p.type_name && p.type_name.toLowerCase().includes(searchTerm)) ||
               (p.colors && p.colors.some(c => c.name.toLowerCase().includes(searchTerm))) ||
               (p.variants && p.variants.some(v => 
                   v.size.toLowerCase().includes(searchTerm) ||
                   v.name.toLowerCase().includes(searchTerm)
               ));
    }

    matchesOrigin(p) {
        if (this.filters.origin === 'all') return true;
        const initial = p.initial_variant || {};
        return initial.origin === this.filters.origin;
    }

    matchesDiscount(p) {
        const initial = p.initial_variant || {};
        const discount = parseFloat(initial.discount || 0);
        return this.filters.discount === 'all' || 
               (this.filters.discount === 'discounted' && discount > 0) ||
               (this.filters.discount === 'no-discount' && discount === 0);
    }

    matchesPriceRange(p) {
        const initial = p.initial_variant || {};
        const price = initial.price || 0;
        return price >= this.filters.minPrice && price <= this.filters.maxPrice;
    }

    sortProducts() {
        this.filteredProducts.sort((a, b) => {
            const aInitial = a.initial_variant || {};
            const bInitial = b.initial_variant || {};
            
            switch (this.filters.sort) {
                case 'price-low': return (aInitial.price || 0) - (bInitial.price || 0);
                case 'price-high': return (bInitial.price || 0) - (aInitial.price || 0);
                case 'discount-high': return (bInitial.discount || 0) - (aInitial.discount || 0);
                case 'name': return a.name.localeCompare(b.name);
                default: return 0;
            }
        });
    }

    loadMore() {
        const startIndex = this.displayedCount;
        const endIndex = startIndex + this.productsPerPage;
        const toDisplay = this.filteredProducts.slice(startIndex, endIndex);

        toDisplay.forEach(product => {
            this.grid.appendChild(this.createProductCard(product));
        });

        this.displayedCount += toDisplay.length;
        
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

    calculateVariantPrice(base_price, percent, discount) {
        const base = parseFloat(base_price || 0);
        const markup_percent = parseFloat(percent || 0);
        const discount_percent = parseFloat(discount || 0);

        const price_with_markup = base + (base * markup_percent / 100);
        const final_price = price_with_markup - (price_with_markup * discount_percent / 100);

        return {
            original: price_with_markup,
            final: final_price
        };
    }

    createProductCard(product) {
        const card = document.createElement('article');
        card.className = 'product-card p-3 relative';
        
        const initial = product.initial_variant || {};
        const variants = product.variants || [];
        const colors = product.colors || [];
        
        // Determine if product has multiple options
        const hasMultipleVariants = variants.length > 1;
        const hasMultipleColors = colors.length > 1;
        const showAddToCart = !hasMultipleVariants && !hasMultipleColors;
        
        // Create variant buttons HTML (only if multiple variants)
        const variantButtons = hasMultipleVariants ? variants.map((v, idx) => {
            const pricing = this.calculateVariantPrice(v.price, v.percent, v.discount);
            const defaultColor = colors.length > 0 ? colors[0] : initial;
            
            return `
                <button type="button" 
                        class="variant-btn px-3 py-2 border rounded hover:border-orange-500 transition text-sm ${idx === 0 ? 'border-orange-500 bg-orange-50' : 'border-gray-300'}"
                        data-variant='${JSON.stringify({
                            variant_id: v.variant_id,
                            name: v.name,
                            size: v.size,
                            price: pricing.final,
                            original_price: pricing.original,
                            discount: v.discount,
                            origin: v.origin,
                            variant_price: v.price,
                            percent: v.percent,
                            color: defaultColor.name || initial.color,
                            color_id: defaultColor.id || initial.color_id,
                            color_price: defaultColor.price || initial.color_price
                        }).replace(/'/g, '&apos;')}'>
                    ${v.size}
                </button>
            `;
        }).join('') : '';
        
        // Create color buttons HTML (only if multiple colors)
        const colorButtons = hasMultipleColors ? colors.map((color, idx) => `
            <button type="button"
                    class="color-btn px-3 py-2 border rounded hover:border-orange-500 transition text-sm ${idx === 0 ? 'border-orange-500 bg-orange-50' : 'border-gray-300'}"
                    data-color='${JSON.stringify({
                        id: color.id,
                        name: color.name,
                        code: color.code,
                        price: color.price
                    }).replace(/'/g, '&apos;')}'>
                ${color.name}
            </button>
        `).join('') : '';
        
        card.innerHTML = `
            ${initial.discount > 0 ? `
                <div class="discount-badge absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full z-10">
                    -${initial.discount}%
                </div>
            ` : ''}
            
            <div class="aspect-square mb-3 overflow-hidden">
                <img src="${product.images?.[0] || '../img/placeholder.jpg'}" alt="${product.name}" class="w-full h-full object-contain product-image">
            </div>
            
            <h3 class="text-lg font-semibold mb-3 line-clamp-2 product-name">${product.name}</h3>
            
            ${hasMultipleColors ? `
                <div class="mb-3">
                    <p class="text-xs text-gray-600 mb-2 font-medium">Color:</p>
                    <div class="flex flex-wrap gap-2">
                        ${colorButtons}
                    </div>
                </div>
            ` : ''}
            
     

            <div class="mb-3 space-y-1">
                <div class="text-xs text-gray-600">
                    <div>Color: <span class="product-color">${initial.color || 'N/A'}</span></div>
                    <div>Size: <span class="selected-size">${initial.size || 'N/A'}</span></div>
                </div>
            </div>

            <div class="price-container mb-3">
                ${initial.discount > 0 ? `
                    <p class="text-xs text-gray-400 line-through original-price">₱${(initial.original_price || 0).toLocaleString()}</p>
                ` : '<p class="text-xs text-gray-400 line-through original-price hidden"></p>'}
                <div class="flex items-center justify-between">
                    <p class="text-xl font-bold text-black final-price">₱${(initial.price || 0).toLocaleString()}</p>
                    <span class="px-2 py-1 text-xs font-medium bg-black text-white product-origin">
                        ${initial.origin || 'local'}
                    </span>
                </div>
            </div>
            
            <div class="space-y-2">
                <form action="product_view" method="GET">
                    <input type="hidden" name="id" value="${product.id}">
                    <button type="submit" class="w-full bg-black text-white py-2 hover:bg-black text-sm transition">
                        View Product
                    </button>
                </form>
                
                ${showAddToCart ? `
                    <form class="productForm" data-product-id="${product.id}">
                        <input type="hidden" name="product_id" value="${product.id}">
                        <input type="hidden" name="selected_type" value="${product.type_name || ''}">
                        <input type="hidden" name="selected_variant" value="${initial.name || ''}" class="variant-name-input">
                        <input type="hidden" name="variant_id" value="${initial.variant_id || 0}" class="variant-id-input">
                        <input type="hidden" name="selected_color_id" value="${initial.color_id || 0}" class="color-id-input">
                        <input type="hidden" name="selected_color_name" value="${initial.color || ''}" class="color-name-input">
                        <input type="hidden" name="color_price" value="${initial.color_price || 0}" class="color-price-input">
                        <input type="hidden" name="variant_price" value="${initial.variant_price || 0}" class="variant-price-input">
                        <input type="hidden" name="total_price" value="${initial.price || 0}" class="total-price-input">
                        <input type="hidden" name="discount" value="${initial.discount || 0}" class="discount-input">
                        <input type="hidden" name="percent" value="${initial.percent || 0}" class="percent-input">
                        <input type="hidden" name="origin" value="${initial.origin || 'local'}" class="origin-input">
                        <input type="hidden" name="return_url" value="index">
                        
                        <button type="submit" class="w-full bg-black text-white py-2 hover:bg-gray-800 text-sm flex items-center justify-center gap-2 transition">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    </form>
                ` : ''}
            </div>
        `;
        
        // Only add variant listeners if there are multiple variants
        if (hasMultipleVariants) {
            card.querySelectorAll('.variant-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const variantData = JSON.parse(btn.dataset.variant);
                    const form = card.querySelector('.productForm');
                    
                    if (form) {
                        form.querySelector('.variant-id-input').value = variantData.variant_id;
                        form.querySelector('.variant-name-input').value = variantData.name;
                        
                        const sizeEl = card.querySelector('.selected-size');
                        if (sizeEl) sizeEl.textContent = variantData.size;
                        
                        form.querySelector('.variant-price-input').value = variantData.variant_price;
                        form.querySelector('.total-price-input').value = variantData.price;
                        form.querySelector('.discount-input').value = variantData.discount;
                        form.querySelector('.percent-input').value = variantData.percent;
                        form.querySelector('.origin-input').value = variantData.origin;
                        
                        const priceEl = card.querySelector('.final-price');
                        if (priceEl) priceEl.textContent = `₱${variantData.price.toLocaleString()}`;
                        
                        const originEl = card.querySelector('.product-origin');
                        if (originEl) originEl.textContent = variantData.origin;
                        
                        if (variantData.color) {
                            form.querySelector('.color-id-input').value = variantData.color_id;
                            form.querySelector('.color-name-input').value = variantData.color;
                            form.querySelector('.color-price-input').value = variantData.color_price;
                            const colorEl = card.querySelector('.product-color');
                            if (colorEl) colorEl.textContent = variantData.color;
                        }
                        
                        const originalPriceEl = card.querySelector('.original-price');
                        if (originalPriceEl) {
                            if (variantData.discount > 0) {
                                originalPriceEl.textContent = `₱${variantData.original_price.toLocaleString()}`;
                                originalPriceEl.classList.remove('hidden');
                            } else {
                                originalPriceEl.classList.add('hidden');
                            }
                        }
                        
                        let discountBadge = card.querySelector('.discount-badge');
                        if (variantData.discount > 0) {
                            if (discountBadge) {
                                discountBadge.textContent = `-${variantData.discount}%`;
                            } else {
                                discountBadge = document.createElement('div');
                                discountBadge.className = 'discount-badge absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full z-10';
                                discountBadge.textContent = `-${variantData.discount}%`;
                                card.insertBefore(discountBadge, card.firstChild);
                            }
                        } else if (discountBadge) {
                            discountBadge.remove();
                        }
                    }
                    
                    card.querySelectorAll('.variant-btn').forEach(b => {
                        b.classList.remove('border-orange-500', 'bg-orange-50');
                        b.classList.add('border-gray-300');
                    });
                    btn.classList.add('border-orange-500', 'bg-orange-50');
                    btn.classList.remove('border-gray-300');
                });
            });
        }
        
        // Only add color listeners if there are multiple colors
        if (hasMultipleColors) {
            card.querySelectorAll('.color-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const colorData = JSON.parse(btn.dataset.color);
                    this.updateProductCard(card, colorData, 'color');
                    
                    card.querySelectorAll('.color-btn').forEach(b => {
                        b.classList.remove('border-orange-500', 'bg-orange-50');
                        b.classList.add('border-gray-300');
                    });
                    btn.classList.add('border-orange-500', 'bg-orange-50');
                    btn.classList.remove('border-gray-300');
                });
            });
        }
        
        return card;
    }

    updateProductCard(card, data, type) {
        const form = card.querySelector('.productForm');
        
        if (type === 'variant') {
            card.querySelector('.selected-size').textContent = data.size;
            card.querySelector('.final-price').textContent = `₱${data.price.toLocaleString()}`;
            card.querySelector('.product-origin').textContent = data.origin;
            
            const originalPriceEl = card.querySelector('.original-price');
            if (data.discount > 0) {
                originalPriceEl.textContent = `₱${data.original_price.toLocaleString()}`;
                originalPriceEl.classList.remove('hidden');
            } else {
                originalPriceEl.classList.add('hidden');
            }
            
            let discountBadge = card.querySelector('.discount-badge');
            if (data.discount > 0) {
                if (discountBadge) {
                    discountBadge.textContent = `-${data.discount}%`;
                } else {
                    discountBadge = document.createElement('div');
                    discountBadge.className = 'discount-badge absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full z-10';
                    discountBadge.textContent = `-${data.discount}%`;
                    card.insertBefore(discountBadge, card.firstChild);
                }
            } else if (discountBadge) {
                discountBadge.remove();
            }
            
            form.querySelector('.variant-name-input').value = data.name;
            form.querySelector('.variant-id-input').value = data.variant_id;
            form.querySelector('.variant-price-input').value = data.variant_price;
            form.querySelector('.total-price-input').value = data.price;
            form.querySelector('.discount-input').value = data.discount;
            form.querySelector('.percent-input').value = data.percent;
            form.querySelector('.origin-input').value = data.origin;
            
        } else if (type === 'color') {
            card.querySelector('.product-color').textContent = data.name;
            form.querySelector('.color-id-input').value = data.id;
            form.querySelector('.color-name-input').value = data.name;
            form.querySelector('.color-price-input').value = data.price;
            
            const variantPrice = parseFloat(form.querySelector('.variant-price-input').value) || 0;
            const discount = parseFloat(form.querySelector('.discount-input').value) || 0;
            const percent = parseFloat(form.querySelector('.percent-input').value) || 0;
            
            let discountedPrice = variantPrice;
            if (percent > 0) discountedPrice -= variantPrice * (percent / 100);
            if (discount > 0) discountedPrice -= variantPrice * (discount / 100);
            
            const totalPrice = data.price + discountedPrice;
            form.querySelector('.total-price-input').value = totalPrice;
            card.querySelector('.final-price').textContent = `₱${totalPrice.toLocaleString()}`;
        }
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