<?php
// index-allproduct-page-3.php
session_name("nobleuser");
session_start();

include ROOT_PATH . '/connection/connect.php';

mysqli_query($conn, "SET SESSION group_concat_max_len = 10000;");

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

$is_guest = !isset($_SESSION['user_id']);

$material_query = "
    SELECT 
        p.id AS product_id,
        p.product_name,
        p.codename,
         p.min_order_qty,   -- ← dagdag ito
        pc.id AS color_id,
        pc.color_name,
        pc.color_code,
        pc.price AS color_price,
        pc.image AS color_image,
        pc.image2 AS color_image2,
GROUP_CONCAT(
    DISTINCT
    JSON_OBJECT(
        'variant_id', pv.id,
        'size', pv.size,
        'price', pv.price,
        'original_price', pv.original_price,
        'percent', pv.percent,
        'discount', pv.discount,
        'origin', pv.origin,
        'timer_discount_percent', pv.timer_discount_percent,
        'timer_discount_active', pv.timer_discount_active,
        'timer_discount_start', pv.timer_discount_start,
        'timer_discount_end', pv.timer_discount_end
    )
    ORDER BY pv.size
) AS variants
    FROM products p
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE pc.id IS NOT NULL
    AND p.is_archived = 0
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
    $final_price = (float) $variant_price + (float) $color_price;
    $original_price = (float) $variant_price;
    return [
        'original' => $original_price,
        'final' => $final_price,
        'savings' => 0
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
    <style>
        :root {
            --primary-color: #f97316;
            --secondary-color: #ea580c;
        }

        .product-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            transition: all 0.3s ease;
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
            bottom: 20px;
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
            color: #fff;
            padding: 12px 16px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            font-weight: 500;
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

        .product-image {
            transition: opacity 0.3s ease-in-out;
        }

        .product-image-2 {
            transition: opacity 0.3s ease-in-out;
            position: absolute;
            top: 0;
            left: 0;
        }

        .notification.error {
            background-color: #dc2626;
            color: #fff;
            padding: 12px 16px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            font-weight: 500;
        }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <?php include ROOT_PATH . '/user/otherpage/push-notification.php'; ?>

    <!-- Hero Banner - Desktop only -->
    <div class="hidden md:block mb-4">
        <div class="container mx-auto px-1 bg-contain bg-center h-96"
            style="background-image: url('<?= BASE_URL ?>/user/img/display2.webp');"></div>
    </div>

    <!-- Filter Overlay -->
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
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle"
                        data-target="mobile-origin">
                        <label class="text-sm font-semibold">Origin</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                    <div id="mobile-origin" class="dropdown-content mt-3 space-y-2">
                        <button
                            class="filter-button mobile-origin-filter w-full px-3 py-2 rounded-lg border active text-left"
                            data-origin="all">All</button>
                        <button class="filter-button mobile-origin-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-origin="local">Local</button>
                        <button class="filter-button mobile-origin-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-origin="international">International</button>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle"
                        data-target="mobile-price">
                        <label class="text-sm font-semibold">Price</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                    <div id="mobile-price" class="dropdown-content mt-3 space-y-3">
                        <div>
                            <label class="text-xs">Min: ₱<span id="mobileMinValue">0</span></label>
                            <input type="range" id="mobileMinPrice" class="w-full range-slider" min="0" max="10000"
                                value="0" step="100">
                        </div>
                        <div>
                            <label class="text-xs">Max: ₱<span id="mobileMaxValue">10,000</span></label>
                            <input type="range" id="mobileMaxPrice" class="w-full range-slider" min="0" max="10000"
                                value="10000" step="100">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle"
                        data-target="mobile-discount">
                        <label class="text-sm font-semibold">Discount</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                    <div id="mobile-discount" class="dropdown-content mt-3 space-y-2">
                        <button
                            class="filter-button mobile-discount-filter w-full px-3 py-2 rounded-lg border active text-left"
                            data-discount="all">All</button>
                        <button
                            class="filter-button mobile-discount-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-discount="discounted">On Sale</button>
                        <button
                            class="filter-button mobile-discount-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-discount="no-discount">Regular Price</button>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle"
                        data-target="mobile-sort">
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

    <!-- Mobile Filter Button -->
    <div class="lg:hidden fixed bottom-4 left-4 right-4 z-30 mb-2">
        <button onclick="openMobileFilters()"
            class="w-full px-4 py-2 bg-black text-white rounded-lg flex items-center justify-center gap-2 hover:bg-gray-900">
            <i class="fas fa-filter"></i> Filters & Sort
        </button>
    </div>

    <!-- Main Layout -->
    <div class="lg:flex gap-4">
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
                        <button class="filter-button origin-filter w-full px-3 py-2 rounded-lg border active text-left"
                            data-origin="all">All</button>
                        <button class="filter-button origin-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-origin="local">Local</button>
                        <button class="filter-button origin-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-origin="international">International</button>
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
                            <input type="range" id="minPrice" class="w-full range-slider" min="0" max="10000" value="0"
                                step="100">
                        </div>
                        <div>
                            <label class="text-xs">Max: ₱<span id="maxValue">10,000</span></label>
                            <input type="range" id="maxPrice" class="w-full range-slider" min="0" max="10000"
                                value="10000" step="100">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle"
                        data-target="discount">
                        <label class="text-sm font-semibold">Discount</label>
                        <svg class="chevron w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                    <div id="discount" class="dropdown-content mt-3 space-y-2">
                        <button
                            class="filter-button discount-filter w-full px-3 py-2 rounded-lg border active text-left"
                            data-discount="all">All</button>
                        <button class="filter-button discount-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-discount="discounted">On Sale</button>
                        <button class="filter-button discount-filter w-full px-3 py-2 rounded-lg border text-left"
                            data-discount="no-discount">Regular Price</button>
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
        <main class="flex-1 p-2 pb-20 lg:pb-2">
            <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
                <div class="relative flex-1 max-w-xs">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <i class="fas fa-search text-sm"></i>
                    </span>
                    <input type="text" id="searchInput" placeholder="Search products..."
                        class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-white">
                </div>
                <div class="text-sm text-gray-600 whitespace-nowrap">
                    Showing <span id="displayCount">0</span> of <span id="totalCount">0</span> products
                </div>
            </div>

            <div id="productData" style="display: none;">
                <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($material_results)):
                        $variants = [];
                        if (!empty($row['variants'])) {
                            $variants = json_decode('[' . $row['variants'] . ']', true);
                        }

                        $first_variant = $variants[0] ?? [
                            'variant_id' => 0,
                            'size' => 'One Size',
                            'price' => 0,
                            'original_price' => 0,
                            'percent' => 0,
                            'discount' => 0,
                            'origin' => 'local',
                            'timer_discount_percent' => 0,
                            'timer_discount_active' => 0,
                            'timer_discount_start' => null,
                            'timer_discount_end' => null
                        ];

                        // Check if timer is active right now
                        $now = time();
                        $timer_active = false;

                        if (
                            !empty($first_variant['timer_discount_active']) &&
                            !empty($first_variant['timer_discount_start']) &&
                            !empty($first_variant['timer_discount_end'])
                        ) {
                            $t_start = strtotime($first_variant['timer_discount_start']);
                            $t_end = strtotime($first_variant['timer_discount_end']);
                            if ($now >= $t_start && $now <= $t_end) {
                                $timer_active = true;
                            }
                        }

                        $pricing = calculate_price(
                            $first_variant['price'],
                            $row['color_price'],
                            $first_variant['percent'],
                            $first_variant['discount']
                        );

                       $color_image_path = !empty($row['color_image']) ? BASE_URL . '/' . ltrim($row['color_image'], '/') : BASE_URL . '/user/img/placeholder.jpg';

                        $product = [
                            'id' => (int) $row['product_id'],
                            'name' => safe_output($row['product_name']),
                            'color_id' => (int) $row['color_id'],
                            'color_name' => safe_output($row['color_name']),
                            'color_code' => safe_output($row['color_code']),
                            'color_price' => (float) $row['color_price'],
                            'color_image' => safe_output($color_image_path),
'color_image2' => !empty($row['color_image2']) ? BASE_URL . '/' . ltrim($row['color_image2'], '/') : '',
                            'min_order_qty' => (int) ($row['min_order_qty'] ?? 1),
                            'variants' => $variants,
                            'initial_variant' => [
                                'variant_id' => (int) $first_variant['variant_id'],
                                'size' => safe_output($first_variant['size']),
                                'price' => (float) $pricing['final'],
                                'original_price' => (float) ($first_variant['original_price'] ?? $pricing['final']),
                                'discount' => (float) $first_variant['discount'],
                                'origin' => safe_output($first_variant['origin']),
                                'variant_price' => (float) $first_variant['price'],
                                'percent' => (float) $first_variant['percent'],
                                'timer_active' => $timer_active,
                            ]
                        ];

                        $product_json = json_encode($product, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <div class="product-data-item"
                            data-product='<?= htmlspecialchars($product_json, ENT_QUOTES, 'UTF-8') ?>'></div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <div id="productsGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6 mb-8">
            </div>

            <div id="paginationContainer" class="flex justify-center items-center gap-2 flex-wrap"></div>

            <div id="noResults" class="hidden text-center py-20">
                <h3 class="text-2xl font-bold mb-3">No products found</h3>
                <p class="text-gray-500">Try adjusting your filters</p>
            </div>
        </main>
    </div>

    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>
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
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func(...args), wait);
                };
            },
            formatNumber(num) {
                return new Intl.NumberFormat('en-US').format(num);
            }
        };

        const allProducts = [];
        const productGroups = {};

        document.querySelectorAll('.product-data-item').forEach(item => {
            const productData = JSON.parse(item.getAttribute('data-product'));
            if (!productGroups[productData.id]) {
                productGroups[productData.id] = [];
            }
            productGroups[productData.id].push(productData);
        });

        Object.values(productGroups).forEach(group => {
            allProducts.push(group[0]);
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
            constructor() { this.init(); }
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
                    search: '', origin: 'all', discount: 'all',
                    minPrice: 0, maxPrice: 10000000, sort: 'default'
                };
                this.grid = document.getElementById('productsGrid');
                this.paginationContainer = document.getElementById('paginationContainer');
                this.displayCount = document.getElementById('displayCount');
                this.totalCount = document.getElementById('totalCount');
                this.noResults = document.getElementById('noResults');
                this.debouncedFilter = Utils.debounce(() => this.applyFilters(), CONFIG.DEBOUNCE_DELAY);
                this.fuzzyThreshold = 0.65;
                this.init();
            }

            levenshteinDistance(str1, str2) {
                const len1 = str1.length, len2 = str2.length;
                const matrix = Array(len2 + 1).fill(null).map(() => Array(len1 + 1).fill(0));
                for (let i = 0; i <= len1; i++) matrix[0][i] = i;
                for (let j = 0; j <= len2; j++) matrix[j][0] = j;
                for (let j = 1; j <= len2; j++) {
                    for (let i = 1; i <= len1; i++) {
                        const cost = str1[i - 1] === str2[j - 1] ? 0 : 1;
                        matrix[j][i] = Math.min(matrix[j][i - 1] + 1, matrix[j - 1][i] + 1, matrix[j - 1][i - 1] + cost);
                    }
                }
                return matrix[len2][len1];
            }

            calculateSimilarity(str1, str2) {
                const longer = str1.length > str2.length ? str1 : str2;
                const shorter = str1.length > str2.length ? str2 : str1;
                if (longer.length === 0) return 1.0;
                return (longer.length - this.levenshteinDistance(longer, shorter)) / longer.length;
            }

            fuzzyMatch(searchWord, targetWord) {
                searchWord = searchWord.toLowerCase();
                targetWord = targetWord.toLowerCase();
                if (targetWord.includes(searchWord)) return true;
                if (searchWord.length <= 2) return false;
                return this.calculateSimilarity(searchWord, targetWord) >= this.fuzzyThreshold;
            }

            fuzzyMatchInText(searchWord, text) {
                return text.split(/\s+/).some(w => this.fuzzyMatch(searchWord, w));
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
                    ['minPrice', 'minValue', 'minPrice'], ['maxPrice', 'maxValue', 'maxPrice'],
                    ['mobileMinPrice', 'mobileMinValue', 'minPrice'], ['mobileMaxPrice', 'mobileMaxValue', 'maxPrice']
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
                if (this.filters.sort !== 'default') this.sortProducts();
                this.currentPage = 1;
                this.renderPage();
            }

            matchesSearch(p) {
                if (!this.filters.search) return true;
                const initial = p.initial_variant || {};
                const searchWords = this.filters.search.split(/\s+/).filter(w => w.length > 0);
                const searchableText = [p.name, p.color_name, initial.size, initial.origin,
                ...(p.variants || []).map(v => v.size)].filter(Boolean).join(' ').toLowerCase();
                return searchWords.some(w => this.fuzzyMatchInText(w, searchableText));
            }

            matchesOrigin(p) {
                if (this.filters.origin === 'all') return true;
                return (p.initial_variant || {}).origin === this.filters.origin;
            }

            matchesDiscount(p) {
                const discount = parseFloat((p.initial_variant || {}).discount || 0);
                if (this.filters.discount === 'no-discount') return discount === 0;
                if (this.filters.discount === 'discounted') {
                    const max = this.filters.minDiscount || 100;
                    return discount > 0 && discount <= max;
                }
                return true;
            }

            matchesPriceRange(p) {
                const price = (p.initial_variant || {}).price || 0;
                return price >= this.filters.minPrice && price <= this.filters.maxPrice;
            }

            sortProducts() {
                this.filteredProducts.sort((a, b) => {
                    const ai = a.initial_variant || {}, bi = b.initial_variant || {};
                    switch (this.filters.sort) {
                        case 'price-low': return (ai.price || 0) - (bi.price || 0);
                        case 'price-high': return (bi.price || 0) - (ai.price || 0);
                        case 'discount-high': return (bi.discount || 0) - (ai.discount || 0);
                        case 'name': return a.name.localeCompare(b.name);
                        default: return 0;
                    }
                });
            }

            renderPage() {
                const totalPages = Math.ceil(this.filteredProducts.length / this.productsPerPage);
                const startIndex = (this.currentPage - 1) * this.productsPerPage;
                const endIndex = startIndex + this.productsPerPage;
                const productsToShow = this.filteredProducts.slice(startIndex, endIndex);

                this.grid.innerHTML = '';
                productsToShow.forEach(product => this.grid.appendChild(this.createProductCard(product)));

                const actualEnd = Math.min(endIndex, this.filteredProducts.length);
                const actualStart = this.filteredProducts.length > 0 ? startIndex + 1 : 0;
                this.displayCount.textContent = `${actualStart}-${actualEnd}`;
                this.totalCount.textContent = this.filteredProducts.length;

                if (this.filteredProducts.length === 0) {
                    this.noResults.classList.remove('hidden');
                    this.grid.classList.add('hidden');
                    this.paginationContainer.classList.add('hidden');
                } else {
                    this.noResults.classList.add('hidden');
                    this.grid.classList.remove('hidden');
                    this.paginationContainer.classList.remove('hidden');
                }

                totalPages > 1 ? this.renderPagination(totalPages) : this.paginationContainer.innerHTML = '';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            renderPagination(totalPages) {
                let html = `
                    <button class="pagination-btn" ${this.currentPage === 1 ? 'disabled' : ''}
                            onclick="productFilter.goToPage(${this.currentPage - 1})">
                        <i class="fas fa-chevron-left"></i>
                    </button>`;

                this.getPageNumbers(totalPages).forEach(page => {
                    html += page === '...'
                        ? `<span class="pagination-ellipsis">...</span>`
                        : `<button class="pagination-btn ${page === this.currentPage ? 'active' : ''}"
                                onclick="productFilter.goToPage(${page})">${page}</button>`;
                });

                html += `
                    <button class="pagination-btn" ${this.currentPage === totalPages ? 'disabled' : ''}
                            onclick="productFilter.goToPage(${this.currentPage + 1})">
                        <i class="fas fa-chevron-right"></i>
                    </button>`;

                this.paginationContainer.innerHTML = html;
            }

            getPageNumbers(totalPages) {
                const pages = [], cur = this.currentPage;
                if (totalPages <= 7) {
                    for (let i = 1; i <= totalPages; i++) pages.push(i);
                } else if (cur <= 3) {
                    for (let i = 1; i <= 4; i++) pages.push(i);
                    pages.push('...', totalPages);
                } else if (cur >= totalPages - 2) {
                    pages.push(1, '...');
                    for (let i = totalPages - 3; i <= totalPages; i++) pages.push(i);
                } else {
                    pages.push(1, '...');
                    for (let i = cur - 1; i <= cur + 1; i++) pages.push(i);
                    pages.push('...', totalPages);
                }
                return pages;
            }

            goToPage(page) {
                const totalPages = Math.ceil(this.filteredProducts.length / this.productsPerPage);
                if (page < 1 || page > totalPages) return;
                this.currentPage = page;
                this.renderPage();
            }

            createProductCard(product) {
                const card = document.createElement('article');
                card.className = 'product-card overflow-hidden rounded-lg shadow-sm';

                const initial = product.initial_variant || {};
                const variants = product.variants || [];
                const hasMultipleVariants = variants.length > 1;

                // Image container
                const imageContainer = document.createElement('div');
                imageContainer.className = 'relative aspect-square overflow-visible bg-gray-100 rounded-t-lg';

                const img = document.createElement('img');
                img.src = product.color_image;
                img.alt = product.name;
                img.className = 'w-full h-full object-contain product-image';
                img.loading = 'lazy';
                imageContainer.appendChild(img);

                if (product.color_image2) {
                    const img2 = document.createElement('img');
                    img2.src = product.color_image2;
                    img2.alt = `${product.name} - View 2`;
                    img2.className = 'absolute inset-0 w-full h-full object-contain product-image-2 transition-opacity duration-300 opacity-0';
                    img2.loading = 'lazy';
                    imageContainer.appendChild(img2);
                }

                // Discount badge
                const initialDiscount = parseFloat(initial.discount || 0);
                if (initialDiscount > 0) {
                    const discountBadge = document.createElement('span');
                    discountBadge.className = 'absolute bottom-3 left-3 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded z-20';
                    discountBadge.style.zIndex = '20';
                    discountBadge.textContent = `Save ${Math.round(initialDiscount)}%`;
                    imageContainer.appendChild(discountBadge);
                }

                // Wishlist button
                const wishlistBtn = document.createElement('button');
                wishlistBtn.type = 'button';
                wishlistBtn.className = 'absolute top-3 right-3 bg-white rounded-full p-2 hover:bg-gray-100 transition w-10 h-10 flex items-center justify-center';
                wishlistBtn.innerHTML = '<i class="far fa-heart text-gray-600"></i>';
                wishlistBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    wishlistBtn.querySelector('i').classList.toggle('far');
                    wishlistBtn.querySelector('i').classList.toggle('fas');
                    wishlistBtn.querySelector('i').classList.toggle('text-red-600');
                });
                imageContainer.appendChild(wishlistBtn);

                card.appendChild(imageContainer);

                // Info section
                const infoSection = document.createElement('div');
                infoSection.className = 'p-2 sm:p-3 flex flex-col flex-grow';

                const productName = document.createElement('h3');
                productName.className = 'text-sm sm:text-base font-semibold line-clamp-2 text-black';
                productName.textContent = product.name;
                infoSection.appendChild(productName);

                const colorDiv = document.createElement('div');
                colorDiv.className = 'text-xs text-gray-600 mt-1 hidden sm:block';
                colorDiv.innerHTML = `<span class="font-medium color-label">${product.color_name}</span>`;
                infoSection.appendChild(colorDiv);

                // Color swatches
                const siblings = productGroups[product.id] || [product];
                if (siblings.length > 1) {
                    const colorRow = document.createElement('div');
                    colorRow.className = 'flex gap-1 flex-wrap items-center px-2 pt-1';
                    const VISIBLE_COLORS = 3;
                    let hiddenColorCount = 0;

                    siblings.forEach((colorVariant, idx) => {
                        if (idx >= VISIBLE_COLORS) { hiddenColorCount++; return; }
                        const swatch = document.createElement('button');
                        swatch.type = 'button';
                        swatch.title = colorVariant.color_name;
                        swatch.className = `w-5 h-5 rounded-full border-2 transition-all ${idx === 0 ? 'border-black scale-110' : 'border-gray-300 hover:border-gray-500'}`;
                        swatch.style.backgroundColor = colorVariant.color_code || '#ccc';

                        swatch.addEventListener('click', (e) => {
                            e.preventDefault();
                            img.src = colorVariant.color_image;
                            if (product.color_image2 && colorVariant.color_image2) {
                                const img2El = imageContainer.querySelector('.product-image-2');
                                if (img2El) img2El.src = colorVariant.color_image2;
                            }
                            const colorLabel = infoSection.querySelector('.color-label');
                            if (colorLabel) colorLabel.textContent = colorVariant.color_name;
                            const form = card.querySelector('.productForm');
                            if (form) {
                                form.querySelector('.color-id-input').value = colorVariant.color_id;
                                form.querySelector('.color-name-input').value = colorVariant.color_name;
                                form.querySelector('.color-price-input').value = colorVariant.color_price;
                            }
                            const activeSizeBtn = card.querySelector('.size-btn.bg-black');
                            if (activeSizeBtn) {
                                const newPrice = parseFloat(activeSizeBtn.dataset.variantPrice || 0) + parseFloat(colorVariant.color_price);
                                activeSizeBtn.dataset.price = newPrice;
                                const priceEl = card.querySelector('.final-price');
                                if (priceEl) priceEl.textContent = `₱${newPrice.toLocaleString()}`;
                                if (form) {
                                    form.querySelector('.total-price-input').value = newPrice;
                                    form.querySelector('.color-price-input').value = colorVariant.color_price;
                                }
                            }
                            colorRow.querySelectorAll('button').forEach(s => {
                                s.classList.remove('border-black', 'scale-110');
                                s.classList.add('border-gray-300');
                            });
                            swatch.classList.add('border-black', 'scale-110');
                            swatch.classList.remove('border-gray-300');
                        });

                        colorRow.appendChild(swatch);
                    });

                    if (hiddenColorCount > 0) {
                        const colorBadge = document.createElement('span');
                        colorBadge.className = 'border border-gray-200 rounded-full text-xs font-bold bg-gray-50 text-gray-700';
                        colorBadge.textContent = `+${hiddenColorCount}`;
                        colorBadge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;min-width:22px;min-height:22px;';
                        colorRow.appendChild(colorBadge);
                    }

                    infoSection.appendChild(colorRow);
                }

                // Price container
                const priceContainer = document.createElement('div');
                priceContainer.className = 'mt-2';

                if (initial.timer_active && initial.original_price && initial.original_price > initial.price) {
                    const originalPrice = document.createElement('p');
                    originalPrice.className = 'text-xs text-gray-400 line-through original-price-display';
                    originalPrice.textContent = `₱${parseFloat(initial.original_price).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
                    priceContainer.appendChild(originalPrice);
                }

                const finalPrice = document.createElement('p');
                finalPrice.className = 'text-lg font-bold text-black final-price';
                finalPrice.textContent = `₱${(initial.price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
                priceContainer.appendChild(finalPrice);
                // Flash Sale badge — label only, NO countdown timer — inside priceContainer so it stays below price
                if (initial.timer_active) {
                    const flashBadge = document.createElement('div');
                    flashBadge.className = 'mt-1 bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded inline-flex items-center gap-1 flash-sale-badge';
                    flashBadge.innerHTML = `<i class="fas fa-fire-alt text-yellow-300"></i><span>Flash Sale</span>`;
                    priceContainer.appendChild(flashBadge);
                }

                infoSection.appendChild(priceContainer);

                // Size buttons
                if (hasMultipleVariants) {
                    const sizesContainer = document.createElement('div');
                    sizesContainer.className = 'mt-2';
                    const sizeButtonsDiv = document.createElement('div');
                    sizeButtonsDiv.className = 'flex gap-1 flex-wrap items-center';

                    const VISIBLE_SIZES = 2;
                    let hiddenCount = 0;

                    variants.forEach((v, idx) => {
                        const sizeBtn = document.createElement('button');
                        sizeBtn.type = 'button';
                        sizeBtn.className = `size-btn px-2 py-1 border rounded text-xs whitespace-nowrap font-semibold transition-all ${idx === 0 ? 'bg-black text-white border-black' : 'bg-white text-gray-800 border-gray-300 hover:border-orange-500'}`;
                        sizeBtn.style.position = 'relative';
                        sizeBtn.textContent = v.size;

                        sizeBtn.dataset.variantId = v.variant_id;
                        sizeBtn.dataset.size = v.size;
                        sizeBtn.dataset.price = parseFloat(v.price) + parseFloat(product.color_price);
                        sizeBtn.dataset.discount = v.discount;
                        sizeBtn.dataset.origin = v.origin;
                        sizeBtn.dataset.variantPrice = v.price;
                        sizeBtn.dataset.originalPrice = v.original_price || 0;
                        sizeBtn.dataset.timerActive = v.timer_discount_active || 0;
                        sizeBtn.dataset.timerEnd = v.timer_discount_end
                            ? Math.floor(new Date(v.timer_discount_end).getTime() / 1000) : 0;
                        sizeBtn.dataset.timerPct = v.timer_discount_percent || 0;
                        sizeBtn.dataset.percent = v.percent;

                        // Flash sale red dot on size button
                        const nowTs = Math.floor(Date.now() / 1000);
                        const btnTimerEnd = parseInt(sizeBtn.dataset.timerEnd || 0);
                        const btnTimerActive = parseInt(sizeBtn.dataset.timerActive || 0) === 1 && btnTimerEnd > nowTs;

                        if (btnTimerActive) {
                            const flashDot = document.createElement('span');
                            flashDot.className = 'absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full border border-white z-10';
                            flashDot.title = `Flash Sale: Extra ${v.timer_discount_percent}% OFF`;
                            sizeBtn.appendChild(flashDot);
                        } else if (parseFloat(v.discount) > 0) {
                            const discountDot = document.createElement('span');
                            discountDot.className = 'absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full';
                            sizeBtn.appendChild(discountDot);
                        }

                        if (idx >= VISIBLE_SIZES) {
                            sizeBtn.style.display = 'none';
                            sizeBtn.classList.add('size-btn-hidden');
                            hiddenCount++;
                        }

                        sizeButtonsDiv.appendChild(sizeBtn);
                    });

                    if (hiddenCount > 0) {
                        const indicatorBadge = document.createElement('span');
                        indicatorBadge.className = 'border border-gray-200 rounded-full text-xs font-bold bg-gray-50 text-gray-700';
                        indicatorBadge.textContent = `+${hiddenCount}`;
                        indicatorBadge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;min-width:32px;min-height:32px;';
                        sizeButtonsDiv.appendChild(indicatorBadge);
                    }

                    sizesContainer.appendChild(sizeButtonsDiv);
                    infoSection.appendChild(sizesContainer);
                }

                // Buttons
                const buttonsDiv = document.createElement('div');
                buttonsDiv.className = 'space-y-1 mt-auto pt-2';

                const viewForm = document.createElement('form');
                viewForm.action = '<?= BASE_URL ?>/productview';
                viewForm.method = 'GET';
                const viewInput = document.createElement('input');
                viewInput.type = 'hidden';
                viewInput.name = 'id';
                viewInput.value = product.id;
                viewForm.appendChild(viewInput);
                const viewButton = document.createElement('button');
                viewButton.type = 'submit';
                viewButton.className = ' text-black text-xs sm:text-sm font-semibold transition rounded hover:underline text-start';
                viewButton.textContent = 'Quickview';
                viewForm.appendChild(viewButton);
                buttonsDiv.appendChild(viewForm);

                const cartForm = document.createElement('form');
                cartForm.className = 'productForm';
                cartForm.dataset.productId = product.id;

                [
                    { name: 'product_id', value: product.id },
                    { name: 'variant_id', value: initial.variant_id, className: 'variant-id-input' },
                    { name: 'selected_color_id', value: product.color_id, className: 'color-id-input' },
                    { name: 'selected_color_name', value: product.color_name, className: 'color-name-input' },
                    { name: 'color_price', value: product.color_price, className: 'color-price-input' },
                    { name: 'variant_price', value: initial.variant_price, className: 'variant-price-input' },
                    { name: 'total_price', value: initial.price, className: 'total-price-input' },
                    { name: 'discount', value: initial.discount, className: 'discount-input' },
                    { name: 'percent', value: initial.percent, className: 'percent-input' },
                    { name: 'origin', value: initial.origin, className: 'origin-input' },
                    { name: 'selected_size', value: initial.size, className: 'size-input' },
                    { name: 'return_url', value: 'index' }
                ].forEach(inputData => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = inputData.name;
                    input.value = inputData.value;
                    if (inputData.className) input.className = inputData.className;
                    cartForm.appendChild(input);
                });
                const minQty = product.min_order_qty || 1;

                const minLabel = document.createElement('p');
                minLabel.className = 'text-xs text-gray-400 mt-1';
                minLabel.textContent = `Min. order: ${minQty} pcs`;

                const cartRow = document.createElement('div');
                cartRow.className = 'flex items-center gap-2 mt-1';

                const qtyWrapper = document.createElement('div');
                qtyWrapper.className = 'flex items-center border border-gray-300 rounded overflow-hidden';

                const minusBtn = document.createElement('button');
                minusBtn.type = 'button';
                minusBtn.className = 'px-2 py-1 text-sm font-bold text-gray-600 hover:bg-gray-100 transition';
                minusBtn.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';

                const qtyInput = document.createElement('input');
                qtyInput.type = 'number';
                qtyInput.name = 'quantity';
                qtyInput.value = minQty;
                qtyInput.min = minQty;
                qtyInput.className = 'w-10 text-center text-xs font-semibold border-x border-gray-300 py-1 focus:outline-none';
                qtyInput.style.cssText = '-moz-appearance:textfield;appearance:textfield;';

                const plusBtn = document.createElement('button');
                plusBtn.type = 'button';
                plusBtn.className = 'px-2 py-1 text-sm font-bold text-gray-600 hover:bg-gray-100 transition';
                plusBtn.innerHTML = '<i class="fa-solid fa-chevron-up"></i>';

                minusBtn.addEventListener('click', () => {
                    const min = parseInt(qtyInput.getAttribute('min')) || 1;
                    const val = parseInt(qtyInput.value) || min;
                    if (val > min) qtyInput.value = val - 1;
                });

                plusBtn.addEventListener('click', () => {
                    const val = parseInt(qtyInput.value) || 1;
                    qtyInput.value = val + 1;
                });

                qtyWrapper.appendChild(minusBtn);
                qtyWrapper.appendChild(qtyInput);
                qtyWrapper.appendChild(plusBtn);

                const cartButton = document.createElement('button');
                cartButton.type = 'submit';
                cartButton.className = 'flex items-center gap-1 text-xs sm:text-sm font-semibold underline text-black hover:text-orange-500 transition whitespace-nowrap';
                cartButton.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';

                cartRow.appendChild(qtyWrapper);
                cartRow.appendChild(cartButton);

                if (minQty > 1) cartForm.appendChild(minLabel);
                cartForm.appendChild(cartRow);

                buttonsDiv.appendChild(cartForm);
                infoSection.appendChild(buttonsDiv);
                card.appendChild(infoSection);
                // Size button click handlers
                setTimeout(() => {
                    const allSizeButtons = card.querySelectorAll('.size-btn');

                    allSizeButtons.forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            e.preventDefault();

                            const variantData = {
                                variant_id: btn.dataset.variantId,
                                size: btn.dataset.size,
                                price: parseFloat(btn.dataset.price),
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
                            }

                            // Update price display
                            const priceEl = card.querySelector('.final-price');
                            if (priceEl) priceEl.textContent = `₱${variantData.price.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

                            // Update strikethrough original price
                            const origPriceEl = card.querySelector('.original-price-display');
                            const nowTs = Math.floor(Date.now() / 1000);
                            const timerEnd = parseInt(btn.dataset.timerEnd || 0);
                            const isTimerActive = parseInt(btn.dataset.timerActive || 0) === 1 && timerEnd > nowTs;
                            const origPrice = parseFloat(btn.dataset.originalPrice || 0);

                            if (origPriceEl) {
                                if (isTimerActive && origPrice > variantData.price) {
                                    origPriceEl.textContent = `₱${origPrice.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
                                    origPriceEl.classList.remove('hidden');
                                } else {
                                    origPriceEl.classList.add('hidden');
                                }
                            }

                            // Show/hide Flash Sale badge (label only, no countdown) on size switch
                            const existingFlashBadge = card.querySelector('.flash-sale-badge');
                            if (isTimerActive) {
                                if (!existingFlashBadge) {
                                    const newBadge = document.createElement('div');
                                    newBadge.className = 'mt-1 bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded inline-flex items-center gap-1 flash-sale-badge';
                                    newBadge.innerHTML = `<i class="fas fa-fire-alt text-yellow-300"></i><span>Flash Sale</span>`;
                                    // Insert inside priceContainer so it stays below the price
                                    card.querySelector('.final-price')?.parentElement?.appendChild(newBadge);
                                }
                            } else {
                                existingFlashBadge?.remove();
                            }

                            // Update discount badge on image
                            let discountBadge = imageContainer.querySelector('span.bg-red-600');
                            if (variantData.discount > 0) {
                                if (discountBadge) {
                                    discountBadge.textContent = `Save ${Math.round(variantData.discount)}%`;
                                } else {
                                    const newBadge = document.createElement('span');
                                    newBadge.className = 'absolute bottom-3 left-3 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded z-20';
                                    newBadge.style.zIndex = '20';
                                    newBadge.textContent = `Save ${Math.round(variantData.discount)}%`;
                                    imageContainer.appendChild(newBadge);
                                }
                            } else {
                                if (discountBadge) discountBadge.remove();
                            }

                            // Update size button active states
                            allSizeButtons.forEach(b => {
                                b.classList.remove('bg-black', 'text-white', 'border-black');
                                b.classList.add('bg-white', 'text-gray-800', 'border-gray-300');
                            });
                            btn.classList.remove('bg-white', 'text-gray-800', 'border-gray-300');
                            btn.classList.add('bg-black', 'text-white', 'border-black');
                        });
                    });

                    if (allSizeButtons.length > 0) allSizeButtons[0].click();
                }, 0);

                return card;
            }
        }

        class CartManager {
            constructor() { this.init(); }

            init() {
                document.addEventListener('submit', (e) => {
                    if (e.target.classList.contains('productForm')) this.handleAddToCart(e);
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
                    const response = await fetch('<?= BASE_URL ?>/addcart', {
                        method: 'POST',
                        body: new FormData(form)
                    });
                    const data = await response.json();
                    if (data.success) {
                        if (typeof refreshCart === 'function') {
                            refreshCart();
                        }
                        this.showNotification(data.message || 'Added to cart!', 'success');
                        this.updateCartCount(data.cart_count);
                        button.innerHTML = 'Added!';
                        setTimeout(() => { button.innerHTML = originalContent; button.disabled = false; }, 2000);
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

            updateCartCount(count) {
                if (typeof window.updateCartBadge === 'function') window.updateCartBadge(count);
                setTimeout(() => {
                    if (typeof window.updateCartGlobally === 'function') window.updateCartGlobally();
                }, 800);
            }

            showNotification(message, type) {
                const container = document.getElementById('notificationContainer');
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                const icon = type === 'success'
                    ? '<i class="fa-regular fa-circle-check mr-2"></i>'
                    : '<i class="fa-regular fa-circle-xmark mr-2"></i>';
                notification.innerHTML = `${icon} ${message}`;

                container.appendChild(notification);
                setTimeout(() => notification.classList.add('show'), 10);
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
        }

        function openMobileFilters() { window.mobileFilterManager?.open(); }
        function closeMobileFilters() { window.mobileFilterManager?.close(); }

      document.addEventListener('DOMContentLoaded', () => {
    window.mobileFilterManager = new MobileFilterManager();
    new DropdownManager();
    window.productFilter = new ProductFilter();
    new CartManager();

    const urlParams = new URLSearchParams(window.location.search);
    const filterParam = urlParams.get('filter');
    const minDiscount = parseFloat(urlParams.get('min_discount') || 0);

    if (filterParam === 'discounted' && minDiscount > 0) {
        window.productFilter.filters.minDiscount = minDiscount;

        const discountBtn = document.querySelector('.discount-filter[data-discount="discounted"]');
        if (discountBtn) discountBtn.click();

        // Inject the clear filter banner
        const banner = document.createElement('div');
        banner.id = 'promo-filter-banner';
        banner.className = 'flex items-center justify-between bg-orange-50 border border-orange-300 rounded-lg px-4 py-2 mb-4 text-sm';
        banner.innerHTML = `
            <span class="text-orange-700 font-semibold">
                <i class="fas fa-tag mr-1"></i>
                Showing products with up to ${minDiscount}% discount
            </span>
            <button onclick="clearPromoFilter()"
                class="ml-4 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 px-3 py-1 rounded-full transition">
                ✕ Clear Filter
            </button>
        `;

        // Insert before the products grid
        const main = document.querySelector('main');
        const grid = document.getElementById('productsGrid');
        if (main && grid) main.insertBefore(banner, grid);
    }
});

function clearPromoFilter() {
    // Remove URL params and reset filter
    const url = new URL(window.location.href);
    url.searchParams.delete('filter');
    url.searchParams.delete('min_discount');
    window.history.replaceState({}, '', url);

    // Remove banner
    document.getElementById('promo-filter-banner')?.remove();

    // Reset discount filter back to "All"
    window.productFilter.filters.minDiscount = 0;
    const allBtn = document.querySelector('.discount-filter[data-discount="all"]');
    if (allBtn) allBtn.click();
}
    </script>

</body>

</html>