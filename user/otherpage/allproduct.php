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

// Build query with proper joins
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

// Build WHERE clause dynamically
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

// Execute query
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

// Helper Functions
function safe_output($value, $default = '')
{
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

function calculate_price($base_price, $percent = 0, $discount = 0)
{
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

function process_product_images($main_image, $sub_images, $type_image)
{
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
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Product Display - Noble Store</title>
    <meta name="description" content="Discover high-quality products curated for your lifestyle">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- AOS Animation -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        mont: ['Montserrat', 'sans-serif'],
                    },
                    animation: {
                        'bubble-bounce': 'bubble-bounce 2.2s cubic-bezier(.68, -0.55, .27, 1.55) infinite',
                        'float': 'float 3s ease-in-out infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'fadeInUp': 'fadeInUp 0.6s ease-out forwards'
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --primary-color: #f97316;
            --secondary-color: #ea580c;
            --accent-color: #dc2626;
        }

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

        .product-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }

        .discount-badge {
            background: linear-gradient(135deg, var(--accent-color) 0%, #dc2626 100%);
            animation: pulse 2s infinite;
        }

        .btn-buy {
            background: linear-gradient(135deg, #000000 0%, #1f2937 100%);
            transition: all 0.3s ease;
        }

        .btn-buy:hover {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-preorder {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            transition: all 0.3s ease;
        }

        .btn-preorder:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #c2410c 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(249, 115, 22, 0.4);
        }

        .filter-button {
            transition: all 0.3s ease;
        }

        .filter-button.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            transform: scale(1.05);
        }

        .filter-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        /* Mobile Filter Overlay */
        .filter-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
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
            transition: left 0.3s ease;
            overflow-y: auto;
        }

        .mobile-filter-sidebar.active {
            left: 0;
        }

        /* Dropdown styles */
        .dropdown-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .dropdown-content.active {
            max-height: 300px;
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotate {
            transform: rotate(180deg);
        }

        /* Image Gallery */
        .image-gallery {
            position: relative;
            overflow: hidden;
        }

        .gallery-container {
            display: flex;
            transition: transform 0.3s ease;
        }

        .gallery-image {
            flex-shrink: 0;
            width: 100%;
            height: 100%;
        }

        .gallery-nav {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 4px;
            z-index: 10;
        }

        .gallery-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .gallery-dot.active {
            background: var(--primary-color);
            transform: scale(1.2);
        }

        .gallery-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 10;
            font-size: 12px;
        }

        .image-gallery:hover .gallery-arrow {
            opacity: 1;
        }

        .gallery-arrow.prev {
            left: 4px;
        }

        .gallery-arrow.next {
            right: 4px;
        }

        .gallery-arrow:hover {
            background: rgba(0, 0, 0, 0.7);
        }

        /* Mobile responsiveness for arrows */
        @media (max-width: 640px) {
            .gallery-arrow {
                width: 24px;
                height: 24px;
                font-size: 14px;
            }
            
            .gallery-arrow.prev {
                left: 6px;
            }
            
            .gallery-arrow.next {
                right: 6px;
            }
        }

        /* Loading spinner */
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Notification system */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 16px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            max-width: 90vw;
        }

        .notification.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .notification.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .notification.show {
            transform: translateX(0);
        }

        /* Mobile notification adjustments */
        @media (max-width: 640px) {
            .notification {
                top: 10px;
                right: 10px;
                left: 10px;
                transform: translateY(-100%);
                max-width: none;
            }
            
            .notification.show {
                transform: translateY(0);
            }
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Animated View Details Button */
        .animated-view-btn {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            width: 48px;
            height: 40px;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition-duration: .3s;
            background: linear-gradient(135deg, #000000 0%, #000000 100%);
        }

        .animated-view-btn .btn-sign {
            width: 100%;
            font-size: 1.1em;
            color: white;
            transition-duration: .3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .animated-view-btn .btn-text {
            position: absolute;
            right: 0%;
            width: 0%;
            opacity: 0;
            color: white;
            font-size: 0.85em;
            font-weight: 600;
            transition-duration: .3s;
            white-space: nowrap;
        }

        /* Desktop hover effects */
        @media (min-width: 1024px) {
            .animated-view-btn:hover {
                width: 140px;
                transition-duration: .3s;
            }

            .animated-view-btn:hover .btn-sign {
                width: 35%;
                transition-duration: .3s;
                padding-left: 10px;
            }

            .animated-view-btn:hover .btn-text {
                opacity: 1;
                width: 65%;
                transition-duration: .3s;
                padding-right: 10px;
            }
        }

        .animated-view-btn:active {
            transform: translate(1px, 1px);
        }

        /* Range sliders */
        .range-slider {
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            border-radius: 3px;
            background: linear-gradient(to right, var(--primary-color) 0%, var(--secondary-color) 100%);
            outline: none;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        /* Responsive product card adjustments */
        @media (max-width: 640px) {
            .product-card {
                min-height: 400px;
                max-height: none;
            }
            
            .product-card:hover {
                transform: translateY(-4px);
            }
        }

        @media (min-width: 641px) and (max-width: 1024px) {
            .product-card {
                min-height: 480px;
            }
        }

        @media (min-width: 1025px) {
            .product-card {
                min-height: 560px;
                max-height: 560px;
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Ensure proper touch targets on mobile */
        @media (max-width: 640px) {
            .filter-button {
                min-height: 44px;
                padding: 12px 16px;
            }
            
            .dropdown-toggle {
                min-height: 44px;
                padding: 12px 0;
            }
        }
    </style>
</head>

<body class="bg-gray-50 font-mont">
    <?php include '../navbar/top.php'; ?>

    <!-- Mobile Filter Overlay -->
    <div class="filter-overlay lg:hidden" id="filterOverlay" onclick="closeMobileFilters()"></div>
    
    <!-- Mobile Filter Sidebar -->
    <aside class="mobile-filter-sidebar lg:hidden" id="mobileFilterSidebar">
        <div class="p-4 sm:p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-black playfair">
                    <i class="fas fa-sliders-h mr-2 text-gray-600"></i>Filters
                </h2>
                <button onclick="closeMobileFilters()" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                    <i class="fas fa-times text-gray-600 text-lg"></i>
                </button>
            </div>

            <!-- Mobile Filter Content -->
            <div class="space-y-6">
                <!-- Search Filter -->
                <div class="border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-search-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Search Products</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="mobile-search-dropdown" class="dropdown-content mt-3">
                        <div class="relative">
                            <input type="text" id="mobileSearchFilter" placeholder="Search by name..." class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm">
                            <svg class="absolute right-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Origin Filter -->
                <div class="border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-origin-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Origin</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="mobile-origin-dropdown" class="dropdown-content mt-3">
                        <div class="flex flex-col gap-2">
                            <button class="filter-button mobile-origin-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium active text-left" data-origin="all">All</button>
                            <button class="filter-button mobile-origin-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-origin="local">Local</button>
                            <button class="filter-button mobile-origin-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-origin="international">International</button>
                        </div>
                    </div>
                </div>

                <!-- Price Range Filter -->
                <div class="border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-price-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Price Range</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="mobile-price-dropdown" class="dropdown-content mt-3">
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs text-gray-600">Min: ₱<span id="mobileMinPriceValue">0</span></label>
                                <input type="range" id="mobileMinPrice" class="w-full range-slider" min="0" max="10000" value="0" step="100">
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Max: ₱<span id="mobileMaxPriceValue">10,000</span></label>
                                <input type="range" id="mobileMaxPrice" class="w-full range-slider" min="0" max="10000" value="10000" step="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discount Filter -->
                <div class="border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-discount-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Discount</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="mobile-discount-dropdown" class="dropdown-content mt-3">
                        <div class="flex flex-col gap-2">
                            <button class="filter-button mobile-discount-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium active text-left" data-discount="all">All</button>
                            <button class="filter-button mobile-discount-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-discount="discounted">On Sale</button>
                            <button class="filter-button mobile-discount-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-discount="no-discount">Regular Price</button>
                        </div>
                    </div>
                </div>

                <!-- Sort Filter -->
                <div>
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="mobile-sort-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Sort By</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="mobile-sort-dropdown" class="dropdown-content mt-3">
                        <select id="mobileSortFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm">
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

    <!-- Main Layout Container -->
    <div class="min-h-screen bg-gray-50">
        <!-- Header Section -->
        <header class="text-center py-8 sm:py-12 lg:py-16">
            <div class="px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-orange-400 mb-4 lg:mb-6">
                    Premium <span class="text-black">Collections</span>
                </h1>
                <p class="mt-4 lg:mt-6 text-base sm:text-lg text-gray-700 max-w-3xl mx-auto leading-relaxed">
                    Discover a wide selection of high-quality products curated to match your lifestyle.
                </p>
            </div>
        </header>

        <!-- Mobile Filter Toggle Button -->
        <div class="lg:hidden sticky top-0 z-30 bg-white shadow-sm border-t border-gray-200">
            <div class="p-4">
                <button onclick="openMobileFilters()" class="flex items-center justify-center gap-2 w-full sm:w-auto px-6 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors font-medium">
                    <i class="fas fa-filter"></i>
                    <span>Filters & Sort</span>
                </button>
            </div>
        </div>

        <!-- Main Content Layout -->
        <div class="lg:flex">
            <!-- Desktop Sidebar Filters -->
            <aside class="hidden lg:block lg:w-80 xl:w-96 p-6 sticky top-0 h-screen overflow-y-auto">
                <h2 class="text-xl font-bold text-black mb-6">
                    <i class="fas fa-sliders-h mr-2 text-gray-600"></i>Filters
                </h2>

                <!-- Search Filter -->
                <div class="mb-6 border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="search-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Search Products</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="search-dropdown" class="dropdown-content mt-3">
                        <div class="relative">
                            <input type="text" id="searchFilter" placeholder="Search by name..." class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm">
                            <svg class="absolute right-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Origin Filter -->
                <div class="mb-6 border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="origin-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Origin</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="origin-dropdown" class="dropdown-content mt-3">
                        <div class="flex flex-col gap-2">
                            <button class="filter-button origin-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium active text-left" data-origin="all">All</button>
                            <button class="filter-button origin-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-origin="local">Local</button>
                            <button class="filter-button origin-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-origin="international">International</button>
                        </div>
                    </div>
                </div>

                <!-- Price Range Filter -->
                <div class="mb-6 border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="price-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Price Range</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="price-dropdown" class="dropdown-content mt-3">
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs text-gray-600">Min: ₱<span id="minPriceValue">0</span></label>
                                <input type="range" id="minPrice" class="w-full range-slider" min="0" max="10000" value="0" step="100">
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Max: ₱<span id="maxPriceValue">10,000</span></label>
                                <input type="range" id="maxPrice" class="w-full range-slider" min="0" max="10000" value="10000" step="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discount Filter -->
                <div class="mb-6 border-b border-gray-100 pb-4">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="discount-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Discount</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="discount-dropdown" class="dropdown-content mt-3">
                        <div class="flex flex-col gap-2">
                            <button class="filter-button discount-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium active text-left" data-discount="all">All</button>
                            <button class="filter-button discount-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-discount="discounted">On Sale</button>
                            <button class="filter-button discount-filter px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-left" data-discount="no-discount">Regular Price</button>
                        </div>
                    </div>
                </div>

                <!-- Sort Filter -->
                <div class="mb-6">
                    <div class="flex items-center justify-between cursor-pointer dropdown-toggle" data-target="sort-dropdown">
                        <label class="text-sm font-semibold text-gray-700">Sort By</label>
                        <svg class="chevron w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="sort-dropdown" class="dropdown-content mt-3">
                        <select id="sortFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm">
                            <option value="default">Default</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="discount-high">Discount: High to Low</option>
                            <option value="name">Name: A to Z</option>
                        </select>
                    </div>
                </div>
            </aside>

            <!-- Products Section -->
            <main class="flex-1 p-4 sm:p-6 lg:p-6 xl:p-8">
                <section class="max-w-full">
                    <div id="productsGrid" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 sm:gap-6">

                        <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($material_results)):
                                $pricing = calculate_price($row['price'], $row['percent'] ?? 0, $row['discount'] ?? 0);
                                $all_images = process_product_images($row['main_image'], $row['sub_images'], $row['type_image']);
                                $discount = (float)($row['discount'] ?? 0);
                            ?>
                                <article class="product-item product-card hover:shadow-2xl p-3 sm:p-4 lg:p-5 group flex flex-col text-center relative overflow-hidden transition-all duration-500 hover:-translate-y-2 hover:scale-[1.02]"
                                    data-name="<?= safe_output(strtolower($row['namevariant'])) ?>"
                                    data-price="<?= $pricing['final'] ?>"
                                    data-original-price="<?= $pricing['original'] ?>"
                                    data-discount="<?= $discount ?>"
                                    data-origin="<?= safe_output(strtolower($row['origin'] ?? 'local')) ?>">

                                    <!-- Discount Badge -->
                                    <?php if ($discount > 0): ?>
                                        <div class="absolute top-2 sm:top-3 lg:top-4 left-2 sm:left-3 lg:left-4 z-20">
                                            <div class="discount-badge bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold px-2 sm:px-3 py-1 sm:py-1.5 rounded-full shadow-lg">
                                                -<?= number_format($discount, 0) ?>%
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Triangle Badge/Icon -->
                                    <div class="absolute top-0 right-0 z-10">
                                        <div class="w-8 h-8 sm:w-10 h-10 lg:w-12 lg:h-12 relative">
                                            <img src="../img/icon/d.png" alt="Product badge" class="absolute top-1 right-1 sm:top-2 sm:right-2 w-6 h-6 sm:w-7 sm:h-7 lg:w-8 lg:h-8 object-contain opacity-80 group-hover:opacity-100 transition-opacity" loading="lazy" />
                                        </div>
                                    </div>

                                    <!-- Product Image Gallery -->
                                    <div class="aspect-square w-full overflow-hidden mb-3 sm:mb-4 group-hover:shadow-inner transition-all duration-300">
                                        <div class="image-gallery w-full h-full relative">
                                            <div class="gallery-container h-full" data-current="0">
                                                <?php foreach ($all_images as $index => $image): ?>
                                                    <img src="<?= safe_output($image) ?>"
                                                        alt="<?= safe_output($row['namevariant']) ?> - Image <?= $index + 1 ?>"
                                                        class="gallery-image w-full h-full object-contain transition-transform duration-500 group-hover:scale-110"
                                                        style="<?= $index === 0 ? '' : 'display: none;' ?>"
                                                        loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                                                        onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgNzBMMTMwIDEwMEgxMTBWMTMwSDkwVjEwMEg3MEwxMDAgNzBaIiBmaWxsPSIjOUI5QjlCIi8+Cjx0ZXh0IHg9IjEwMCIgeT0iMTYwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjOUI5QjlCIiBmb250LXNpemU9IjEyIiBmb250LWZhbWlseT0iQXJpYWwiPk5vIEltYWdlPC90ZXh0Pgo8L3N2Zz4K'" />
                                                <?php endforeach; ?>
                                            </div>

                                            <?php if (count($all_images) > 1): ?>
                                                <!-- Navigation Arrows -->
                                                <button class="gallery-arrow prev" onclick="changeImage(this, -1)" type="button" aria-label="Previous image">‹</button>
                                                <button class="gallery-arrow next" onclick="changeImage(this, 1)" type="button" aria-label="Next image">›</button>

                                                <!-- Navigation Dots -->
                                                <div class="gallery-nav" role="tablist" aria-label="Image navigation">
                                                    <?php foreach ($all_images as $index => $image): ?>
                                                        <button class="gallery-dot <?= $index === 0 ? 'active' : '' ?>"
                                                            onclick="goToImage(this, <?= $index ?>)"
                                                            type="button"
                                                            role="tab"
                                                            aria-label="Image <?= $index + 1 ?>"
                                                            tabindex="<?= $index === 0 ? '0' : '-1' ?>"></button>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Product Info -->
                                    <div class="flex-1 flex flex-col justify-between">
                                        <div>
                                            <h2 class="uppercase text-base font-bold text-gray-800 leading-tight mb-2 sm:mb-3 group-hover:text-orange-600 transition-colors duration-300 line-clamp-2">
                                                <?= safe_output($row['namevariant']) ?>
                                            </h2>

                                            <!-- Color & Size Modal Trigger -->
                                            <div x-data="{ open: false }" class="text-center mb-2 sm:mb-3">
                                                <button @click="open = true" class="text-xs sm:text-sm text-gray-600 underline hover:text-gray-800 transition">
                                                    View Color & Size
                                                </button>

                                                <!-- Modal -->
                                                <div x-show="open" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" x-transition @click.away="open = false" style="display: none;">
                                                    <div class="bg-white max-w-sm w-full p-4 sm:p-6 relative animate-fade-in rounded-lg">
                                                        <button @click="open = false" class="absolute top-2 right-2 sm:top-3 sm:right-3 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                                                        <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-4">Product Details</h3>
                                                        <dl class="text-sm text-gray-600 space-y-3">
                                                            <div class="flex justify-between">
                                                                <dt class="font-semibold text-gray-700">Color:</dt>
                                                                <dd><?= safe_output($row['color'] ?? 'N/A') ?></dd>
                                                            </div>
                                                            <div class="flex justify-between">
                                                                <dt class="font-semibold text-gray-700">Size:</dt>
                                                                <dd><?= safe_output($row['size'] ?? 'N/A') ?></dd>
                                                            </div>
                                                        </dl>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Pricing -->
                                            <div class="mb-3 sm:mb-4">
                                                <?php if ($discount > 0): ?>
                                                    <p class="text-xs sm:text-sm text-gray-400 line-through mb-1">₱<?= number_format($pricing['original'], 2) ?></p>
                                                    <p class="text-lg sm:text-xl font-bold bg-clip-text text-green-600 mb-2">
                                                        ₱<?= number_format($pricing['final'], 2) ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-lg sm:text-xl font-bold bg-clip-text text-green-600 mb-2">
                                                        ₱<?= number_format($pricing['original'], 2) ?>
                                                    </p>
                                                <?php endif; ?>

                                                <!-- Origin Badge -->
                                                <?php if (!empty($row['origin'])): ?>
                                                    <span class="inline-block px-2 sm:px-3 py-1 text-xs font-medium rounded-full <?= $row['origin'] === 'international' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                                        <?= safe_output(ucfirst($row['origin'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex flex-col gap-2 mt-auto">
                                            <!-- View Details Button -->
                                            <form action="product_view" method="GET" class="w-full flex justify-start">
                                                <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                <button type="submit" class="animated-view-btn">
                                                    <div class="btn-sign">
                                                        <i class="fa-solid fa-bag-shopping"></i>
                                                    </div>
                                                    <div class="btn-text">View Details</div>
                                                </button>
                                            </form>

                                            <!-- Add to Cart Button -->
                                            <form class="productForm w-full" data-product-id="<?= (int)$row['product_id'] ?>">
                                                <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                                                <input type="hidden" name="selected_type" value="<?= safe_output($row['type_name'] ?? '') ?>">
                                                <input type="hidden" name="selected_variant" value="<?= safe_output($row['namevariant'] ?? '') ?> - <?= safe_output($row['size'] ?? '') ?>">
                                                <input type="hidden" name="variant_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                                <input type="hidden" name="selected_size" value="<?= safe_output($row['size'] ?? '') ?>">
                                                <input type="hidden" name="selected_color_id" value="<?= (int)($row['color_id'] ?? 0) ?>">
                                                <input type="hidden" name="selected_color_name" value="<?= safe_output($row['color'] ?? '') ?>">
                                                <input type="hidden" name="color_price" value="<?= floatval($row['color_price'] ?? 0) ?>">
                                                <input type="hidden" name="variant_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                <input type="hidden" name="total_price" value="<?= floatval($pricing['final']) ?>">
                                                <input type="hidden" name="discount" value="<?= floatval($row['discount'] ?? 0) ?>">
                                                <input type="hidden" name="percent" value="<?= floatval($row['percent'] ?? 0) ?>">
                                                <input type="hidden" name="origin" value="<?= safe_output($row['origin'] ?? 'local') ?>">
                                                <input type="hidden" name="return_url" value="index">

                                                <button type="submit" class="w-full bg-black hover:from-orange-600 hover:to-orange-800 text-white text-xs sm:text-sm px-4 sm:px-6 py-2 sm:py-3 flex items-center justify-center gap-2 font-semibold transition-all duration-300 transform hover:scale-105" aria-label="Add to cart">
                                                    <img src="../img/icon/cart.png" alt="" class="w-4 h-4 sm:w-6 sm:h-6" aria-hidden="true" />
                                                    Add to Cart
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-span-full text-center py-12 sm:py-20">
                                <div class="max-w-md mx-auto px-4">
                                    <div class="w-16 h-16 sm:w-24 sm:h-24 mx-auto mb-4 sm:mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                                        <svg class="w-8 h-8 sm:w-12 sm:h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <h3 class="text-xl sm:text-2xl font-bold text-gray-800 mb-2 sm:mb-3">No products available</h3>
                                    <p class="text-gray-500 text-base sm:text-lg">Check back later for new products</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- No Results Message -->
                    <div id="noResults" class="hidden text-center py-12 sm:py-20">
                        <div class="max-w-md mx-auto px-4">
                            <div class="w-16 h-16 sm:w-24 sm:h-24 mx-auto mb-4 sm:mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 sm:w-12 sm:h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xl sm:text-2xl font-bold text-gray-800 mb-2 sm:mb-3">No products found</h3>
                            <p class="text-gray-500 text-base sm:text-lg">Try adjusting your filters or search terms</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <!-- Notification Container -->
    <div id="notificationContainer" aria-live="assertive" aria-atomic="true"></div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>

    <script>
   // Enhanced Product Display JavaScript System
'use strict';

// Configuration and Constants
const CONFIG = {
    DEBOUNCE_DELAY: 300,
    ANIMATION_DELAY: 50,
    NOTIFICATION_DURATION: 3000,
    PRICE_RANGE: { MIN: 0, MAX: 10000, STEP: 100 }
};

// Utility Functions
const Utils = {
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    formatNumber(num) {
        return new Intl.NumberFormat('en-US').format(num);
    },

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount).replace('PHP', '₱');
    },

    sanitizeInput(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    createElement(tag, className = '', textContent = '') {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (textContent) element.textContent = textContent;
        return element;
    }
};

// Notification System
class NotificationSystem {
    constructor() {
        this.container = this.getOrCreateContainer();
        this.activeNotifications = new Set();
    }

    getOrCreateContainer() {
        let container = document.getElementById('notificationContainer');
        if (!container) {
            container = Utils.createElement('div');
            container.id = 'notificationContainer';
            container.setAttribute('aria-live', 'assertive');
            container.setAttribute('aria-atomic', 'true');
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
        return container;
    }

    show(message, type = 'success', duration = CONFIG.NOTIFICATION_DURATION) {
        const notification = this.createNotification(message, type);
        this.container.appendChild(notification);
        this.activeNotifications.add(notification);

        // Trigger animation
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });

        // Auto-remove
        setTimeout(() => {
            this.remove(notification);
        }, duration);

        return notification;
    }

    createNotification(message, type) {
        const notification = Utils.createElement('div', `notification ${type}`);
        notification.textContent = message;
        notification.setAttribute('role', 'alert');
        notification.style.pointerEvents = 'auto';
        
        // Add close button
        const closeBtn = Utils.createElement('button', 'notification-close');
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Close notification');
        closeBtn.onclick = () => this.remove(notification);
        notification.appendChild(closeBtn);

        return notification;
    }

    remove(notification) {
        if (notification && this.activeNotifications.has(notification)) {
            this.activeNotifications.delete(notification);
            notification.classList.remove('show');
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }

    clear() {
        this.activeNotifications.forEach(notification => this.remove(notification));
    }
}

// Mobile Filter Management
class MobileFilterManager {
    constructor() {
        this.overlay = document.getElementById('filterOverlay');
        this.sidebar = document.getElementById('mobileFilterSidebar');
        this.isOpen = false;
        
        this.init();
    }

    init() {
        // Prevent body scroll when filters are open
        this.bindEvents();
    }

    bindEvents() {
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.close());
        }

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    }

    open() {
        if (this.overlay && this.sidebar) {
            this.overlay.classList.add('active');
            this.sidebar.classList.add('active');
            document.body.style.overflow = 'hidden';
            this.isOpen = true;

            // Focus first focusable element
            const firstInput = this.sidebar.querySelector('input, button, select');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }

    close() {
        if (this.overlay && this.sidebar) {
            this.overlay.classList.remove('active');
            this.sidebar.classList.remove('active');
            document.body.style.overflow = '';
            this.isOpen = false;
        }
    }
}

// Image Gallery Management
class ImageGallery {
    constructor() {
        this.galleries = new Map();
        this.init();
    }

    init() {
        document.querySelectorAll('.image-gallery').forEach(gallery => {
            this.initGallery(gallery);
        });
    }

    initGallery(gallery) {
        const container = gallery.querySelector('.gallery-container');
        const images = gallery.querySelectorAll('.gallery-image');
        
        if (!container || images.length <= 1) return;

        const galleryData = {
            container,
            images: Array.from(images),
            currentIndex: 0,
            isTransitioning: false
        };

        this.galleries.set(gallery, galleryData);

        // Add touch/swipe support
        this.addTouchSupport(gallery, galleryData);
        
        // Add keyboard support
        this.addKeyboardSupport(gallery, galleryData);
    }

    addTouchSupport(gallery, galleryData) {
        let startX = 0;
        let startY = 0;
        let startTime = 0;

        gallery.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            startTime = Date.now();
        }, { passive: true });

        gallery.addEventListener('touchend', (e) => {
            if (galleryData.isTransitioning) return;

            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const endTime = Date.now();

            const deltaX = endX - startX;
            const deltaY = endY - startY;
            const deltaTime = endTime - startTime;

            // Check if it's a swipe (not just a tap)
            if (Math.abs(deltaX) > 50 && Math.abs(deltaY) < 100 && deltaTime < 300) {
                if (deltaX > 0) {
                    this.changeImage(gallery, -1);
                } else {
                    this.changeImage(gallery, 1);
                }
            }
        }, { passive: true });
    }

    addKeyboardSupport(gallery, galleryData) {
        gallery.addEventListener('keydown', (e) => {
            if (galleryData.isTransitioning) return;

            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.changeImage(gallery, -1);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.changeImage(gallery, 1);
                    break;
                case 'Home':
                    e.preventDefault();
                    this.goToImage(gallery, 0);
                    break;
                case 'End':
                    e.preventDefault();
                    this.goToImage(gallery, galleryData.images.length - 1);
                    break;
            }
        });
    }

    changeImage(gallery, direction) {
        const galleryData = this.galleries.get(gallery);
        if (!galleryData || galleryData.isTransitioning) return;

        const newIndex = this.calculateNewIndex(galleryData.currentIndex, direction, galleryData.images.length);
        this.goToImage(gallery, newIndex);
    }

    calculateNewIndex(current, direction, length) {
        let newIndex = current + direction;
        if (newIndex < 0) newIndex = length - 1;
        if (newIndex >= length) newIndex = 0;
        return newIndex;
    }

    goToImage(gallery, index) {
        const galleryData = this.galleries.get(gallery);
        if (!galleryData || galleryData.isTransitioning || index === galleryData.currentIndex) return;

        galleryData.isTransitioning = true;

        // Update images
        galleryData.images.forEach((img, i) => {
            img.style.display = i === index ? 'block' : 'none';
        });

        // Update dots
        const dots = gallery.querySelectorAll('.gallery-dot');
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
            dot.setAttribute('tabindex', i === index ? '0' : '-1');
        });

        galleryData.currentIndex = index;
        galleryData.container.dataset.current = index.toString();

        // Reset transition flag
        setTimeout(() => {
            galleryData.isTransitioning = false;
        }, 100);
    }

    // Static methods for global access
    static changeImage(button, direction) {
        const gallery = button.closest('.image-gallery');
        if (gallery && window.imageGalleryInstance) {
            window.imageGalleryInstance.changeImage(gallery, direction);
        }
    }

    static goToImage(dot, index) {
        const gallery = dot.closest('.image-gallery');
        if (gallery && window.imageGalleryInstance) {
            window.imageGalleryInstance.goToImage(gallery, index);
        }
    }
}

// Dropdown Management System
class DropdownManager {
    constructor() {
        this.activeDropdowns = new Set();
        this.init();
    }

    init() {
        this.initDropdowns();
        this.addGlobalListeners();
    }

    initDropdowns() {
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            this.setupDropdownToggle(toggle);
        });
    }

    setupDropdownToggle(toggle) {
        const targetId = toggle.getAttribute('data-target');
        const dropdown = document.getElementById(targetId);
        const chevron = toggle.querySelector('.chevron');
        
        if (!dropdown || !chevron) return;

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleDropdown(targetId, toggle, dropdown, chevron);
        });

        // Set initial ARIA attributes
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', targetId);
        if (!toggle.hasAttribute('tabindex')) {
            toggle.setAttribute('tabindex', '0');
        }
    }

    toggleDropdown(targetId, toggle, dropdown, chevron) {
        const isActive = dropdown.classList.contains('active');
        
        // Close all other dropdowns first
        this.closeAllDropdowns();
        
        if (!isActive) {
            this.openDropdown(targetId, toggle, dropdown, chevron);
        }
    }

    openDropdown(targetId, toggle, dropdown, chevron) {
        dropdown.classList.add('active');
        chevron.classList.add('rotate');
        toggle.setAttribute('aria-expanded', 'true');
        this.activeDropdowns.add(targetId);
        
        // Focus management
        const firstFocusable = dropdown.querySelector('input, button, select, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            setTimeout(() => firstFocusable.focus(), 100);
        }
    }

    closeAllDropdowns() {
        this.activeDropdowns.forEach(dropdownId => {
            const dropdown = document.getElementById(dropdownId);
            const toggle = document.querySelector(`[data-target="${dropdownId}"]`);
            const chevron = toggle?.querySelector('.chevron');
            
            if (dropdown) dropdown.classList.remove('active');
            if (chevron) chevron.classList.remove('rotate');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
        this.activeDropdowns.clear();
    }

    addGlobalListeners() {
        // Close dropdowns on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown-toggle, .dropdown-content')) {
                this.closeAllDropdowns();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllDropdowns();
                return;
            }

            if (e.key === 'Enter' || e.key === ' ') {
                if (e.target.classList.contains('dropdown-toggle')) {
                    e.preventDefault();
                    e.target.click();
                }
            }
        });
    }
}

// Product Filter System
class ProductFilter {
    constructor() {
        this.products = Array.from(document.querySelectorAll('.product-item'));
        this.noResults = document.getElementById('noResults');
        this.productsGrid = document.getElementById('productsGrid');
        
        this.filters = {
            search: '',
            origin: 'all',
            discount: 'all',
            minPrice: CONFIG.PRICE_RANGE.MIN,
            maxPrice: CONFIG.PRICE_RANGE.MAX,
            sort: 'default'
        };

        this.elements = this.getFilterElements();
        this.debouncedFilter = Utils.debounce(() => this.applyFilters(), CONFIG.DEBOUNCE_DELAY);
        
        this.init();
    }

    getFilterElements() {
        return {
            // Desktop elements
            searchInput: document.getElementById('searchFilter'),
            minPriceSlider: document.getElementById('minPrice'),
            maxPriceSlider: document.getElementById('maxPrice'),
            minPriceValue: document.getElementById('minPriceValue'),
            maxPriceValue: document.getElementById('maxPriceValue'),
            sortSelect: document.getElementById('sortFilter'),
            
            // Mobile elements
            mobileSearchInput: document.getElementById('mobileSearchFilter'),
            mobileMinPriceSlider: document.getElementById('mobileMinPrice'),
            mobileMaxPriceSlider: document.getElementById('mobileMaxPrice'),
            mobileMinPriceValue: document.getElementById('mobileMinPriceValue'),
            mobileMaxPriceValue: document.getElementById('mobileMaxPriceValue'),
            mobileSortSelect: document.getElementById('mobileSortFilter')
        };
    }

    init() {
        this.initSearchFilters();
        this.initPriceRangeFilters();
        this.initSortFilters();
        this.initFilterButtons();
        this.syncMobileFilters();
        this.applyFilters();
    }

    initSearchFilters() {
        [this.elements.searchInput, this.elements.mobileSearchInput].forEach(input => {
            if (input) {
                input.addEventListener('input', (e) => {
                    this.filters.search = e.target.value.toLowerCase().trim();
                    this.syncInputValues('search', e.target.value);
                    this.debouncedFilter();
                });
            }
        });
    }

    initPriceRangeFilters() {
        // Desktop price filters
        this.initPriceSlider(this.elements.minPriceSlider, this.elements.minPriceValue, 'minPrice');
        this.initPriceSlider(this.elements.maxPriceSlider, this.elements.maxPriceValue, 'maxPrice');
        
        // Mobile price filters
        this.initPriceSlider(this.elements.mobileMinPriceSlider, this.elements.mobileMinPriceValue, 'minPrice');
        this.initPriceSlider(this.elements.mobileMaxPriceSlider, this.elements.mobileMaxPriceValue, 'maxPrice');
    }

    initPriceSlider(slider, valueDisplay, filterKey) {
        if (!slider || !valueDisplay) return;

        slider.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            const otherKey = filterKey === 'minPrice' ? 'maxPrice' : 'minPrice';
            const otherValue = this.filters[otherKey];

            // Validate range
            if (filterKey === 'minPrice' && value > otherValue) {
                e.target.value = otherValue;
                return;
            }
            if (filterKey === 'maxPrice' && value < otherValue) {
                e.target.value = otherValue;
                return;
            }

            this.filters[filterKey] = value;
            valueDisplay.textContent = Utils.formatNumber(value);
            this.syncSliderValues(filterKey, value);
            this.debouncedFilter();
        });

        // Initialize display
        valueDisplay.textContent = Utils.formatNumber(parseInt(slider.value));
    }

    initSortFilters() {
        [this.elements.sortSelect, this.elements.mobileSortSelect].forEach(select => {
            if (select) {
                select.addEventListener('change', (e) => {
                    this.filters.sort = e.target.value;
                    this.syncSelectValues('sort', e.target.value);
                    this.applyFilters();
                });
            }
        });
    }

    initFilterButtons() {
        // Origin filters
        document.querySelectorAll('.origin-filter, .mobile-origin-filter').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.setFilterButtonActive('.origin-filter, .mobile-origin-filter', e.target);
                this.filters.origin = e.target.dataset.origin;
                this.applyFilters();
            });
        });

        // Discount filters
        document.querySelectorAll('.discount-filter, .mobile-discount-filter').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.setFilterButtonActive('.discount-filter, .mobile-discount-filter', e.target);
                this.filters.discount = e.target.dataset.discount;
                this.applyFilters();
            });
        });
    }

    setFilterButtonActive(selector, activeButton) {
        document.querySelectorAll(selector).forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-pressed', 'false');
        });
        
        // Find matching buttons and activate them
        const dataKey = activeButton.dataset.origin || activeButton.dataset.discount;
        document.querySelectorAll(selector).forEach(btn => {
            if ((btn.dataset.origin && btn.dataset.origin === dataKey) || 
                (btn.dataset.discount && btn.dataset.discount === dataKey)) {
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
            }
        });
    }

    syncMobileFilters() {
        // Sync initial values between desktop and mobile
        if (this.elements.searchInput && this.elements.mobileSearchInput) {
            this.elements.mobileSearchInput.value = this.elements.searchInput.value;
        }
    }

    syncInputValues(type, value) {
        if (type === 'search') {
            [this.elements.searchInput, this.elements.mobileSearchInput].forEach(input => {
                if (input && input.value !== value) {
                    input.value = value;
                }
            });
        }
    }

    syncSelectValues(type, value) {
        if (type === 'sort') {
            [this.elements.sortSelect, this.elements.mobileSortSelect].forEach(select => {
                if (select && select.value !== value) {
                    select.value = value;
                }
            });
        }
    }

    syncSliderValues(type, value) {
        const sliders = type === 'minPrice' 
            ? [this.elements.minPriceSlider, this.elements.mobileMinPriceSlider]
            : [this.elements.maxPriceSlider, this.elements.mobileMaxPriceSlider];
        
        const displays = type === 'minPrice'
            ? [this.elements.minPriceValue, this.elements.mobileMinPriceValue]
            : [this.elements.maxPriceValue, this.elements.mobileMaxPriceValue];

        sliders.forEach(slider => {
            if (slider && parseInt(slider.value) !== value) {
                slider.value = value;
            }
        });

        displays.forEach(display => {
            if (display) {
                display.textContent = Utils.formatNumber(value);
            }
        });
    }

    applyFilters() {
        const filteredProducts = this.products.filter(product => {
            return this.matchesAllFilters(product);
        });

        const sortedProducts = this.filters.sort !== 'default' 
            ? this.sortProducts(filteredProducts) 
            : filteredProducts;

        this.updateDisplay(sortedProducts);
    }

    matchesAllFilters(product) {
        return this.matchesSearch(product) &&
               this.matchesOrigin(product) &&
               this.matchesDiscount(product) &&
               this.matchesPriceRange(product);
    }

    matchesSearch(product) {
        if (!this.filters.search) return true;
        const name = (product.dataset.name || '').toLowerCase();
        return name.includes(this.filters.search);
    }

    matchesOrigin(product) {
        if (this.filters.origin === 'all') return true;
        const origin = (product.dataset.origin || 'local').toLowerCase();
        return origin === this.filters.origin;
    }

    matchesDiscount(product) {
        const discount = parseFloat(product.dataset.discount || '0');
        switch (this.filters.discount) {
            case 'all': return true;
            case 'discounted': return discount > 0;
            case 'no-discount': return discount === 0;
            default: return true;
        }
    }

    matchesPriceRange(product) {
        const price = parseFloat(product.dataset.price || '0');
        return price >= this.filters.minPrice && price <= this.filters.maxPrice;
    }

    sortProducts(products) {
        return [...products].sort((a, b) => {
            const priceA = parseFloat(a.dataset.price || '0');
            const priceB = parseFloat(b.dataset.price || '0');
            const discountA = parseFloat(a.dataset.discount || '0');
            const discountB = parseFloat(b.dataset.discount || '0');
            const nameA = (a.dataset.name || '').toLowerCase();
            const nameB = (b.dataset.name || '').toLowerCase();

            switch (this.filters.sort) {
                case 'price-low': return priceA - priceB;
                case 'price-high': return priceB - priceA;
                case 'discount-high': return discountB - discountA;
                case 'name': return nameA.localeCompare(nameB);
                default: return 0;
            }
        });
    }

    updateDisplay(products) {
        // Hide all products first
        this.products.forEach(product => {
            product.style.display = 'none';
            product.classList.remove('animate-fadeInUp');
        });

        if (products.length > 0) {
            // Show filtered products with animation
            products.forEach((product, index) => {
                product.style.display = 'block';
                product.style.animationDelay = `${index * CONFIG.ANIMATION_DELAY}ms`;
                
                setTimeout(() => {
                    product.classList.add('animate-fadeInUp');
                }, index * CONFIG.ANIMATION_DELAY);
            });

            this.showProductsGrid();
        } else {
            this.showNoResults();
        }
    }

    showProductsGrid() {
        if (this.noResults) this.noResults.classList.add('hidden');
        if (this.productsGrid) this.productsGrid.classList.remove('hidden');
    }

    showNoResults() {
        if (this.noResults) this.noResults.classList.remove('hidden');
        if (this.productsGrid) this.productsGrid.classList.add('hidden');
    }
}

// Cart Management System
class CartManager {
    constructor() {
        this.notification = new NotificationSystem();
        this.pendingRequests = new Set();
        this.init();
    }

    init() {
        document.querySelectorAll('.productForm').forEach(form => {
            form.addEventListener('submit', (e) => this.handleAddToCart(e));
        });
    }

    async handleAddToCart(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const button = form.querySelector('button[type="submit"]');

        if (!button || this.pendingRequests.has(form)) return;

        this.pendingRequests.add(form);
        const originalContent = button.innerHTML;
        
        try {
            this.setButtonLoading(button);
            this.validateFormData(formData);

            const response = await this.submitToCart(formData);
            const data = await response.json();

            if (data.success) {
                this.handleSuccess(data, button, originalContent);
            } else {
                throw new Error(data.message || 'Failed to add product to cart');
            }
        } catch (error) {
            this.handleError(error, button, originalContent);
        } finally {
            this.pendingRequests.delete(form);
        }
    }

    setButtonLoading(button) {
        button.disabled = true;
        button.innerHTML = '<div class="loading-spinner"></div> Adding...';
    }

    validateFormData(formData) {
        const requiredFields = [
            { name: 'product_id', required: true },
            { name: 'selected_color_id', default: '1' },
            { name: 'selected_color_name', default: 'Default' },
            { name: 'color_price', default: '0' }
        ];

        requiredFields.forEach(field => {
            const value = formData.get(field.name);
            if (field.required && (!value || value === '')) {
                throw new Error(`${field.name} is required`);
            }
            if (!field.required && (!value || value === '')) {
                formData.set(field.name, field.default);
            }
        });
    }

    async submitToCart(formData) {
        const response = await fetch('../cart/add_to_cart', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response;
    }

    handleSuccess(data, button, originalContent) {
        this.notification.show(data.message || 'Product added to cart!', 'success');
        this.updateCartCount(data.cart_count);
        this.showSuccessState(button, originalContent);
    }

    handleError(error, button, originalContent) {
        console.error('Add to cart error:', error);
        this.notification.show(error.message || 'An error occurred', 'error');
        this.resetButton(button, originalContent);
    }

    showSuccessState(button, originalContent) {
        button.innerHTML = `
            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Added!
        `;
        button.className = button.className.replace(/bg-black/, 'bg-green-500 hover:bg-green-600');

        setTimeout(() => {
            this.resetButton(button, originalContent);
        }, 2000);
    }

    resetButton(button, originalContent) {
        button.innerHTML = originalContent;
        button.className = button.className.replace(/bg-green-\d+\s*hover:bg-green-\d+/, 'bg-black');
        button.disabled = false;
    }

    updateCartCount(count) {
        const selectors = ['.cart-count', '#cart-count', '[data-cart-count]'];
        selectors.forEach(selector => {
            document.querySelectorAll(selector).forEach(element => {
                if (element) {
                    element.textContent = count.toString();
                    element.classList.add('animate-bounce');
                    setTimeout(() => element.classList.remove('animate-bounce'), 1000);
                }
            });
        });
    }
}

// Global functions for HTML compatibility
function openMobileFilters() {
    if (window.mobileFilterManager) {
        window.mobileFilterManager.open();
    }
}

function closeMobileFilters() {
    if (window.mobileFilterManager) {
        window.mobileFilterManager.close();
    }
}

function changeImage(button, direction) {
    ImageGallery.changeImage(button, direction);
}

function goToImage(dot, index) {
    ImageGallery.goToImage(dot, index);
}

// Performance Observer for monitoring
if ('PerformanceObserver' in window) {
    const observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
            if (entry.entryType === 'largest-contentful-paint') {
                console.log('LCP:', entry.startTime);
            }
        }
    });
    observer.observe({ entryTypes: ['largest-contentful-paint'] });
}

// Main Initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing enhanced product display system...');
    
    // Initialize AOS if available
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });
    }

    // Initialize all systems
    try {
        window.mobileFilterManager = new MobileFilterManager();
        window.imageGalleryInstance = new ImageGallery();
        new DropdownManager();
        new ProductFilter();
        new CartManager();

        console.log('All systems initialized successfully');
    } catch (error) {
        console.error('Initialization error:', error);
    }

    // Enhanced button interactions
    initButtonEffects();
    
    // Intersection Observer for product animations
    initProductObserver();
    
    // Lazy loading for images
    initLazyLoading();
    

    console.log('Enhanced product display system ready');
});

// Additional initialization functions
function initButtonEffects() {
    const buttons = document.querySelectorAll('.btn-buy, .btn-preorder, .animated-view-btn');
    
    buttons.forEach(button => {
        // Mouse events
        button.addEventListener('mouseenter', function() {
            if (!this.disabled) {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            }
        });

        button.addEventListener('mouseleave', function() {
            if (!this.disabled) {
                this.style.transform = 'translateY(0) scale(1)';
            }
        });

        // Focus events for accessibility
        button.addEventListener('focus', function() {
            this.style.outline = '2px solid #f97316';
            this.style.outlineOffset = '2px';
        });

        button.addEventListener('blur', function() {
            this.style.outline = '';
            this.style.outlineOffset = '';
        });

        // Keyboard interaction
        button.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.style.transform = 'translateY(1px) scale(0.98)';
            }
        });

        button.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                this.style.transform = 'translateY(-2px) scale(1.02)';
                this.click();
            }
        });
    });
}

function initProductObserver() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('animate-fadeInUp');
                }, index * 50);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.product-item').forEach(card => {
        observer.observe(card);
    });
}

function initLazyLoading() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    if (img.dataset.src) {
                        // Show loading state
                        img.style.opacity = '0.5';
                        
                        // Load image
                        const tempImg = new Image();
                        tempImg.onload = () => {
                            img.src = tempImg.src;
                            img.style.opacity = '1';
                            img.removeAttribute('data-src');
                        };
                        tempImg.onerror = () => {
                            img.src = '../img/placeholder.jpg';
                            img.style.opacity = '1';
                            img.removeAttribute('data-src');
                        };
                        tempImg.src = img.dataset.src;
                        
                        imageObserver.unobserve(img);
                    }
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '100px'
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for browsers without IntersectionObserver
        document.querySelectorAll('img[data-src]').forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
    }
}


// Accessibility improvements
function enhanceAccessibility() {
    // Add skip links
    if (!document.querySelector('.skip-link')) {
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.className = 'skip-link sr-only focus:not-sr-only';
        skipLink.textContent = 'Skip to main content';
        document.body.insertBefore(skipLink, document.body.firstChild);
    }

    // Improve keyboard navigation for product cards
    document.querySelectorAll('.product-item').forEach(card => {
        if (!card.hasAttribute('tabindex')) {
            card.setAttribute('tabindex', '0');
        }
        
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const viewButton = card.querySelector('.animated-view-btn');
                if (viewButton) {
                    viewButton.click();
                }
            }
        });
    });

    // Add ARIA labels to interactive elements
    document.querySelectorAll('.gallery-arrow').forEach(arrow => {
        if (!arrow.hasAttribute('aria-label')) {
            arrow.setAttribute('aria-label', 
                arrow.classList.contains('prev') ? 'Previous image' : 'Next image');
        }
    });

    document.querySelectorAll('.gallery-dot').forEach((dot, index) => {
        if (!dot.hasAttribute('aria-label')) {
            dot.setAttribute('aria-label', `Image ${index + 1}`);
        }
    });
}

// Initialize accessibility enhancements
document.addEventListener('DOMContentLoaded', enhanceAccessibility);

// Memory cleanup on page unload
window.addEventListener('beforeunload', () => {
    // Clean up any intervals, timeouts, or observers
    if (window.imageGalleryInstance) {
        window.imageGalleryInstance.galleries.clear();
    }
    
    // Clear any pending requests
    document.querySelectorAll('.productForm').forEach(form => {
        form.removeEventListener('submit', () => {});
    });
});
    </script>
</body>

</html>