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
        null;
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


$is_guest = !isset($_SESSION['user_id']);
// Modified query to get products with colors and variants
$material_query = "
    SELECT 
        p.id AS product_id,
        p.product_name,
        p.codename,
        pc.id AS color_id,
        pc.color_name,
        pc.color_code,
        pc.price AS color_price,
        pc.image AS color_image,
        GROUP_CONCAT(
            DISTINCT
            JSON_OBJECT(
                'variant_id', pv.id,
                'size', pv.size,
                'price', pv.price,
                'percent', pv.percent,
                'discount', pv.discount,
                'origin', pv.origin
            )
            ORDER BY pv.size
        ) AS variants
    FROM products p
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE pc.id IS NOT NULL
    GROUP BY p.id, pc.id
    ORDER BY p.id DESC, pc.id ASC
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

function safe_output($value, $default = '')
{
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

function calculate_price($variant_price, $color_price, $percent = 0, $discount = 0)
{
    $base = (float)$variant_price + (float)$color_price;
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
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <!-- Add before closing </head> tag -->
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
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
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
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
            background-color: #28a745;
            /* Verification green */
            color: #ffffff;
            padding: 12px 16px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            font-weight: 500;
            transition: 0.3s ease;
        }

        .notification.success:hover {
            background-color: #1e7e34;
            /* Slightly darker on hover */
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .size-btn {
            transition: all 0.2s;
        }

        .size-btn.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: var(--primary-color);
        }

        .scrollbar-thin::-webkit-scrollbar {
            height: 4px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .pagination-btn {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            background: white;
            color: #374151;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .pagination-btn:hover:not(.active):not(:disabled) {
            background: #f3f4f6;
            border-color: var(--primary-color);
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-ellipsis {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
        <div class="absolute inset-0 z-0">
            <img src="../img/saleandexplore/a.png" alt="Background" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        </div>

        <div class="relative z-10">
            <h1 class="text-3xl md:text-4xl lg:text-5xl text-white">Premium <span class="text-white">Collections</span></h1>
            <p class="text-sm md:text-base lg:text-lg text-white mt-2 md:mt-4 max-w-3xl mx-auto px-4">Discover high-quality products curated for your lifestyle.</p>

            <div class="mt-4 md:mt-6 lg:mt-8 max-w-2xl mx-auto px-4">
                <div class="relative">
                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Search products..."
                        class="w-full px-4 md:px-6 py-2 md:py-3 pr-10 md:pr-12 border-2 border-white rounded-full focus:outline-none focus:border-orange-500 text-gray-700 bg-white text-sm md:text-base">
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
                <p class="text-base md:text-lg text-black"></p>
            </div>
        </div>
    </section>

    <!-- Mobile Filter Button -->
    <div class="lg:hidden fixed bottom-4 left-4 right-4 z-30">
        <button onclick="openMobileFilters()" class="w-full px-4 py-3 bg-black text-white rounded-full shadow-lg flex items-center justify-center gap-2 hover:bg-gray-900">
            <i class="fas fa-filter"></i> Filters & Sort
        </button>
    </div>

    <!-- Main Layout -->
    <div class="lg:flex">
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:block w-80 p-6 sticky top-0 h-screen overflow-y-auto">
            <h2 class="text-xl mb-6"><i class="fas fa-sliders-h mr-2"></i>Filters</h2>

            <div class="space-y-6">
                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="origin">
                        <label class="text-sm font-semibold">Origin</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
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

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="mb-4 text-sm text-gray-600">
                Showing <span id="displayCount">0</span> of <span id="totalCount">0</span> products
            </div>

            <!-- Hidden product data -->
            <div id="productData" style="display: none;">
                <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($material_results)):
                        $variants = [];
                        if (!empty($row['variants'])) {
                            $variants = json_decode('[' . $row['variants'] . ']', true);
                        }

                        $first_variant = $variants[0] ?? ['variant_id' => 0, 'size' => 'One Size', 'price' => 0, 'percent' => 0, 'discount' => 0, 'origin' => 'local'];

                        $pricing = calculate_price(
                            $first_variant['price'],
                            $row['color_price'],
                            $first_variant['percent'],
                            $first_variant['discount']
                        );

                        $color_image_path = !empty($row['color_image']) ? '../../' . $row['color_image'] : '../img/placeholder.jpg';
                        $product = [
                            'id' => (int)$row['product_id'],
                            'name' => safe_output($row['product_name']), // ✅ Already sanitized
                            'color_id' => (int)$row['color_id'],
                            'color_name' => safe_output($row['color_name']), // ✅ Already sanitized
                            'color_code' => safe_output($row['color_code']),
                            'color_price' => (float)$row['color_price'],
                            'color_image' => safe_output($color_image_path),
                            'variants' => $variants,
                            'initial_variant' => [
                                'variant_id' => (int)$first_variant['variant_id'],
                                'size' => safe_output($first_variant['size']), // ✅ Add this
                                'price' => (float)$pricing['final'],
                                'original_price' => (float)$pricing['original'],
                                'discount' => (float)$first_variant['discount'],
                                'origin' => safe_output($first_variant['origin']), // ✅ Add this
                                'variant_price' => (float)$first_variant['price'],
                                'percent' => (float)$first_variant['percent']
                            ]
                        ];

                        $product_json = json_encode($product, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    ?>
                        <div class="product-data-item" data-product='<?= htmlspecialchars($product_json, ENT_QUOTES, 'UTF-8') ?>'></div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <div id="productsGrid" class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8 items-start"></div>

            <div id="paginationContainer" class="flex justify-center items-center gap-2 flex-wrap">
                <!-- Pagination will be inserted here -->
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
            PRODUCTS_PER_PAGE: 20
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
                this.currentPage = 1;
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
                this.paginationContainer = document.getElementById('paginationContainer');
                this.displayCount = document.getElementById('displayCount');
                this.totalCount = document.getElementById('totalCount');
                this.noResults = document.getElementById('noResults');

                this.debouncedFilter = Utils.debounce(() => this.applyFilters(), CONFIG.DEBOUNCE_DELAY);
                this.fuzzyThreshold = 0.65; // 65% similarity required for fuzzy match
                this.init();
            }

            // Levenshtein Distance Algorithm for Fuzzy Matching
            levenshteinDistance(str1, str2) {
                const len1 = str1.length;
                const len2 = str2.length;
                const matrix = Array(len2 + 1).fill(null).map(() => Array(len1 + 1).fill(0));

                for (let i = 0; i <= len1; i++) matrix[0][i] = i;
                for (let j = 0; j <= len2; j++) matrix[j][0] = j;

                for (let j = 1; j <= len2; j++) {
                    for (let i = 1; i <= len1; i++) {
                        const cost = str1[i - 1] === str2[j - 1] ? 0 : 1;
                        matrix[j][i] = Math.min(
                            matrix[j][i - 1] + 1, // deletion
                            matrix[j - 1][i] + 1, // insertion
                            matrix[j - 1][i - 1] + cost // substitution
                        );
                    }
                }

                return matrix[len2][len1];
            }

            // Calculate similarity percentage between two strings
            calculateSimilarity(str1, str2) {
                const longer = str1.length > str2.length ? str1 : str2;
                const shorter = str1.length > str2.length ? str2 : str1;

                if (longer.length === 0) return 1.0;

                const distance = this.levenshteinDistance(longer, shorter);
                return (longer.length - distance) / longer.length;
            }

            // Fuzzy match a search word against a target word
            fuzzyMatch(searchWord, targetWord) {
                searchWord = searchWord.toLowerCase();
                targetWord = targetWord.toLowerCase();

                // Exact match or contains (fastest check first)
                if (targetWord.includes(searchWord)) return true;

                // If search word is very short (1-2 chars), only do exact/contains match
                if (searchWord.length <= 2) return false;

                // Fuzzy matching for longer words
                const similarity = this.calculateSimilarity(searchWord, targetWord);
                return similarity >= this.fuzzyThreshold;
            }

            // Check if search word fuzzy matches any word in the text
            fuzzyMatchInText(searchWord, text) {
                const textWords = text.split(/\s+/);
                return textWords.some(textWord => this.fuzzyMatch(searchWord, textWord));
            }

            init() {
                this.initSearchFilters();
                this.initPriceFilters();
                this.initSortFilters();
                this.initFilterButtons();
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

                this.currentPage = 1;
                this.renderPage();
            }

            matchesSearch(p) {
                if (!this.filters.search) return true;

                const searchTerm = this.filters.search;
                const initial = p.initial_variant || {};

                // Split search term into words for flexible matching
                const searchWords = searchTerm.split(/\s+/).filter(word => word.length > 0);

                // Create searchable text from product data
                const searchableText = [
                    p.name,
                    p.color_name,
                    initial.size,
                    initial.origin,
                    ...(p.variants || []).map(v => v.size)
                ].filter(Boolean).join(' ').toLowerCase();

                // FUZZY MATCHING: Check if ANY search word fuzzy matches ANY part of the searchable text
                return searchWords.some(searchWord =>
                    this.fuzzyMatchInText(searchWord, searchableText)
                );
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
                        case 'price-low':
                            return (aInitial.price || 0) - (bInitial.price || 0);
                        case 'price-high':
                            return (bInitial.price || 0) - (aInitial.price || 0);
                        case 'discount-high':
                            return (bInitial.discount || 0) - (aInitial.discount || 0);
                        case 'name':
                            return a.name.localeCompare(b.name);
                        default:
                            return 0;
                    }
                });
            }

            renderPage() {
                const totalPages = Math.ceil(this.filteredProducts.length / this.productsPerPage);
                const startIndex = (this.currentPage - 1) * this.productsPerPage;
                const endIndex = startIndex + this.productsPerPage;
                const productsToShow = this.filteredProducts.slice(startIndex, endIndex);

                // Clear grid
                this.grid.innerHTML = '';

                // Render products
                productsToShow.forEach(product => {
                    this.grid.appendChild(this.createProductCard(product));
                });

                // Update counts
                const actualEnd = Math.min(endIndex, this.filteredProducts.length);
                const actualStart = this.filteredProducts.length > 0 ? startIndex + 1 : 0;
                this.displayCount.textContent = `${actualStart}-${actualEnd}`;
                this.totalCount.textContent = this.filteredProducts.length;

                // Show/hide elements
                if (this.filteredProducts.length === 0) {
                    this.noResults.classList.remove('hidden');
                    this.grid.classList.add('hidden');
                    this.paginationContainer.classList.add('hidden');
                } else {
                    this.noResults.classList.add('hidden');
                    this.grid.classList.remove('hidden');
                    this.paginationContainer.classList.remove('hidden');
                }

                // Render pagination
                this.renderPagination(totalPages);

                // Scroll to top smoothly
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            renderPagination(totalPages) {
                if (totalPages <= 1) {
                    this.paginationContainer.innerHTML = '';
                    return;
                }

                let paginationHTML = '';

                // Previous button
                paginationHTML += `
                    <button class="pagination-btn" ${this.currentPage === 1 ? 'disabled' : ''} 
                            onclick="productFilter.goToPage(${this.currentPage - 1})">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                `;

                // Page numbers
                const pageNumbers = this.getPageNumbers(totalPages);
                pageNumbers.forEach(page => {
                    if (page === '...') {
                        paginationHTML += `<span class="pagination-ellipsis">...</span>`;
                    } else {
                        paginationHTML += `
                            <button class="pagination-btn ${page === this.currentPage ? 'active' : ''}"
                                    onclick="productFilter.goToPage(${page})">
                                ${page}
                            </button>
                        `;
                    }
                });

                // Next button
                paginationHTML += `
                    <button class="pagination-btn" ${this.currentPage === totalPages ? 'disabled' : ''}
                            onclick="productFilter.goToPage(${this.currentPage + 1})">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                `;

                this.paginationContainer.innerHTML = paginationHTML;
            }

            getPageNumbers(totalPages) {
                const pages = [];
                const current = this.currentPage;

                if (totalPages <= 7) {
                    for (let i = 1; i <= totalPages; i++) {
                        pages.push(i);
                    }
                } else {
                    if (current <= 3) {
                        for (let i = 1; i <= 4; i++) pages.push(i);
                        pages.push('...');
                        pages.push(totalPages);
                    } else if (current >= totalPages - 2) {
                        pages.push(1);
                        pages.push('...');
                        for (let i = totalPages - 3; i <= totalPages; i++) pages.push(i);
                    } else {
                        pages.push(1);
                        pages.push('...');
                        for (let i = current - 1; i <= current + 1; i++) pages.push(i);
                        pages.push('...');
                        pages.push(totalPages);
                    }
                }

                return pages;
            }

            goToPage(page) {
                const totalPages = Math.ceil(this.filteredProducts.length / this.productsPerPage);
                if (page < 1 || page > totalPages) return;

                this.currentPage = page;
                this.renderPage();
            }

            calculateVariantPrice(variant_price, color_price, percent, discount) {
                // ✅ SIMPLE FORMULA: variant + color (no markup deduction)
                const final_price = parseFloat(variant_price || 0) + parseFloat(color_price || 0);
                const original_price = final_price;

                return {
                    original: original_price,
                    final: final_price
                };
            }

           // REPLACE ang createProductCard method na ito sa loob ng ProductFilter class

createProductCard(product) {
    const card = document.createElement('article');
    card.className = 'product-card p-3 relative';

    const initial = product.initial_variant || {};
    const variants = product.variants || [];
    const hasMultipleVariants = variants.length > 1;



    // Image container
    const imageContainer = document.createElement('div');
    imageContainer.className = 'aspect-square mb-3 overflow-hidden rounded-lg';
    const img = document.createElement('img');
    img.src = product.color_image;
    img.alt = product.name;
    img.className = 'w-full h-full object-contain product-image';
    imageContainer.appendChild(img);
    card.appendChild(imageContainer);

    // Product name
    const productName = document.createElement('h3');
    productName.className = 'text-lg font-semibold mb-2 line-clamp-2 product-name';
    productName.textContent = product.name;
    card.appendChild(productName);

    // Info container
    const infoContainer = document.createElement('div');
    infoContainer.className = 'mb-3 space-y-1';
    
    // Color info
    const colorDiv = document.createElement('div');
    colorDiv.className = 'flex items-center gap-2';
    colorDiv.innerHTML = `<span class="text-xs text-gray-600">Color:</span>`;
    
    const colorInfoDiv = document.createElement('div');
    colorInfoDiv.className = 'flex items-center gap-1';
    
    if (product.color_code) {
        const colorBox = document.createElement('div');
        colorBox.className = 'w-4 h-4 rounded-full border';
        colorBox.style.backgroundColor = product.color_code;
        colorInfoDiv.appendChild(colorBox);
    }
    
    const colorNameSpan = document.createElement('span');
    colorNameSpan.className = 'text-xs font-medium product-color';
    colorNameSpan.textContent = product.color_name;
    colorInfoDiv.appendChild(colorNameSpan);
    
    colorDiv.appendChild(colorInfoDiv);
    infoContainer.appendChild(colorDiv);

    // Size info
    const sizeDiv = document.createElement('div');
    sizeDiv.className = 'text-xs text-gray-600';
    sizeDiv.innerHTML = 'Size: ';
    const sizeSpan = document.createElement('span');
    sizeSpan.className = 'font-medium selected-size';
    sizeSpan.textContent = initial.size;
    sizeDiv.appendChild(sizeSpan);
    infoContainer.appendChild(sizeDiv);
    
    card.appendChild(infoContainer);

    // Size buttons (if multiple variants)
    if (hasMultipleVariants) {
        const sizesContainer = document.createElement('div');
        sizesContainer.className = 'mb-3';
        
        const sizesLabel = document.createElement('p');
        sizesLabel.className = 'text-xs text-gray-600 mb-2';
        sizesLabel.textContent = 'Available Sizes:';
        sizesContainer.appendChild(sizesLabel);
        
        const sizeButtonsDiv = document.createElement('div');
        sizeButtonsDiv.className = 'flex gap-2 overflow-x-auto pb-2 scrollbar-thin';
        
        variants.forEach((v, idx) => {
            const pricing = this.calculateVariantPrice(v.price, product.color_price, v.percent, v.discount);
            
            const sizeBtn = document.createElement('button');
            sizeBtn.type = 'button';
            sizeBtn.className = `size-btn px-3 py-2 border rounded hover:border-orange-500 transition text-sm whitespace-nowrap flex-shrink-0 ${idx === 0 ? 'active' : 'border-gray-300'}`;
            sizeBtn.textContent = v.size;
            
            // Store variant data
            sizeBtn.dataset.variantId = v.variant_id;
            sizeBtn.dataset.size = v.size;
            sizeBtn.dataset.price = pricing.final;
            sizeBtn.dataset.originalPrice = pricing.original;
            sizeBtn.dataset.discount = v.discount;
            sizeBtn.dataset.origin = v.origin;
            sizeBtn.dataset.variantPrice = v.price;
            sizeBtn.dataset.percent = v.percent;
            
            sizeButtonsDiv.appendChild(sizeBtn);
        });
        
        sizesContainer.appendChild(sizeButtonsDiv);
        card.appendChild(sizesContainer);
    }

    // Price container - HIDE if may multiple variants at walang manual selection pa
    const priceContainer = document.createElement('div');
    priceContainer.className = `price-container mb-3 ${hasMultipleVariants ? 'price-container-hidden' : ''}`;
    
    // ADD THIS CLASS FOR CONTROL
    if (hasMultipleVariants) {
        priceContainer.style.opacity = '0.5';
        priceContainer.style.pointerEvents = 'none';
    }

    const priceRow = document.createElement('div');
    priceRow.className = 'flex items-center justify-between gap-2';
    
    const priceWithDiscount = document.createElement('div');
    priceWithDiscount.className = 'flex items-center gap-2';
    
    const finalPrice = document.createElement('p');
    finalPrice.className = 'text-xl font-bold text-orange-600 final-price';
    finalPrice.textContent = `₱${(initial.price || 0).toLocaleString()}`;
    priceWithDiscount.appendChild(finalPrice);

    // Discount badge sa tabi ng price
    if (initial.discount > 0) {
        const discountBadgeInline = document.createElement('span');
        discountBadgeInline.className = 'discount-badge-inline bg-red-500 text-white text-xs px-2 py-1 rounded-full font-semibold';
        discountBadgeInline.textContent = `-${initial.discount}%`;
        priceWithDiscount.appendChild(discountBadgeInline);
    }

    priceRow.appendChild(priceWithDiscount);
    
    const originBadge = document.createElement('span');
    originBadge.className = 'px-2 py-1 text-xs font-medium bg-black text-white rounded product-origin';
    originBadge.textContent = initial.origin || 'local';
    
    priceRow.appendChild(originBadge);
    priceContainer.appendChild(priceRow);
    card.appendChild(priceContainer);

    // Buttons container
    const buttonsDiv = document.createElement('div');
    buttonsDiv.className = 'space-y-2';
    
    // View Product form
    const viewForm = document.createElement('form');
    viewForm.action = 'index-product_view-page-4-AA';
    viewForm.method = 'GET';
    
    const viewInput = document.createElement('input');
    viewInput.type = 'hidden';
    viewInput.name = 'id';
    viewInput.value = product.id;
    viewForm.appendChild(viewInput);
    
    const viewButton = document.createElement('button');
    viewButton.type = 'submit';
    viewButton.className = 'w-full bg-black text-white py-2 hover:bg-orange-500 text-sm transition rounded';
    viewButton.textContent = 'View Product';
    viewForm.appendChild(viewButton);
    buttonsDiv.appendChild(viewForm);

    // Add to Cart form
    const cartForm = document.createElement('form');
    cartForm.className = 'productForm';
    cartForm.dataset.productId = product.id;
    
    // Hidden inputs
    const hiddenInputs = [
        {name: 'product_id', value: product.id},
        {name: 'variant_id', value: initial.variant_id, className: 'variant-id-input'},
        {name: 'selected_color_id', value: product.color_id, className: 'color-id-input'},
        {name: 'selected_color_name', value: product.color_name, className: 'color-name-input'},
        {name: 'color_price', value: product.color_price, className: 'color-price-input'},
        {name: 'variant_price', value: initial.variant_price, className: 'variant-price-input'},
        {name: 'total_price', value: initial.price, className: 'total-price-input'},
        {name: 'discount', value: initial.discount, className: 'discount-input'},
        {name: 'percent', value: initial.percent, className: 'percent-input'},
        {name: 'origin', value: initial.origin, className: 'origin-input'},
        {name: 'selected_size', value: initial.size, className: 'size-input'},
        {name: 'return_url', value: 'index'}
    ];
    
    hiddenInputs.forEach(inputData => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputData.name;
        input.value = inputData.value;
        if (inputData.className) input.className = inputData.className;
        cartForm.appendChild(input);
    });
    
    const cartButton = document.createElement('button');
    cartButton.type = 'submit';
    cartButton.className = 'w-full bg-black text-white py-2 hover:bg-orange-500 text-sm flex items-center justify-center gap-2 transition rounded';
    cartButton.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
    cartForm.appendChild(cartButton);
    
    buttonsDiv.appendChild(cartForm);
    card.appendChild(buttonsDiv);

    // AUTOMATICALLY TRIGGER FIRST SIZE SELECTION
    setTimeout(() => {
        if (hasMultipleVariants) {
            // Auto-select first size on load
            const firstSizeBtn = card.querySelector('.size-btn.active');
            if (firstSizeBtn) {
                firstSizeBtn.click();
            }
        }
    }, 0);

    // Size button event listeners
    if (hasMultipleVariants) {
        card.querySelectorAll('.size-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                const variantData = {
                    variant_id: btn.dataset.variantId,
                    size: btn.dataset.size,
                    price: parseFloat(btn.dataset.price),
                    original_price: parseFloat(btn.dataset.originalPrice),
                    discount: parseFloat(btn.dataset.discount),
                    origin: btn.dataset.origin,
                    variant_price: parseFloat(btn.dataset.variantPrice),
                    percent: parseFloat(btn.dataset.percent)
                };
                
                const form = card.querySelector('.productForm');
                if (form) {
                    form.querySelector('.variant-id-input').value = variantData.variant_id;
                    form.querySelector('.size-input').value = variantData.size;
                    form.querySelector('.variant-price-input').value = variantData.variant_price;
                    form.querySelector('.total-price-input').value = variantData.price;
                    form.querySelector('.discount-input').value = variantData.discount;
                    form.querySelector('.percent-input').value = variantData.percent;
                    form.querySelector('.origin-input').value = variantData.origin;

                    const sizeEl = card.querySelector('.selected-size');
                    if (sizeEl) sizeEl.textContent = variantData.size;

                    const priceEl = card.querySelector('.final-price');
                    if (priceEl) priceEl.textContent = `₱${variantData.price.toLocaleString()}`;

                    // UPDATE discount badge inline
                    let discountBadgeInline = card.querySelector('.discount-badge-inline');
                    if (variantData.discount > 0) {
                        if (discountBadgeInline) {
                            discountBadgeInline.textContent = `-${variantData.discount}%`;
                        } else {
                            discountBadgeInline = document.createElement('span');
                            discountBadgeInline.className = 'discount-badge-inline bg-red-500 text-white text-xs px-2 py-1 rounded-full font-semibold';
                            discountBadgeInline.textContent = `-${variantData.discount}%`;
                            priceEl.parentElement.appendChild(discountBadgeInline);
                        }
                    } else if (discountBadgeInline) {
                        discountBadgeInline.remove();
                    }

                    const originEl = card.querySelector('.product-origin');
                    if (originEl) originEl.textContent = variantData.origin;

                    // UPDATE: Show price container when size is selected
                    const priceContainer = card.querySelector('.price-container');
                    if (priceContainer) {
                        priceContainer.style.opacity = '1';
                        priceContainer.style.pointerEvents = 'auto';
                    }
                }

                card.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

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

                        // UPDATE CART COUNT - This is what's missing!
                        this.updateCartCount(data.cart_count || data.total_items);

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

            // NEW METHOD: Update cart count badge
            updateCartCount(count) {
                // Update all cart count elements
                const cartCountElements = document.querySelectorAll('[data-cart-count], .cart-count');
                cartCountElements.forEach(el => {
                    el.textContent = count;
                    // Show/hide badge based on count
                    const badge = el.closest('.cart-count');
                    if (badge) {
                        if (count > 0) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                });

                // Update modal cart count if exists
                const modalCartCount = document.getElementById('modal-cart-count');
                if (modalCartCount) {
                    modalCartCount.textContent = `${count} items`;
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
            window.productFilter = new ProductFilter();
            new CartManager();
        });
    </script>

</body>

</html>