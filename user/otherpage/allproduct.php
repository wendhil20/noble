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

        .gallery-arrow.prev {
            left: 8px;
        }

        .gallery-arrow.next {
            right: 8px;
        }

        .gallery-arrow:hover {
            background: rgba(0, 0, 0, 0.7);
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card {
            min-height: 560px;
            max-height: 560px;
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
    </style>
</head>

<body class="bg-gray-50 font-mont">
    <?php include '../navbar/top.php'; ?>

    <!-- Main Content -->
    <main class="px-4 py-16">
        <!-- Header Section -->
        <header class="text-center mb-16 relative">
            <!-- Animated Background Bubbles -->
           

            <div class="relative z-10 text-center">
                <h1 class="text-5xl md:text-6xl font-black text-transparent bg-gradient-to-r from-orange-400 to-orange-400 bg-clip-text mb-4 tracking-tight" data-aos="fade-up">
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
            <div class="filter-card p-6 mb-8 bg-white rounded-xl shadow-lg">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Search Filter -->
                    <div class="lg:col-span-1">
                        <label for="searchFilter" class="block text-sm font-semibold text-gray-700 mb-2">Search Products</label>
                        <div class="relative">
                            <input type="text" id="searchFilter" placeholder="Search by name..." autocomplete="off" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                            <svg class="absolute right-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Origin Filter -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Origin</label>
                        <div class="flex gap-2" role="group" aria-label="Origin filter">
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium active" data-origin="all" type="button">All</button>
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium" data-origin="local" type="button">Local</button>
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium" data-origin="international" type="button">International</button>
                        </div>
                    </div>

                    <!-- Price Range Filter -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Price Range</label>
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <input type="range" id="minPrice" class="range-slider w-full" min="0" max="10000" value="0" step="100" aria-label="Minimum price">
                                    <div class="text-xs text-gray-500 mt-1">Min: ₱<span id="minPriceValue">0</span></div>
                                </div>
                                <div class="flex-1">
                                    <input type="range" id="maxPrice" class="range-slider w-full" min="0" max="10000" value="10000" step="100" aria-label="Maximum price">
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
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium active" data-discount="all" type="button">All</button>
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium" data-discount="discounted" type="button">On Sale</button>
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium" data-discount="no-discount" type="button">Regular Price</button>
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
        <section class="max-w-full mx-auto px-4 py-8">
            <div id="productsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-6" data-aos="fade-up" data-aos-delay="400">

                <?php if ($material_results && mysqli_num_rows($material_results) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($material_results)):
                        $pricing = calculate_price($row['price'], $row['percent'] ?? 0, $row['discount'] ?? 0);
                        $all_images = process_product_images($row['main_image'], $row['sub_images'], $row['type_image']);
                        $discount = (float)($row['discount'] ?? 0);
                    ?>
                        <article class="product-item product-card hover:shadow-2xl  p-5 group flex flex-col h-[560px] text-center relative overflow-hidden transition-all duration-500 hover:-translate-y-2 hover:scale-[1.02] "
                            data-name="<?= safe_output(strtolower($row['namevariant'])) ?>"
                            data-price="<?= $pricing['final'] ?>"
                            data-original-price="<?= $pricing['original'] ?>"
                            data-discount="<?= $discount ?>"
                            data-origin="<?= safe_output(strtolower($row['origin'] ?? 'local')) ?>">

                            <!-- Discount Badge -->
                            <?php if ($discount > 0): ?>
                                <div class="absolute top-4 left-4 z-20">
                                    <div class="discount-badge bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-lg animate-pulse">
                                        -<?= number_format($discount, 0) ?>%
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Triangle Badge/Icon -->
                            <div class="absolute top-0 right-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Product badge" class="absolute top-2 right-2 w-8 h-8 object-contain opacity-80 group-hover:opacity-100 transition-opacity" loading="lazy" />
                                </div>
                            </div>

                            <!-- Product Image Gallery -->
                            <div class="aspect-square w-full overflow-hidden mb-4 group-hover:shadow-inner transition-all duration-300">
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
                                    <h2 class="uppercase text-lg font-bold text-gray-800 leading-tight mb-3 group-hover:text-orange-600 transition-colors duration-300 line-clamp-2">
                                        <?= safe_output($row['namevariant']) ?>
                                    </h2>

                                    <!-- Color & Size Modal Trigger -->
                                    <div x-data="{ open: false }" class="text-center mb-3">
                                        <button @click="open = true" class="text-sm text-gray-600 underline hover:text-gray-800 transition">
                                            View Color & Size
                                        </button>

                                        <!-- Modal -->
                                        <div x-show="open" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-transition @click.away="open = false" style="display: none;">
                                            <div class="bg-white max-w-sm w-full p-6 relative animate-fade-in">
                                                <button @click="open = false" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
                                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Product Details</h3>
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
                                    <div class="mb-4">
                                        <?php if ($discount > 0): ?>
                                            <p class="text-sm text-gray-400 line-through mb-1">₱<?= number_format($pricing['original'], 2) ?></p>
                                            <p class="text-xl font-bold bg-clip-text text-green-600 mb-2">
                                                ₱<?= number_format($pricing['final'], 2) ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-xl font-bold  bg-clip-text text-green-600 mb-2">
                                                ₱<?= number_format($pricing['original'], 2) ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Origin Badge -->
                                        <?php if (!empty($row['origin'])): ?>
                                            <span class="inline-block px-3 py-1 text-xs font-medium rounded-full <?= $row['origin'] === 'international' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                                <?= safe_output(ucfirst($row['origin'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>


                                <!-- Replace your current View Details Button section with this -->
                                <div class="flex flex-col gap-2 mt-auto">
                                    <!-- Animated View Details Button -->
                                    <form action="product_view" method="GET" class="w-full flex justify-start">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit" class="animated-view-btn">
                                            <div class="btn-sign">
                                                <i class="fa-solid fa-bag-shopping"></i>
                                            </div>
                                            <div class="btn-text">View Details</div>
                                        </button>
                                    </form>

                                    <!-- Add to Cart Button (keeping your existing one) -->
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

                                        <button type="submit" class="w-full bg-black hover:from-orange-600 hover:to-orange-800 text-white text-sm px-6 py-3 flex items-center justify-center gap-2 font-semibold transition-all duration-300 transform hover:scale-105" aria-label="Add to cart">
                                            <img src="../img/icon/cart.png" alt="" class="w-6 h-6" aria-hidden="true" />
                                            Add to Cart
                                        </button>
                                    </form>
                                </div>

                                <style>
                                    /* Animated View Details Button Styles */
                                    .animated-view-btn {
                                        display: flex;
                                        align-items: center;
                                        justify-content: flex-start;
                                        width: 48px;
                                        height: 45px;
                                        border: none;
                                        cursor: pointer;
                                        position: relative;
                                        overflow: hidden;
                                        transition-duration: .3s;
                                        background: linear-gradient(135deg, #000000 0%, #000000 100%);
                                    }

                                    /* Icon */
                                    .animated-view-btn .btn-sign {
                                        width: 100%;
                                        font-size: 1.2em;
                                        color: white;
                                        transition-duration: .3s;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                    }

                                    /* Text */
                                    .animated-view-btn .btn-text {
                                        position: absolute;
                                        right: 0%;
                                        width: 0%;
                                        opacity: 0;
                                        color: white;
                                        font-size: 0.9em;
                                        font-weight: 600;
                                        transition-duration: .3s;
                                        white-space: nowrap;
                                    }

                                    /* Hover effect */
                                    .animated-view-btn:hover {
                                        width: 180px;
                                        transition-duration: .3s;
                                        background: linear-gradient(135deg, #000000 0%, #000000 100%);
                                    }

                                    .animated-view-btn:hover .btn-sign {
                                        width: 35%;
                                        transition-duration: .3s;
                                        padding-left: 15px;
                                    }

                                    .animated-view-btn:hover .btn-text {
                                        opacity: 1;
                                        width: 65%;
                                        transition-duration: .3s;
                                        padding-right: 15px;
                                    }

                                    /* Click effect */
                                    .animated-view-btn:active {
                                        transform: translate(1px, 1px);
                                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                                    }

                                    /* Focus accessibility */
                                    .animated-view-btn:focus {
                                        outline: 2px solid #f97316;
                                        outline-offset: 2px;
                                    }
                                </style>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-20">
                        <div class="max-w-md mx-auto">
                            <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-3">No products available</h3>
                            <p class="text-gray-500 text-lg">Check back later for new products</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- No Results Message -->
            <div id="noResults" class="hidden text-center py-20" role="status" aria-live="polite">
                <div class="max-w-md mx-auto">
                    <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-3">No products found</h3>
                    <p class="text-gray-500 text-lg">Try adjusting your filters or search terms</p>
                </div>
            </div>
        </section>
    </main>

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

    <!-- Notification Container -->
    <div id="notificationContainer" aria-live="assertive" aria-atomic="true"></div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>

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

                requestAnimationFrame(() => {
                    notification.classList.add('show');
                });

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

                images.forEach((img, i) => {
                    img.style.display = i === index ? 'block' : 'none';
                });

                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                    dot.setAttribute('tabindex', i === index ? '0' : '-1');
                });

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
                this.searchInput?.addEventListener('input', (e) => {
                    this.currentFilters.search = e.target.value.toLowerCase().trim();
                    this.debouncedApplyFilters();
                });

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

                this.sortSelect?.addEventListener('change', (e) => {
                    this.currentFilters.sort = e.target.value;
                    this.applyFilters();
                });

                document.querySelectorAll('.origin-filter').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        document.querySelectorAll('.origin-filter').forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');
                        this.currentFilters.origin = e.target.dataset.origin;
                        this.applyFilters();
                    });
                });

                document.querySelectorAll('.discount-filter').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        document.querySelectorAll('.discount-filter').forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');
                        this.currentFilters.discount = e.target.dataset.discount;
                        this.applyFilters();
                    });
                });

                this.applyFilters();
            }

            applyFilters() {
                let visibleProducts = Array.from(this.products).filter(product => {
                    return this.matchesSearch(product) &&
                        this.matchesOrigin(product) &&
                        this.matchesDiscount(product) &&
                        this.matchesPriceRange(product);
                });

                if (this.currentFilters.sort !== 'default') {
                    visibleProducts = this.sortProducts(visibleProducts);
                }

                this.products.forEach(product => {
                    product.classList.add('hidden');
                    product.style.animationDelay = '';
                });

                if (visibleProducts.length > 0) {
                    const grid = this.productsGrid;
                    visibleProducts.forEach((product, index) => {
                        grid.appendChild(product);
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
                    case 'all':
                        return true;
                    case 'discounted':
                        return discount > 0;
                    case 'no-discount':
                        return discount === 0;
                    default:
                        return true;
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
                        case 'price-low':
                            return priceA - priceB;
                        case 'price-high':
                            return priceB - priceA;
                        case 'discount-high':
                            return discountB - discountA;
                        case 'name':
                            return nameA.localeCompare(nameB);
                        default:
                            return 0;
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
                button.disabled = true;
                button.innerHTML = '<div class="loading-spinner"></div> Adding...';

                try {
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
                const requiredFields = [{
                        name: 'selected_color_id',
                        default: '1'
                    },
                    {
                        name: 'selected_color_name',
                        default: 'Default'
                    },
                    {
                        name: 'color_price',
                        default: '0'
                    }
                ];

                requiredFields.forEach(field => {
                    if (!formData.get(field.name) || formData.get(field.name) === '') {
                        formData.set(field.name, field.default);
                    }
                });
            }

            showSuccessState(button, originalContent) {
                button.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Added!`;
                button.className = button.className.replace(/bg-gradient-to-r.+?hover:to-orange-800/, 'bg-green-500 hover:bg-green-600');

                setTimeout(() => {
                    this.resetButton(button, originalContent);
                }, 2000);
            }

            resetButton(button, originalContent) {
                button.innerHTML = originalContent;
                button.className = button.className.replace(/bg-green-\d+\s*hover:bg-green-\d+/, 'bg-gradient-to-r from-orange-400 to-orange-600 hover:from-orange-600 hover:to-orange-800');
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

        // Global functions for gallery
        function changeImage(button, direction) {
            ImageGallery.changeImage(button, direction);
        }

        function goToImage(dot, index) {
            ImageGallery.goToImage(dot, index);
        }

        // Initialize everything when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 1000,
                    once: true,
                    offset: 100,
                    easing: 'ease-out-cubic'
                });
            }

            new ProductFilter();
            new CartManager();

            const buttons = document.querySelectorAll('.btn-buy, .btn-preorder');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });

                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

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

            document.querySelectorAll('.product-item').forEach(card => {
                observer.observe(card);
            });

            document.addEventListener('keydown', function(e) {
                const focusedElement = document.activeElement;
                if (focusedElement && focusedElement.closest('.image-gallery')) {
                    const gallery = focusedElement.closest('.image-gallery');

                    switch (e.key) {
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

            console.log('Product display page initialized successfully');
        });
    </script>
</body>

</html>