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
    background-color: #28a745; /* Verification green */
    color: #ffffff;
    padding: 12px 16px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    font-weight: 500;
    transition: 0.3s ease;
}

.notification.success:hover {
    background-color: #1e7e34; /* Slightly darker on hover */
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
                            'id' => $row['product_id'],
                            'name' => $row['product_name'],
                            'color_id' => $row['color_id'],
                            'color_name' => $row['color_name'],
                            'color_code' => $row['color_code'],
                            'color_price' => $row['color_price'],
                            'color_image' => $color_image_path,
                            'variants' => $variants,
                            'initial_variant' => [
                                'variant_id' => $first_variant['variant_id'],
                                'size' => $first_variant['size'],
                                'price' => $pricing['final'],
                                'original_price' => $pricing['original'],
                                'discount' => $first_variant['discount'],
                                'origin' => $first_variant['origin'],
                                'variant_price' => $first_variant['price'],
                                'percent' => $first_variant['percent']
                            ]
                        ];

                        $product_json = json_encode($product);
                    ?>
                        <div class="product-data-item" data-product='<?= $product_json ?>'></div>
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
                this.init();
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

                return p.name.toLowerCase().includes(searchTerm) ||
                    (p.color_name && p.color_name.toLowerCase().includes(searchTerm)) ||
                    (initial.size && initial.size.toLowerCase().includes(searchTerm)) ||
                    (p.variants && p.variants.some(v => v.size.toLowerCase().includes(searchTerm)));
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
                window.scrollTo({ top: 0, behavior: 'smooth' });
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
                const base = parseFloat(variant_price || 0) + parseFloat(color_price || 0);
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
                const hasMultipleVariants = variants.length > 1;

                card.innerHTML = `
            ${initial.discount > 0 ? `
                <div class="discount-badge absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full z-10">
                    -${initial.discount}%
                </div>
            ` : ''}
            
            <div class="aspect-square mb-3 overflow-hidden rounded-lg">
                <img src="${product.color_image}" alt="${product.name}" class="w-full h-full object-cover product-image">
            </div>
            
            <h3 class="text-lg font-semibold mb-2 line-clamp-2 product-name">${product.name}</h3>
            
            <div class="mb-3 space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-600">Color:</span>
                    <div class="flex items-center gap-1">
                        ${product.color_code ? `<div class="w-4 h-4 rounded-full border" style="background-color: ${product.color_code}"></div>` : ''}
                        <span class="text-xs font-medium product-color">${product.color_name}</span>
                    </div>
                </div>
                <div class="text-xs text-gray-600">
                    Size: <span class="font-medium selected-size">${initial.size}</span>
                </div>
            </div>

            ${hasMultipleVariants ? `
                <div class="mb-3">
                    <p class="text-xs text-gray-600 mb-2">Available Sizes:</p>
                    <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                        ${variants.map((v, idx) => {
                            const pricing = this.calculateVariantPrice(v.price, product.color_price, v.percent, v.discount);
                            return `
                                <button type="button" 
                                    class="size-btn px-3 py-2 border rounded hover:border-orange-500 transition text-sm whitespace-nowrap flex-shrink-0 ${idx === 0 ? 'active' : 'border-gray-300'}"
                                    data-variant='${JSON.stringify({
                                        variant_id: v.variant_id,
                                        size: v.size,
                                        price: pricing.final,
                                        original_price: pricing.original,
                                        discount: v.discount,
                                        origin: v.origin,
                                        variant_price: v.price,
                                        percent: v.percent
                                    }).replace(/'/g, '&apos;')}'>
                                    ${v.size}
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            ` : ''}

            <div class="price-container mb-3">
                ${initial.discount > 0 ? `
                    <p class="text-xs text-gray-400 line-through original-price">₱${(initial.original_price || 0).toLocaleString()}</p>
                ` : '<p class="text-xs text-gray-400 line-through original-price hidden"></p>'}
                <div class="flex items-center justify-between">
                    <p class="text-xl font-bold text-orange-600 final-price">₱${(initial.price || 0).toLocaleString()}</p>
                    <span class="px-2 py-1 text-xs font-medium bg-black text-white rounded product-origin">
                        ${initial.origin || 'local'}
                    </span>
                </div>
            </div>
            
            <div class="space-y-2">
                <form action="index-product_view-page-4-AA" method="GET">
                    <input type="hidden" name="id" value="${product.id}">
                    <button type="submit" class="w-full bg-black text-white py-2 hover:bg-orange-500 text-sm transition rounded">
                        View Product
                    </button>
                </form>
                
                <form class="productForm" data-product-id="${product.id}">
                    <input type="hidden" name="product_id" value="${product.id}">
                    <input type="hidden" name="variant_id" value="${initial.variant_id}" class="variant-id-input">
                    <input type="hidden" name="selected_color_id" value="${product.color_id}" class="color-id-input">
                    <input type="hidden" name="selected_color_name" value="${product.color_name}" class="color-name-input">
                    <input type="hidden" name="color_price" value="${product.color_price}" class="color-price-input">
                    <input type="hidden" name="variant_price" value="${initial.variant_price}" class="variant-price-input">
                    <input type="hidden" name="total_price" value="${initial.price}" class="total-price-input">
                    <input type="hidden" name="discount" value="${initial.discount}" class="discount-input">
                    <input type="hidden" name="percent" value="${initial.percent}" class="percent-input">
                    <input type="hidden" name="origin" value="${initial.origin}" class="origin-input">
                    <input type="hidden" name="selected_size" value="${initial.size}" class="size-input">
                    <input type="hidden" name="return_url" value="index">
                    
                    <button type="submit" class="w-full bg-black text-white py-2 hover:bg-orange-500 text-sm flex items-center justify-center gap-2 transition rounded">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </form>
            </div>
        `;

                // Add size button listeners if there are multiple variants
                if (hasMultipleVariants) {
                    card.querySelectorAll('.size-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            e.preventDefault();
                            const variantData = JSON.parse(btn.dataset.variant);
                            const form = card.querySelector('.productForm');

                            if (form) {
                                // Update form inputs
                                form.querySelector('.variant-id-input').value = variantData.variant_id;
                                form.querySelector('.size-input').value = variantData.size;
                                form.querySelector('.variant-price-input').value = variantData.variant_price;
                                form.querySelector('.total-price-input').value = variantData.price;
                                form.querySelector('.discount-input').value = variantData.discount;
                                form.querySelector('.percent-input').value = variantData.percent;
                                form.querySelector('.origin-input').value = variantData.origin;

                                // Update displayed size
                                const sizeEl = card.querySelector('.selected-size');
                                if (sizeEl) sizeEl.textContent = variantData.size;

                                // Update displayed price
                                const priceEl = card.querySelector('.final-price');
                                if (priceEl) priceEl.textContent = `₱${variantData.price.toLocaleString()}`;

                                // Update origin badge
                                const originEl = card.querySelector('.product-origin');
                                if (originEl) originEl.textContent = variantData.origin;

                                // Update original price and discount badge
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

                            // Update active state
                            card.querySelectorAll('.size-btn').forEach(b => {
                                b.classList.remove('active');
                            });
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
            window.productFilter = new ProductFilter();
            new CartManager();
        });
    </script>

</body>

</html>