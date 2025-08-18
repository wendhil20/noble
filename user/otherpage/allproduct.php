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

// ✅ Session check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// ✅ Get filter parameters
$category_filter = $_GET['category'] ?? '';
$subcategory_filter = $_GET['sub'] ?? '';

// ✅ Optimized query with proper joins
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

// ✅ Build WHERE clause dynamically
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

if (!empty($where_conditions)) {
    $material_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$material_query .= " ORDER BY pv.discount DESC, pv.percent ASC, p.id, pc.id";

// ✅ Execute query
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

// ✅ Helper function for safe output
function safe_output($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

// ✅ Helper function for price calculation
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

// ✅ Helper function for image processing
function process_product_images($main_image, $sub_images, $type_image) {
    $images = [];
    
    // Add main image
    if (!empty($main_image)) {
        $images[] = '../../' . $main_image;
    }
    
    // Add sub images
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
    
    // Add type image as fallback
    if (!empty($type_image)) {
        $images[] = '../../' . $type_image;
    }
    
    // Remove duplicates and ensure at least one image
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
    <title>Product Display - Noble Store</title>
    <meta name="description" content="Discover high-quality products curated for your lifestyle">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
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

        .bubble-bounce {
            position: absolute;
            display: inline-block;
            opacity: 0.15;
            border-radius: 50%;
            animation: bubble-bounce 2.2s cubic-bezier(.68, -0.55, .27, 1.55) infinite;
            box-shadow: 0 8px 32px 0 rgba(251, 146, 60, 0.25);
        }

        @keyframes bubble-bounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.08); }
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

        .price-gradient {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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

        .origin-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .origin-international {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: var(--accent-color);
        }

        .origin-local {
            background: linear-gradient(135deg, #bfdbfe 0%, #93c5fd 100%);
            color: #2563eb;
        }

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

        .filter-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(249, 115, 22, 0.1);
            backdrop-filter: blur(10px);
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
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 10;
        }

        .image-gallery:hover .gallery-arrow {
            opacity: 1;
        }

        .gallery-arrow.prev { left: 8px; }
        .gallery-arrow.next { right: 8px; }
        .gallery-arrow:hover { background: rgba(0, 0, 0, 0.7); }

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
    </style>
</head>

<body class="bg-gray-50 font-mont">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Main Content -->
    <main class="px-4 py-16">
        <!-- Header Section -->
        <header class="text-center mb-16 relative">
            <!-- Animated Background Bubbles -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
                <span class="bubble-bounce" style="left: 15%; top: 25%; width: 100px; height: 100px; background: radial-gradient(circle at 40% 40%, #fbbf24 60%, #f59e42 100%); animation-delay: 0s;"></span>
                <span class="bubble-bounce" style="left: 65%; top: 45%; width: 70px; height: 70px; background: radial-gradient(circle at 60% 60%, #f97316 60%, #fbbf24 100%); animation-delay: 0.7s;"></span>
                <span class="bubble-bounce" style="left: 35%; top: 65%; width: 50px; height: 50px; background: radial-gradient(circle at 50% 50%, #f59e42 60%, #fbbf24 100%); animation-delay: 1.2s;"></span>
                <span class="bubble-bounce" style="left: 75%; top: 15%; width: 80px; height: 80px; background: radial-gradient(circle at 60% 60%, #fbbf24 60%, #f59e42 100%); animation-delay: 1.7s;"></span>
            </div>
            
            <div class="relative z-10 text-center">
                <h1 class="text-5xl md:text-6xl font-black text-transparent bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text mb-4 tracking-tight" data-aos="fade-up">
                    All Products
                </h1>

                <div class="mx-auto w-40 h-1.5 bg-gradient-to-r from-orange-500 via-red-500 to-transparent rounded-full" data-aos="fade-up" data-aos-delay="200"></div>

                <p class="mt-6 text-lg text-gray-700 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="400">
                    Discover a wide selection of high-quality products curated to match your lifestyle.
                </p>
            </div>
        </header>

        <!-- Filters Section -->
        <section class="max-w-full mx-auto mb-12" data-aos="fade-up" data-aos-delay="300">
            <div class="filter-card rounded-2xl shadow-lg p-6 mb-8">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Search Filter -->
                    <div class="lg:col-span-1">
                        <label for="searchFilter" class="block text-sm font-semibold text-gray-700 mb-2">Search Products</label>
                        <div class="relative">
                            <input type="text" 
                                   id="searchFilter" 
                                   placeholder="Search by name..." 
                                   autocomplete="off"
                                   class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                            <svg class="absolute right-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Origin Filter -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Origin</label>
                        <div class="flex gap-2" role="group" aria-label="Origin filter">
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium active" data-origin="all" type="button">
                                All
                            </button>
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium" data-origin="local" type="button">
                                Local
                            </button>
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium" data-origin="international" type="button">
                                International
                            </button>
                        </div>
                    </div>

                    <!-- Price Range Filter -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Price Range</label>
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <input type="range" 
                                           id="minPrice" 
                                           class="range-slider w-full" 
                                           min="0" 
                                           max="10000" 
                                           value="0" 
                                           step="100"
                                           aria-label="Minimum price">
                                    <div class="text-xs text-gray-500 mt-1">Min: ₱<span id="minPriceValue">0</span></div>
                                </div>
                                <div class="flex-1">
                                    <input type="range" 
                                           id="maxPrice" 
                                           class="range-slider w-full" 
                                           min="0" 
                                           max="10000" 
                                           value="10000" 
                                           step="100"
                                           aria-label="Maximum price">
                                    <div class="text-xs text-gray-500 mt-1">Max: ₱<span id="maxPriceValue">10,000</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Filters Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-200">
                    <!-- Discount Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Discount</label>
                        <div class="flex gap-2 flex-wrap" role="group" aria-label="Discount filter">
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium active" data-discount="all" type="button">
                                All
                            </button>
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium" data-discount="discounted" type="button">
                                On Sale
                            </button>
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium" data-discount="no-discount" type="button">
                                Regular Price
                            </button>
                        </div>
                    </div>

                    <!-- Sort Filter -->
                    <div>
                        <label for="sortFilter" class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
                        <select id="sortFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm">
                            <option value="default">Default</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="discount-high">Discount: High to Low</option>
                            <option value="name">Name: A to Z</option>
                        </select>
                    </div>

                    <!-- Results Count -->
                    <div class="flex items-end">
                        <div class="text-sm text-gray-600" aria-live="polite">
                            Showing <span id="resultCount">0</span> products
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Products Grid -->
        <section class="max-w-full mx-auto">
            <div id="productsGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-6" data-aos="fade-up" data-aos-delay="400">
                
                <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($material_results)): 
                        $pricing = calculate_price($row['price'], $row['percent'] ?? 0, $row['discount'] ?? 0);
                        $all_images = process_product_images($row['main_image'], $row['sub_images'], $row['type_image']);
                        $discount = (float)($row['discount'] ?? 0);
                    ?>
                        <article class="product-item product-card rounded-2xl shadow-lg p-6 group flex flex-col justify-between h-[520px] text-center relative overflow-hidden transition-all duration-300"
                                 data-name="<?= safe_output(strtolower($row['namevariant'])) ?>"
                                 data-price="<?= $pricing['final'] ?>"
                                 data-original-price="<?= $pricing['original'] ?>"
                                 data-discount="<?= $discount ?>"
                                 data-origin="<?= safe_output(strtolower($row['origin'] ?? 'local')) ?>">
                            
                            <!-- Discount Badge -->
                            <?php if ($discount > 0): ?>
                                <div class="absolute top-3 left-3 z-20">
                                    <div class="discount-badge text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg">
                                        -<?= number_format($discount, 0) ?>%
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Triangle Badge/Icon -->
                            <div class="absolute top-0 right-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" 
                                         alt="Product badge" 
                                         class="absolute top-1 right-1 w-9 h-9 object-contain" 
                                         loading="lazy" />
                                </div>
                            </div>

                            <!-- Product Image Gallery -->
                            <div class="aspect-square w-full bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-200 rounded-xl overflow-hidden mb-4 group-hover:shadow-inner transition-all duration-300">
                                <div class="image-gallery w-full h-full relative">
                                    <div class="gallery-container h-full" data-current="0">
                                        <?php foreach ($all_images as $index => $image): ?>
                                            <img src="<?= safe_output($image) ?>" 
                                                 alt="<?= safe_output($row['namevariant']) ?> - Image <?= $index + 1 ?>" 
                                                 class="gallery-image object-contain transition-transform duration-300 group-hover:scale-105"
                                                 style="<?= $index === 0 ? '' : 'display: none;' ?>"
                                                 loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgNzBMMTMwIDEwMEgxMTBWMTMwSDkwVjEwMEg3MEwxMDAgNzBaIiBmaWxsPSIjOUI5QjlCIi8+Cjx0ZXh0IHg9IjEwMCIgeT0iMTYwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjOUI5QjlCIiBmb250LXNpemU9IjEyIiBmb250LWZhbWlseT0iQXJpYWwiPk5vIEltYWdlPC90ZXh0Pgo8L3N2Zz4K'" />
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (count($all_images) > 1): ?>
                                        <!-- Navigation Arrows -->
                                        <button class="gallery-arrow prev" 
                                                onclick="changeImage(this, -1)" 
                                                type="button"
                                                aria-label="Previous image">‹</button>
                                        <button class="gallery-arrow next" 
                                                onclick="changeImage(this, 1)" 
                                                type="button"
                                                aria-label="Next image">›</button>
                                        
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
                            <div class="mt-auto">
                                <h2 class="text-lg font-bold underline underline-offset-4 text-orange-600 leading-snug break-words mb-3 group-hover:text-orange-700 transition-colors">
                                    <?= safe_output($row['namevariant']) ?>
                                </h2>
                                
                                <dl class="text-sm text-gray-600 text-center space-y-2 mb-4">
                                    <div><dt class="font-semibold text-gray-800 inline">Color:</dt> <dd class="inline"><?= safe_output($row['color'] ?? 'N/A') ?></dd></div>
                                    <div><dt class="font-semibold text-gray-800 inline">Size:</dt> <dd class="inline"><?= safe_output($row['size'] ?? 'N/A') ?></dd></div>
                                </dl>

                                <!-- Pricing -->
                                <div class="mb-4">
                                    <?php if ($discount > 0): ?>
                                        <p class="text-sm text-gray-400 line-through mb-1">₱<?= number_format($pricing['original'], 2) ?></p>
                                        <p class="text-xl price-gradient font-mont mb-2">
                                            ₱<?= number_format($pricing['final'], 2) ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-xl price-gradient font-mont mb-2">₱<?= number_format($pricing['original'], 2) ?></p>
                                    <?php endif; ?>
                                    
                                    <!-- Origin Badge -->
                                    <?php if (!empty($row['origin'])): ?>
                                    <span class="origin-badge <?= $row['origin'] === 'international' ? 'origin-international' : 'origin-local' ?>">
                                        <?= safe_output(ucfirst($row['origin'])) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex justify-center gap-3 mt-4 flex-wrap">
                                    <!-- Buy Button -->
                                    <form action="product_view" method="GET" class="inline">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit" 
                                                class="btn-buy text-white text-sm px-6 py-2.5 rounded-full flex items-center gap-2 shadow-lg font-semibold"
                                                aria-label="View product details">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                            </svg>
                                            Buy
                                        </button>
                                    </form>

                                    <!-- Pre-Order Button -->
                                    <form class="productForm inline" data-product-id="<?= (int)$row['product_id'] ?>">
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

                                        <button type="submit"
                                                class="btn-preorder text-white text-sm px-5 py-2.5 rounded-full flex items-center gap-2 shadow-lg font-semibold ring-2 ring-orange-500 ring-offset-1"
                                                aria-label="Add to cart">
                                            <img src="../img/ecommerce.png" alt="" class="w-4 h-4" aria-hidden="true" />
                                            Pre-Order
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-16">
                        <div class="text-6xl text-gray-300 mb-4">📦</div>
                        <h3 class="text-2xl font-bold text-gray-600 mb-2">No products available</h3>
                        <p class="text-gray-500">Check back later for new products</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- No Results Message (Hidden by default) -->
            <div id="noResults" class="hidden text-center py-16" role="status" aria-live="polite">
                <div class="text-6xl text-gray-300 mb-4">🔍</div>
                <h3 class="text-2xl font-bold text-gray-600 mb-2">No products found</h3>
                <p class="text-gray-500">Try adjusting your filters or search terms</p>
            </div>
        </section>
    </main>

    <!-- Notification Container -->
    <div id="notificationContainer" aria-live="assertive" aria-atomic="true"></div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <script>
        'use strict';

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

            formatNumber(num) {
                return new Intl.NumberFormat('en-US').format(num);
            },

            sanitizeInput(str) {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }
        };

        // Notification System
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('notificationContainer');
                if (!this.container) {
                    this.container = document.createElement('div');
                    this.container.id = 'notificationContainer';
                    this.container.setAttribute('aria-live', 'assertive');
                    this.container.setAttribute('aria-atomic', 'true');
                    document.body.appendChild(this.container);
                }
            }

            show(message, type = 'success', duration = 3000) {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.textContent = message;
                notification.setAttribute('role', 'alert');
                
                this.container.appendChild(notification);
                
                // Show notification
                requestAnimationFrame(() => {
                    notification.classList.add('show');
                });
                
                // Auto remove
                setTimeout(() => {
                    this.remove(notification);
                }, duration);

                return notification;
            }

            remove(notification) {
                if (notification && notification.parentNode) {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }
            }
        }

        // Image Gallery Management
        class ImageGallery {
            static changeImage(button, direction) {
                const gallery = button.closest('.image-gallery');
                const container = gallery.querySelector('.gallery-container');
                const images = gallery.querySelectorAll('.gallery-image');
                const dots = gallery.querySelectorAll('.gallery-dot');
                
                let current = parseInt(container.dataset.current) || 0;
                let newIndex = current + direction;
                
                if (newIndex < 0) newIndex = images.length - 1;
                if (newIndex >= images.length) newIndex = 0;
                
                this.showImage(gallery, newIndex);
            }

            static goToImage(dot, index) {
                const gallery = dot.closest('.image-gallery');
                this.showImage(gallery, index);
            }

            static showImage(gallery, index) {
                const container = gallery.querySelector('.gallery-container');
                const images = gallery.querySelectorAll('.gallery-image');
                const dots = gallery.querySelectorAll('.gallery-dot');
                
                // Hide all images
                images.forEach((img, i) => {
                    img.style.display = i === index ? 'block' : 'none';
                });
                
                // Update dots
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                    dot.setAttribute('tabindex', i === index ? '0' : '-1');
                });
                
                // Update current index
                container.dataset.current = index.toString();
            }
        }

        // Product Filter System
        class ProductFilter {
            constructor() {
                this.products = document.querySelectorAll('.product-item');
                this.searchInput = document.getElementById('searchFilter');
                this.minPriceSlider = document.getElementById('minPrice');
                this.maxPriceSlider = document.getElementById('maxPrice');
                this.minPriceValue = document.getElementById('minPriceValue');
                this.maxPriceValue = document.getElementById('maxPriceValue');
                this.sortSelect = document.getElementById('sortFilter');
                this.resultCount = document.getElementById('resultCount');
                this.noResults = document.getElementById('noResults');
                this.productsGrid = document.getElementById('productsGrid');
                
                this.currentFilters = {
                    search: '',
                    origin: 'all',
                    discount: 'all',
                    minPrice: 0,
                    maxPrice: 10000,
                    sort: 'default'
                };

                this.debouncedApplyFilters = Utils.debounce(() => this.applyFilters(), 300);
                this.init();
            }

            init() {
                // Search filter with debounce
                this.searchInput?.addEventListener('input', (e) => {
                    this.currentFilters.search = e.target.value.toLowerCase().trim();
                    this.debouncedApplyFilters();
                });

                // Price range sliders
                this.minPriceSlider?.addEventListener('input', (e) => {
                    const value = parseInt(e.target.value);
                    this.currentFilters.minPrice = value;
                    if (this.minPriceValue) {
                        this.minPriceValue.textContent = Utils.formatNumber(value);
                    }
                    this.debouncedApplyFilters();
                });

                this.maxPriceSlider?.addEventListener('input', (e) => {
                    const value = parseInt(e.target.value);
                    this.currentFilters.maxPrice = value;
                    if (this.maxPriceValue) {
                        this.maxPriceValue.textContent = Utils.formatNumber(value);
                    }
                    this.debouncedApplyFilters();
                });

                // Sort filter
                this.sortSelect?.addEventListener('change', (e) => {
                    this.currentFilters.sort = e.target.value;
                    this.applyFilters();
                });

                // Origin filter buttons
                document.querySelectorAll('.origin-filter').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        document.querySelectorAll('.origin-filter').forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');
                        this.currentFilters.origin = e.target.dataset.origin;
                        this.applyFilters();
                    });
                });

                // Discount filter buttons
                document.querySelectorAll('.discount-filter').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        document.querySelectorAll('.discount-filter').forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');
                        this.currentFilters.discount = e.target.dataset.discount;
                        this.applyFilters();
                    });
                });

                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.clearAllFilters();
                    }
                });

                // Initial filter application
                this.applyFilters();
            }

            clearAllFilters() {
                // Reset inputs
                if (this.searchInput) this.searchInput.value = '';
                if (this.minPriceSlider) this.minPriceSlider.value = '0';
                if (this.maxPriceSlider) this.maxPriceSlider.value = '10000';
                if (this.sortSelect) this.sortSelect.value = 'default';
                
                // Reset filter buttons
                document.querySelectorAll('.filter-button.active').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector('[data-origin="all"]')?.classList.add('active');
                document.querySelector('[data-discount="all"]')?.classList.add('active');
                
                // Reset filter state
                this.currentFilters = {
                    search: '',
                    origin: 'all',
                    discount: 'all',
                    minPrice: 0,
                    maxPrice: 10000,
                    sort: 'default'
                };

                this.applyFilters();
            }

            applyFilters() {
                let visibleProducts = Array.from(this.products).filter(product => {
                    return this.matchesSearch(product) && 
                           this.matchesOrigin(product) && 
                           this.matchesDiscount(product) && 
                           this.matchesPriceRange(product);
                });

                // Sort products
                if (this.currentFilters.sort !== 'default') {
                    visibleProducts = this.sortProducts(visibleProducts);
                }

                // Hide all products first
                this.products.forEach(product => {
                    product.classList.add('hidden');
                    product.style.animationDelay = '';
                });

                // Show filtered products with stagger animation
                if (visibleProducts.length > 0) {
                    visibleProducts.forEach((product, index) => {
                        product.classList.remove('hidden');
                        product.style.animationDelay = `${index * 0.05}s`;
                        product.classList.add('animate-fadeInUp');
                    });
                    
                    if (this.noResults) this.noResults.classList.add('hidden');
                    if (this.productsGrid) this.productsGrid.classList.remove('hidden');
                } else {
                    if (this.noResults) this.noResults.classList.remove('hidden');
                    if (this.productsGrid) this.productsGrid.classList.add('hidden');
                }

                // Update result count
                if (this.resultCount) {
                    this.resultCount.textContent = visibleProducts.length.toString();
                }
            }

            matchesSearch(product) {
                if (!this.currentFilters.search) return true;
                const name = product.dataset.name || '';
                return name.includes(this.currentFilters.search);
            }

            matchesOrigin(product) {
                if (this.currentFilters.origin === 'all') return true;
                const origin = product.dataset.origin || 'local';
                return origin === this.currentFilters.origin;
            }

            matchesDiscount(product) {
                const discount = parseFloat(product.dataset.discount || '0');
                
                switch (this.currentFilters.discount) {
                    case 'all': return true;
                    case 'discounted': return discount > 0;
                    case 'no-discount': return discount === 0;
                    default: return true;
                }
            }

            matchesPriceRange(product) {
                const price = parseFloat(product.dataset.price || '0');
                return price >= this.currentFilters.minPrice && price <= this.currentFilters.maxPrice;
            }

            sortProducts(products) {
                return products.sort((a, b) => {
                    const priceA = parseFloat(a.dataset.price || '0');
                    const priceB = parseFloat(b.dataset.price || '0');
                    const discountA = parseFloat(a.dataset.discount || '0');
                    const discountB = parseFloat(b.dataset.discount || '0');
                    const nameA = a.dataset.name || '';
                    const nameB = b.dataset.name || '';

                    switch (this.currentFilters.sort) {
                        case 'price-low': return priceA - priceB;
                        case 'price-high': return priceB - priceA;
                        case 'discount-high': return discountB - discountA;
                        case 'name': return nameA.localeCompare(nameB);
                        default: return 0;
                    }
                });
            }
        }

        // Cart Management
        class CartManager {
            constructor() {
                this.notification = new NotificationSystem();
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
                
                if (!button) return;

                const originalContent = button.innerHTML;
                
                // Show loading state
                button.disabled = true;
                button.innerHTML = '<div class="loading-spinner"></div> Adding...';

                try {
                    // Ensure required fields
                    this.ensureRequiredFields(formData);

                    const response = await fetch('../cart/add_to_cart', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        this.notification.show(data.message || 'Product added to cart!', 'success');
                        this.updateCartCount(data.cart_count);
                        this.showSuccessState(button, originalContent);
                    } else {
                        throw new Error(data.message || 'Failed to add product to cart');
                    }
                } catch (error) {
                    console.error('Add to cart error:', error);
                    this.notification.show(error.message || 'An error occurred', 'error');
                    this.resetButton(button, originalContent);
                }
            }

            ensureRequiredFields(formData) {
                const requiredFields = [
                    { name: 'selected_color_id', default: '1' },
                    { name: 'selected_color_name', default: 'Default' },
                    { name: 'color_price', default: '0' }
                ];

                requiredFields.forEach(field => {
                    if (!formData.get(field.name) || formData.get(field.name) === '') {
                        formData.set(field.name, field.default);
                    }
                });
            }

            showSuccessState(button, originalContent) {
                button.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg> 
                    Added!
                `;
                button.className = button.className.replace('btn-preorder', 'bg-green-500 hover:bg-green-600');

                setTimeout(() => {
                    this.resetButton(button, originalContent);
                }, 2000);
            }

            resetButton(button, originalContent) {
                button.innerHTML = originalContent;
                button.className = button.className.replace(/bg-green-\d+\s*hover:bg-green-\d+/g, 'btn-preorder');
                button.disabled = false;
            }

            updateCartCount(count) {
                const cartElements = document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]');
                cartElements.forEach(element => {
                    if (element) {
                        element.textContent = count.toString();
                        element.classList.add('animate-bounce');
                        setTimeout(() => element.classList.remove('animate-bounce'), 1000);
                    }
                });
            }
        }

        // Global functions for gallery (called by inline onclick)
        function changeImage(button, direction) {
            ImageGallery.changeImage(button, direction);
        }

        function goToImage(dot, index) {
            ImageGallery.goToImage(dot, index);
        }

        // Initialize everything when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AOS animations
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 1000,
                    once: true,
                    offset: 100,
                    easing: 'ease-out-cubic'
                });
            }

            // Initialize main components
            new ProductFilter();
            new CartManager();

            // Enhanced button interactions
            const buttons = document.querySelectorAll('.btn-buy, .btn-preorder');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Intersection Observer for lazy animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '50px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fadeInUp');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observe all product cards
            document.querySelectorAll('.product-item').forEach(card => {
                observer.observe(card);
            });

            // Add keyboard navigation for gallery
            document.addEventListener('keydown', function(e) {
                const focusedElement = document.activeElement;
                if (focusedElement && focusedElement.closest('.image-gallery')) {
                    const gallery = focusedElement.closest('.image-gallery');
                    
                    switch(e.key) {
                        case 'ArrowLeft':
                            e.preventDefault();
                            ImageGallery.changeImage(gallery.querySelector('.gallery-arrow.prev'), -1);
                            break;
                        case 'ArrowRight':
                            e.preventDefault();
                            ImageGallery.changeImage(gallery.querySelector('.gallery-arrow.next'), 1);
                            break;
                    }
                }
            });

            // Performance optimization: Lazy load images
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }

            console.log('🚀 E-commerce product display initialized successfully!');
        });

        // Error handling for uncaught errors
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
        });

        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
        });
    </script>
</body>
</html>