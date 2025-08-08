<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token (email or mobile-based or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        // 🔐 Store essential user session info
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        // 👤 Check if it's a Google account (optional)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }

    $stmt->close();
}

// ✅ Final session check
if (!isset($_SESSION['user_id'])) {
    // Not logged in — redirect to login or Google auth
    header('Location: ../google-callback.php'); // You may replace with `index.php` if default login
    exit;
}

$material_querys = "
    SELECT 
        pv.*, pv.origin,
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
    JOIN product_types pt ON pv.type_id = pt.id
    JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    ORDER BY pv.discount DESC, pv.percent ASC, p.id, pc.id
";
$material_results = mysqli_query($conn, $material_querys);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Product Display</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- AOS Animation CSS -->
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
                    }
                }
            }
        }
    </script>

    <style>
        .bubble-bounce {
            position: absolute;
            display: inline-block;
            opacity: 0.15;
            border-radius: 50%;
            animation: bubble-bounce 2.2s cubic-bezier(.68, -0.55, .27, 1.55) infinite;
            box-shadow: 0 8px 32px 0 rgba(251, 146, 60, 0.25);
        }

        @keyframes bubble-bounce {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-30px) scale(1.08);
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            transition: all 0.3s ease;
        }

        .btn-preorder:hover {
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
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
            color: #dc2626;
        }

        .origin-local {
            background: linear-gradient(135deg, #bfdbfe 0%, #93c5fd 100%);
            color: #2563eb;
        }

        /* Range Slider Styles */
        .range-slider {
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            border-radius: 3px;
            background: linear-gradient(to right, #f97316 0%, #ea580c 100%);
            outline: none;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #f97316;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #f97316;
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
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            transform: scale(1.05);
        }

        .filter-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        .hidden {
            display: none;
        }

        /* Image Gallery Styles */
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
            background: #f97316;
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
    </style>
</head>

<body class="bg-gray-50 font-mont">
    <?php include '../navbar/top.php'; ?>
    <!-- Top Sales Section -->
    <section class="px-4 py-16">
        <!-- Header -->
        <div class="text-center mb-16 relative">
            <!-- Animated Background Bubbles -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
                <span class="bubble-bounce" style="left: 15%; top: 25%; width: 100px; height: 100px; background: radial-gradient(circle at 40% 40%, #fbbf24 60%, #f59e42 100%); animation-delay: 0s;"></span>
                <span class="bubble-bounce" style="left: 65%; top: 45%; width: 70px; height: 70px; background: radial-gradient(circle at 60% 60%, #f97316 60%, #fbbf24 100%); animation-delay: 0.7s;"></span>
                <span class="bubble-bounce" style="left: 35%; top: 65%; width: 50px; height: 50px; background: radial-gradient(circle at 50% 50%, #f59e42 60%, #fbbf24 100%); animation-delay: 1.2s;"></span>
                <span class="bubble-bounce" style="left: 75%; top: 15%; width: 80px; height: 80px; background: radial-gradient(circle at 60% 60%, #fbbf24 60%, #f59e42 100%); animation-delay: 1.7s;"></span>
            </div>
            
           <div class="relative z-10 text-center">
  <h2 class="text-5xl md:text-6xl font-black text-transparent bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text mb-4 tracking-tight" data-aos="fade-up">
    All Products
  </h2>

  <div class="mx-auto w-40 h-1.5 bg-gradient-to-r from-orange-500 via-red-500 to-transparent rounded-full" data-aos="fade-up" data-aos-delay="200"></div>

  <p class="mt-6 text-lg text-gray-700 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="400">
    Discover a wide selection of high-quality products curated to match your lifestyle.
  </p>

  <p class="mt-2 text-md text-gray-500 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="600">
    From everyday essentials to luxury picks, explore our ever-growing collection tailored for you.
  </p>

  <p class="mt-2 text-md text-gray-500 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="800">
    New items added weekly. Shop now and experience convenience like never before!
  </p>
</div>

        </div>

        <!-- Filters Section -->
        <div class="max-w-full mx-auto mb-12" data-aos="fade-up" data-aos-delay="300">
            <div class="filter-card rounded-2xl shadow-lg p-6 mb-8">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Search Filter -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Products</label>
                        <div class="relative">
                            <input type="text" id="searchFilter" placeholder="Search by name..." 
                                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                            <svg class="absolute right-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Origin Filter -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Origin</label>
                        <div class="flex gap-2">
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium active" data-origin="all">
                                All
                            </button>
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium" data-origin="local">
                                Local
                            </button>
                            <button class="filter-button origin-filter px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium" data-origin="international">
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
                                    <input type="range" id="minPrice" class="range-slider w-full" min="0" max="10000" value="0" step="100">
                                    <div class="text-xs text-gray-500 mt-1">Min: ₱<span id="minPriceValue">0</span></div>
                                </div>
                                <div class="flex-1">
                                    <input type="range" id="maxPrice" class="range-slider w-full" min="0" max="10000" value="10000" step="100">
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
                        <div class="flex gap-2 flex-wrap">
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium active" data-discount="all">
                                All
                            </button>
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium" data-discount="discounted">
                                On Sale
                            </button>
                            <button class="filter-button discount-filter px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-medium" data-discount="no-discount">
                                Regular Price
                            </button>
                        </div>
                    </div>

                    <!-- Sort Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
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
                        <div class="text-sm text-gray-600">
                            Showing <span id="resultCount">0</span> products
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="max-w-full mx-auto">
            <div id="productsGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-6" data-aos="fade-up" data-aos-delay="400">
                
                <?php while ($row = mysqli_fetch_assoc($material_results)) :
                    $base = (float)$row['price'];
                    $percent = (float)($row['percent'] ?? 0);
                    $discount = (float)($row['discount'] ?? 0);
                    $priceWithMarkup = $base + ($base * $percent / 100);
                    $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
                    
                    // Process images - combine main_image, sub_images, and type_image
                    $allImages = array();
                    
                    // Add main image first
                    if (!empty($row['main_image'])) {
                        $allImages[] = '../../' . $row['main_image'];
                    }
                    
                    // Add sub images
                    if (!empty($row['sub_images'])) {
                        $subImages = json_decode($row['sub_images'], true);
                        if (is_array($subImages)) {
                            foreach ($subImages as $subImg) {
                                if (!empty($subImg)) {
                                    // Clean up the path - remove ../ and add proper path
                                    $cleanPath = str_replace('../', '', $subImg);
                                    $allImages[] = '../../' . $cleanPath;
                                }
                            }
                        }
                    }
                    
                    // Add type image as fallback
                    if (!empty($row['type_image'])) {
                        $allImages[] = '../../' . $row['type_image'];
                    }
                    
                    // Remove duplicates and ensure we have at least one image
                    $allImages = array_unique($allImages);
                    if (empty($allImages)) {
                        $allImages[] = '../img/placeholder.jpg'; // Add a placeholder if no images
                    }
                ?>
                    <div class="product-item product-card rounded-2xl shadow-lg p-6 group flex flex-col justify-between h-[520px] text-center relative overflow-hidden transition-all duration-300"
                         data-name="<?= strtolower(htmlspecialchars($row['namevariant'])) ?>"
                         data-price="<?= $finalPrice ?>"
                         data-original-price="<?= $priceWithMarkup ?>"
                         data-discount="<?= $discount ?>"
                         data-origin="<?= strtolower($row['origin'] ?? 'local') ?>">
                        
                        <!-- Discount Badge (only show if there's a discount) -->
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
                                <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 right-1 w-9 h-9 object-contain" />
                            </div>
                        </div>

                        <!-- Product Image Gallery -->
                        <div class="aspect-square w-full bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-200 rounded-xl overflow-hidden mb-4 group-hover:shadow-inner transition-all duration-300">
                            <div class="image-gallery w-full h-full relative">
                                <div class="gallery-container h-full" data-current="0">
                                    <?php foreach ($allImages as $index => $image): ?>
                                        <img src="<?= htmlspecialchars($image) ?>" 
                                             alt="<?= htmlspecialchars($row['namevariant']) ?>" 
                                             class="gallery-image object-contain transition-transform duration-300 group-hover:scale-105"
                                             style="<?= $index === 0 ? '' : 'display: none;' ?>" />
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($allImages) > 1): ?>
                                    <!-- Navigation Arrows -->
                                    <button class="gallery-arrow prev" onclick="changeImage(this, -1)">‹</button>
                                    <button class="gallery-arrow next" onclick="changeImage(this, 1)">›</button>
                                    
                                    <!-- Navigation Dots -->
                                    <div class="gallery-nav">
                                        <?php foreach ($allImages as $index => $image): ?>
                                            <div class="gallery-dot <?= $index === 0 ? 'active' : '' ?>" 
                                                 onclick="goToImage(this, <?= $index ?>)"></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="mt-auto">
                            <h3 class="text-lg font-bold underline underline-offset-4 text-orange-600 leading-snug break-words mb-3 group-hover:text-orange-700 transition-colors">
                                <?= htmlspecialchars($row['namevariant']) ?>
                            </h3>
                            <ul class="text-sm text-gray-600 text-center space-y-2 mb-4">
                                <li><span class="font-semibold text-gray-800">Color:</span> <?= htmlspecialchars($row['color'] ?? 'N/A') ?></li>
                                <li><span class="font-semibold text-gray-800">Size:</span> <?= htmlspecialchars($row['size'] ?? 'N/A') ?></li>
                            </ul>

                            <!-- Pricing -->
                            <div class="mb-4">
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through mb-1">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                    <p class="text-xl price-gradient font-mont mb-2">
                                        ₱<?= number_format($finalPrice, 2) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-xl price-gradient font-mont mb-2">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                <?php endif; ?>
                                
                                <!-- Origin Badge -->
                                <?php if (!empty($row['origin'])): ?>
                                <span class="origin-badge <?= $row['origin'] === 'international' ? 'origin-international' : 'origin-local' ?>">
                                    <?= ucfirst($row['origin']) ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Buttons -->
                            <div class="flex justify-center gap-3 mt-4 flex-wrap">
                                <!-- Buy Button -->
                                <form action="product_view" method="GET">
                                    <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                    <button type="submit" class="btn-buy text-white text-sm px-6 py-2.5 rounded-full flex items-center gap-2 shadow-lg font-semibold">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                        </svg>
                                        Buy
                                    </button>
                                </form>

                                <!-- Pre-Order Button -->
                             <!-- Fixed Pre-Order Button Form -->
<form class="productForm" data-product-id="<?= (int)$row['product_id'] ?>">
    <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
    <input type="hidden" name="selected_type" value="<?= htmlspecialchars($row['type_name'] ?? '') ?>">
    
    <!-- ✅ FIXED: Include size in the variant name to make it unique -->
    <input type="hidden" name="selected_variant" value="<?= htmlspecialchars($row['namevariant'] ?? '') ?> - <?= htmlspecialchars($row['size'] ?? '') ?>">
    
    <!-- ✅ FIXED: Use the actual variant ID from product_variants table -->
    <input type="hidden" name="variant_id" value="<?= (int)($row['id'] ?? 0) ?>">
    
    <!-- ✅ FIXED: Include size as separate field -->
    <input type="hidden" name="selected_size" value="<?= htmlspecialchars($row['size'] ?? '') ?>">
    
    <!-- Color information -->
    <input type="hidden" name="selected_color_id" value="<?= (int)($row['color_id'] ?? 0) ?>">
    <input type="hidden" name="selected_color_name" value="<?= htmlspecialchars($row['color'] ?? '') ?>">
    <input type="hidden" name="color_price" value="<?= floatval($row['color_price'] ?? 0) ?>">
    
    <!-- Price information -->
    <input type="hidden" name="variant_price" value="<?= floatval($row['price'] ?? 0) ?>">
    <input type="hidden" name="total_price" value="<?= floatval($finalPrice) ?>">
    
    <!-- Additional fields for proper cart identification -->
    <input type="hidden" name="discount" value="<?= floatval($row['discount'] ?? 0) ?>">
    <input type="hidden" name="percent" value="<?= floatval($row['percent'] ?? 0) ?>">
    <input type="hidden" name="origin" value="<?= htmlspecialchars($row['origin'] ?? 'local') ?>">
    
    <!-- Return URL -->
    <input type="hidden" name="return_url" value="index">

   <button type="submit"
    class="btn-preorder text-white text-sm px-5 py-2.5 rounded-full flex items-center gap-2 shadow-lg font-semibold
    ring-2 ring-orange-500 ring-offset-1">
    <img src="../img/ecommerce.png" alt="Cart" class="w-4 h-4" />
    Pre-Order
</button>

</form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- No Results Message -->
            <div id="noResults" class="hidden text-center py-16">
                <div class="text-6xl text-gray-300 mb-4">🔍</div>
                <h3 class="text-2xl font-bold text-gray-600 mb-2">No products found</h3>
                <p class="text-gray-500">Try adjusting your filters or search terms</p>
            </div>
        </div>
    </section>

    <!-- AOS Animation Script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <script>
        // Image Gallery Functions
        function changeImage(button, direction) {
            const gallery = button.closest('.image-gallery');
            const container = gallery.querySelector('.gallery-container');
            const images = gallery.querySelectorAll('.gallery-image');
            const dots = gallery.querySelectorAll('.gallery-dot');
            
            let current = parseInt(container.dataset.current);
            let newIndex = current + direction;
            
            if (newIndex < 0) newIndex = images.length - 1;
            if (newIndex >= images.length) newIndex = 0;
            
            showImage(gallery, newIndex);
        }

        function goToImage(dot, index) {
            const gallery = dot.closest('.image-gallery');
            showImage(gallery, index);
        }

        function showImage(gallery, index) {
            const container = gallery.querySelector('.gallery-container');
            const images = gallery.querySelectorAll('.gallery-image');
            const dots = gallery.querySelectorAll('.gallery-dot');
            
            // Hide all images
            images.forEach(img => img.style.display = 'none');
            
            // Show selected image
            if (images[index]) {
                images[index].style.display = 'block';
            }
            
            // Update dots
            dots.forEach(dot => dot.classList.remove('active'));
            if (dots[index]) {
                dots[index].classList.add('active');
            }
            
            // Update current index
            container.dataset.current = index;
        }

        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Filter and Search Functionality
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

                this.init();
            }

            init() {
                // Search filter
                this.searchInput.addEventListener('input', (e) => {
                    this.currentFilters.search = e.target.value.toLowerCase();
                    this.applyFilters();
                });

                // Price range sliders
                this.minPriceSlider.addEventListener('input', (e) => {
                    this.currentFilters.minPrice = parseInt(e.target.value);
                    this.minPriceValue.textContent = new Intl.NumberFormat().format(this.currentFilters.minPrice);
                    this.applyFilters();
                });

                this.maxPriceSlider.addEventListener('input', (e) => {
                    this.currentFilters.maxPrice = parseInt(e.target.value);
                    this.maxPriceValue.textContent = new Intl.NumberFormat().format(this.currentFilters.maxPrice);
                    this.applyFilters();
                });

                // Sort filter
                this.sortSelect.addEventListener('change', (e) => {
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

                // Initial filter application
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
                });

                // Show filtered products
                if (visibleProducts.length > 0) {
                    visibleProducts.forEach((product, index) => {
                        product.classList.remove('hidden');
                        // Add staggered animation
                        product.style.animationDelay = `${index * 0.1}s`;
                    });
                    this.noResults.classList.add('hidden');
                    this.productsGrid.classList.remove('hidden');
                } else {
                    this.noResults.classList.remove('hidden');
                    this.productsGrid.classList.add('hidden');
                }

                // Update result count
                this.resultCount.textContent = visibleProducts.length;
            }

            matchesSearch(product) {
                const name = product.dataset.name;
                return name.includes(this.currentFilters.search);
            }

            matchesOrigin(product) {
                if (this.currentFilters.origin === 'all') return true;
                return product.dataset.origin === this.currentFilters.origin;
            }

            matchesDiscount(product) {
                const discount = parseFloat(product.dataset.discount);
                
                if (this.currentFilters.discount === 'all') return true;
                if (this.currentFilters.discount === 'discounted') return discount > 0;
                if (this.currentFilters.discount === 'no-discount') return discount === 0;
                
                return true;
            }

            matchesPriceRange(product) {
                const price = parseFloat(product.dataset.price);
                return price >= this.currentFilters.minPrice && price <= this.currentFilters.maxPrice;
            }

            sortProducts(products) {
                return products.sort((a, b) => {
                    const priceA = parseFloat(a.dataset.price);
                    const priceB = parseFloat(b.dataset.price);
                    const discountA = parseFloat(a.dataset.discount);
                    const discountB = parseFloat(b.dataset.discount);
                    const nameA = a.dataset.name;
                    const nameB = b.dataset.name;

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

        // Initialize filters when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            new ProductFilter();

         // Handle form submissions for index.php product forms
            document.querySelectorAll('.productForm').forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const button = this.querySelector('button[type="submit"]');
                    const originalText = button.innerHTML;

                    // Show loading state
                    button.disabled = true;
                    button.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span> Adding...';

                    try {
                        // Ensure required fields are set
                        if (!formData.get('selected_color_id') || formData.get('selected_color_id') === '') {
                            formData.set('selected_color_id', '1');
                        }
                        if (!formData.get('selected_color_name') || formData.get('selected_color_name') === '') {
                            formData.set('selected_color_name', 'Default');
                        }
                        if (!formData.get('color_price')) {
                            formData.set('color_price', '0');
                        }

                        const response = await fetch('../cart/add_to_cart', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            showNotification(data.message || 'Product added to cart!', 'success');
                            updateCartCount(data.cart_count);

                            // Success feedback
                            button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Added!';
                            button.className = button.className.replace('btn-preorder', 'bg-green-500');

                            // Reset after 2 seconds
                            setTimeout(() => {
                                button.innerHTML = originalText;
                                button.className = button.className.replace('bg-green-500', 'btn-preorder');
                                button.disabled = false;
                            }, 2000);
                        } else {
                            throw new Error(data.message || 'Add to cart failed.');
                        }
                    } catch (error) {
                        showNotification(error.message, 'error');
                        console.error('Add to cart error:', error);

                        // Reset button
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                });
            });

            // Add smooth scrolling for any anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
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

            // Add notification system for cart updates
            function showNotification(message, type = 'success') {
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transform translate-x-full transition-transform duration-300 ${
                    type === 'success' ? 'bg-green-500' : 'bg-red-500'
                }`;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                // Animate in
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);
                
                // Animate out and remove
                setTimeout(() => {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (document.body.contains(notification)) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }, 3000);
            }

            // Function to update cart count in header/navbar
            function updateCartCount(count) {
                const cartCountElements = document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]');
                cartCountElements.forEach(element => {
                    if (element) {
                        element.textContent = count;
                        // Add bounce animation
                        element.classList.add('animate-bounce');
                        setTimeout(() => {
                            element.classList.remove('animate-bounce');
                        }, 1000);
                    }
                });
            }
            const productImages = document.querySelectorAll('.product-item img');
            productImages.forEach(img => {
                img.addEventListener('load', function() {
                    this.style.opacity = '1';
                });
                
                img.addEventListener('error', function() {
                    this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgNzBMMTMwIDEwMEgxMTBWMTMwSDkwVjEwMEg3MEwxMDAgNzBaIiBmaWxsPSIjOUI5QjlCIi8+Cjx0ZXh0IHg9IjEwMCIgeT0iMTYwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjOUI5QjlCIiBmb250LXNpemU9IjEyIiBmb250LWZhbWlseT0iQXJpYWwiPk5vIEltYWdlPC90ZXh0Pgo8L3N2Zz4K';
                });
            });

            // Add enhanced button interactions
            const buttons = document.querySelectorAll('.btn-buy, .btn-preorder');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
                
                button.addEventListener('mousedown', function() {
                    this.style.transform = 'translateY(0) scale(0.98)';
                });
                
                button.addEventListener('mouseup', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });
            });

            // Add keyboard navigation support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Clear all filters
                    document.getElementById('searchFilter').value = '';
                    document.getElementById('minPrice').value = '0';
                    document.getElementById('maxPrice').value = '10000';
                    document.getElementById('sortFilter').value = 'default';
                    
                    // Reset filter buttons
                    document.querySelectorAll('.filter-button.active').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    document.querySelector('[data-origin="all"]').classList.add('active');
                    document.querySelector('[data-discount="all"]').classList.add('active');
                    
                    // Trigger filter update
                    new ProductFilter();
                }
            });

            // Add intersection observer for lazy loading and animations
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

            // Add responsive grid adjustment
            function adjustGrid() {
                const grid = document.getElementById('productsGrid');
                const width = window.innerWidth;
                
                if (width < 640) {
                    grid.className = grid.className.replace(/grid-cols-\w+/g, 'grid-cols-1');
                } else if (width < 768) {
                    grid.className = grid.className.replace(/grid-cols-\w+/g, 'grid-cols-2');
                } else if (width < 1024) {
                    grid.className = grid.className.replace(/grid-cols-\w+/g, 'grid-cols-3');
                } else if (width < 1280) {
                    grid.className = grid.className.replace(/grid-cols-\w+/g, 'grid-cols-4');
                } else {
                    grid.className = grid.className.replace(/grid-cols-\w+/g, 'grid-cols-5');
                }
            }

            // Initial grid adjustment and on resize
            adjustGrid();
            window.addEventListener('resize', adjustGrid);

            // Add custom CSS for fade in animation
            const style = document.createElement('style');
            style.textContent = `
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
                .animate-fadeInUp {
                    animation: fadeInUp 0.6s ease-out forwards;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>