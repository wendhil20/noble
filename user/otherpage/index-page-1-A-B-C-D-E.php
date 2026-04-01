<?php
//index-page-1-A-B-C-D-E.php
ob_start();
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Track referral visits FIRST (before anything else)
require_once '../../includes/referral_tracker.php';
trackReferralVisit($conn);

// ✅ If user is already logged in, assign referral code if they don't have one
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    assignReferralToUser($conn, $_SESSION['user_id']);
}

// Include the handler
include 'index-recent_views_handler-page-14.php';

// Fetch recent views
$recent_views = getRecentViews($conn, 10);
$recent_count = mysqli_num_rows($recent_views);

// 🆕 Fetch recommended products (with view counts)
$recommended_products = getRecommendedProducts($conn, 10);
$recommended_count = mysqli_num_rows($recommended_products);

// Check login notification                     
if (isset($_SESSION['login_needed'])) {
    $notification_message = $_SESSION['login_needed'];
    unset($_SESSION['login_needed']);
}

if (isset($_POST['acceptCookies'])) {
    // ✅ Set cookie valid for 1 year
    setcookie("cookiesAccepted", "true", time() + (365 * 24 * 60 * 60), "/");
    // Refresh page para mawala yung banner agad
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$cookieAccepted = isset($_COOKIE['cookiesAccepted']) && $_COOKIE['cookiesAccepted'] === 'true';



// 3. Discount 10% materials - WITH VIEW COUNT, RATING & SOLD COUNT
$material_querys = "
    SELECT 
        pv.*,
        pv.origin,
        pt.type_name,
        pt.type_image,
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.description,
        p.descrip6,
        p.descrip7,
        p.view_count,
        p.unique_view_count,
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold,
        ANY_VALUE(pc.id) AS color_id,
        ANY_VALUE(pc.color_name) AS color,
        ANY_VALUE(pc.color_code) AS color_code,
        ANY_VALUE(pc.price) AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    LEFT JOIN product_colors pc 
        ON pc.product_id = p.id
       AND pc.id = (
           SELECT MIN(pc2.id) 
           FROM product_colors pc2 
           WHERE pc2.product_id = p.id
       )
    WHERE pv.discount > 0
    AND p.is_archived = 0
    GROUP BY pv.id
    ORDER BY p.view_count DESC, RAND()
    LIMIT 10
";
$material_results = mysqli_query($conn, $material_querys);

// Get the maximum discount from the results
$maxDiscount = 0;
if (mysqli_num_rows($material_results) > 0) {
    mysqli_data_seek($material_results, 0);
    while ($row = mysqli_fetch_assoc($material_results)) {
        $discount = (float) ($row['discount'] ?? 0);
        if ($discount > $maxDiscount) {
            $maxDiscount = $discount;
        }
    }
    // Reset pointer back to beginning for the swiper loop
    mysqli_data_seek($material_results, 0);
}


// 5. Fetch "new" status product variants - WITH VIEW COUNT, RATING & SOLD COUNT
$material_querystwo = "
    SELECT 
        pv.*,
        pv.origin,
        pt.type_name,
        pt.type_image,
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.description,
        p.descrip6,
        p.descrip7,
        p.view_count,
        p.unique_view_count,
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold,
       ANY_VALUE(pc.id) AS color_id,
        ANY_VALUE(pc.color_name) AS color,
        ANY_VALUE(pc.color_code) AS color_code,
        ANY_VALUE(pc.price) AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    WHERE pv.status = 'new'
    AND p.is_archived = 0
    GROUP BY pv.id
    ORDER BY RAND()
    LIMIT 10
";
$material_resultstwo = mysqli_query($conn, $material_querystwo);

// Fetch active banners from discount_images table
$banners_query = "SELECT di.*, c.name as category_name FROM discount_images di LEFT JOIN categories c ON di.category_id = c.id WHERE di.is_active = 1 AND di.category_id IS NOT NULL ORDER BY di.uploaded_at DESC LIMIT 10";
$banners_result = $conn->query($banners_query);
$banners = [];
while ($row = $banners_result->fetch_assoc()) {
    $banners[] = $row;
}

// Fetch categories from database
$query = "SELECT id, name, image_pathtwo FROM categories ORDER BY id";
$resultdepartment = mysqli_query($conn, $query);
$categories = mysqli_fetch_all($resultdepartment, MYSQLI_ASSOC);

$bestsellerItems = $conn->query("SELECT * FROM bestseller ORDER BY id DESC");
$bestsellerData = $bestsellerItems->fetch_all(MYSQLI_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Noble Home - Modern Furnishing Supplies</title>
    <link href="../css/promotionslide.css" rel="stylesheet">
    <link href="../css/bannerPromo.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=aspect-ratio"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.19/bundled/lenis.min.js"></script>
    <script>
        // Function to hide the notification after 5 seconds
        setTimeout(function () {
            const notification = document.getElementById('loginNotification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 5000); // 5000ms = 5 seconds


    </script>
    <style>
        footer * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .swiper-slide,
        .swiper-slide-active {
            opacity: 1 !important
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) rotate(0)
            }

            50% {
                transform: translateY(-20px) rotate(180deg)
            }
        }

        .gradient-text {
            background: linear-gradient(135deg, #fff 0, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text
        }

        .btn-glow {
            box-shadow: 0 0 30px rgba(251, 146, 60, .3);
            transition: .3s
        }

        .btn-glow:hover {
            box-shadow: 0 0 40px rgba(251, 146, 60, .5);
            transform: translateY(-2px)
        }

        .text-shadow {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, .5)
        }

        .backdrop-blur-sm {
            backdrop-filter: blur(4px)
        }

        [x-cloak] {
            display: none !important
        }

        .swiper-slide {
            transition: opacity .5s ease-in-out
        }

        .swiper-slide:not(.swiper-slide-active) {
            opacity: .3
        }

        .swiper-button-next,
        .swiper-button-prev {
            width: 2rem;
            height: 2rem;
            background-color: rgba(255, 255, 255, .8);
            border-radius: 9999px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .2)
        }

        .swiper-button-next::after,
        .swiper-button-prev::after {
            font-size: 12px !important;
            color: #111
        }

        .carousel-item {
            transition: .6s cubic-bezier(.4, 0, .2, 1)
        }

        .category-swiper .swiper-pagination,
        .contact-swiper .swiper-pagination {
            position: relative !important;
            bottom: auto !important;
            margin-top: 2rem !important
        }

        .swiper-pagination-bullet {
            width: 30px !important;
            height: 4px !important;
            border-radius: 2px !important;
            background: rgba(255, 255, 255, 0.5) !important;
            opacity: 1 !important;
        }


        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(.95)
            }

            to {
                opacity: 1;
                transform: scale(1)
            }
        }

        .modal-enter {
            animation: .2s ease-out fadeIn
        }

        .swiper-pagination-bullet {
            background: #fb923c;
            opacity: .5
        }

        .swiper-pagination-bullet-active {
            background: #fb923c;
            opacity: 1
        }


        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        .animate-shimmer {
            animation: shimmer 1.5s ease-in-out infinite;
        }



        .banner-image {
            transition: opacity 0.3s ease-in-out;
        }

        /* Main slider responsive sizing */
        .mySwiper {
            min-height: 150px;
        }

        @media (min-width: 640px) {
            .mySwiper {
                min-height: 250px;
            }
        }

        @media (min-width: 1024px) {
            .mySwiper {
                min-height: 350px;
            }
        }
    </style>
</head>

<body class="">

    <?php include '../navbar/top.php'; ?>
    <?php include 'push-notification.php'; ?>

    <?php include 'index-flash_notification-D.php'; ?>

    <?php if (isset($_SESSION['toast'])): ?>
        <div id="toast"
            class="fixed top-5 right-5 bg-<?= $_SESSION['toast']['type'] === 'error' ? 'red' : 'green' ?>-500 text-white text-lg px-4 py-2 rounded shadow-lg z-50">
            <?= htmlspecialchars($_SESSION['toast']['message']) ?>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('toast');
                if (toast) toast.style.display = 'none';
            }, 4000);
        </script>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

    <?php if (isset($notification_message)): ?>
        <div id="loginNotification" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50">
            <div class="bg-red-200 text-yellow-800  p-4 rounded shadow-lg max-w-md w-full text-center">
                <p><?= htmlspecialchars($notification_message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['login_success'])): ?>
        <div id="login-alert"
            class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50
      bg-green-100 border border-green-300 text-green-800 px-6 py-3 rounded shadow-lg transition-opacity duration-1000">
            <?= $_SESSION['login_success'] ?>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.getElementById('login-alert');
                if (alert) {
                    alert.classList.add('opacity-0');
                    setTimeout(() => alert.remove(), 1000); // wait for fade-out
                }
            }, 3000);
        </script>
        <?php unset($_SESSION['login_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['login_error'])): ?>
        <div id="error-alert" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50
      bg-red-100 text-red-800 px-6 py-3 rounded shadow-lg transition-opacity duration-1000">
            <?= $_SESSION['login_error'] ?>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.getElementById('error-alert');
                if (alert) {
                    alert.classList.add('opacity-0');
                    setTimeout(() => alert.remove(), 1000);
                }
            }, 3000);
        </script>
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

    <div class="w-full px-0 mx-0 overflow-hidden">
        <div class="relative overflow-hidden"
            style="margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); width: 100vw;">
            <div class="mySwiper h-full p-4">
                <div class="swiper-wrapper">
                    <?php if (!empty($banners)): ?>
                        <?php foreach ($banners as $idx => $banner): ?>
                            <a href="../otherpage/index-subcategory_grid_page-14.php?category_name=<?= urlencode(strtolower($banner['category_name'])) ?>"
                                class="swiper-slide block cursor-pointer hover:opacity-90 transition-opacity group">
                                <div class="relative w-full overflow-hidden flex items-center justify-center bg-gray-100">
                                    <div
                                        class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer">
                                    </div>
                                    <img src="../../uploads/<?= basename($banner['filename']) ?>"
                                        alt="<?= htmlspecialchars($banner['category_name'] ?? 'Banner') ?>"
                                        class="banner-image w-full h-full object-cover opacity-0 z-10 rounded-lg"
                                        onerror="this.src='../../uploads/placeholder.jpg'; this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                        onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide bg-gray-800 flex items-center justify-center">
                            <p class="text-gray-400">No banners available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <section class="py-6 md:py-8 bg-white">
        <!-- Header -->
        <div class="mb-6 md:mb-8 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center gap-4">
                <div class="w-48 h-0.5" style="background: linear-gradient(to left, #eab308, transparent);"></div>
                <h2 class="text-lg md:text-2xl lg:text-3xl font-semibold whitespace-nowrap">
                    Shop by Department
                </h2>
                <div class="w-48 h-0.5" style="background: linear-gradient(to right, #eab308, transparent);"></div>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="px-2 sm:px-4 lg:px-8 max-w-8xl mx-auto">
            <div class="flex flex-wrap justify-center gap-4 md:gap-6 lg:gap-8">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryName = $category['name'];
                    $imagePath = '../../uploads/categories/' . $category['image_pathtwo'];

                    if ($categoryName === 'BathroomFixtures') {
                        $displayName = 'Bathroom';
                    } elseif ($categoryName === 'KitchenFixtures') {
                        $displayName = 'Kitchen';
                    } elseif ($categoryName === 'lightingfixture') {
                        $displayName = 'Lighting';
                    } elseif ($categoryName === 'Bedfurniture') {
                        $displayName = 'Bedroom';
                    } elseif ($categoryName === 'buildingmaterials') {
                        $displayName = 'Building Materials';
                    } elseif ($categoryName === 'AACBlock') {
                        $displayName = 'AAC Blocks';
                    } else {
                        $displayName = ucfirst($categoryName);
                    }
                    ?>
                    <a href="index-subcategory_grid_page-14.php?category_name=<?php echo urlencode($categoryName); ?>"
                        class="group flex flex-col items-center gap-2 w-16 sm:w-20 md:w-24">

                        <!-- Circle Icon -->
                        <div class="w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 rounded-full border-2 border-yellow-400 flex items-center justify-center overflow-hidden group-hover:border-yellow-500 group-hover:shadow-md transition-all duration-300"
                            style="border-color: #eab308;">
                            <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                alt="<?php echo htmlspecialchars($displayName); ?>"
                                class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 object-contain group-hover:scale-110 transition-transform duration-300"
                                loading="lazy">
                        </div>

                        <!-- Label -->
                        <p class="text-center text-xs font-semibold uppercase leading-tight">
                            <?php echo htmlspecialchars($displayName); ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php

    function renderProductCard($row, $conn)
    {
        $product_id = (int) $row['id'];

        // ✅ GET SIZE PRICES (final prices from product_variants)
        $minSizePrice = (float) ($row['min_size_price'] ?? 0);
        $maxSizePrice = (float) ($row['max_size_price'] ?? 0);

        // ✅ GET COLOR PRICES (from product_colors)
        $minColorPrice = (float) ($row['min_color_price'] ?? 0);
        $maxColorPrice = (float) ($row['max_color_price'] ?? 0);

        // ✅ GET DISCOUNT (display only)
        $discount = (float) ($row['discount'] ?? 0);

        // 🔥 SIMPLE FORMULA: final_size_price + color_price
        $minFinalPrice = $minSizePrice + $minColorPrice;
        $maxFinalPrice = $maxSizePrice + $maxColorPrice;

        // Format display price
        if ($minFinalPrice != $maxFinalPrice) {
            $displayPrice = '₱' . number_format($minFinalPrice, 2) . ' - ₱' . number_format($maxFinalPrice, 2);
        } else {
            $displayPrice = '₱' . number_format($minFinalPrice, 2);
        }

        // Get rating
        $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ? 
");
        $rating_q->bind_param("i", $product_id);
        $rating_q->execute();
        $rating_result = $rating_q->get_result()->fetch_assoc();
        $avg_rating = $rating_result['avg_rating'] ?? 0;
        $total_raters = $rating_result['total_raters'] ?? 0;
        $rating_q->close();

        // Get sold count
        $sold_q = $conn->prepare("
        SELECT SUM(quantity) as total_sold 
        FROM sold_items 
        WHERE product_id = ? 

    ");
        $sold_q->bind_param("i", $product_id);
        $sold_q->execute();
        $sold_result = $sold_q->get_result()->fetch_assoc();
        $total_sold = (int) ($sold_result['total_sold'] ?? 0);
        $sold_q->close();

        $full = floor($avg_rating);
        $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
        $empty = 5 - $full - $half;

        $firstColor = !empty($row['color']) ? trim($row['color']) : '';
        ?>

        <div class="swiper-slide p-2">
            <a href="index-product_view-page-4-AA?id=<?= $product_id ?>" class="block group rounded-xl p-2 transition-all duration-300 
              hover:shadow-lg hover:-translate-y-1">
                <!-- Image container -->
                <div class="relative rounded-lg overflow-hidden mb-2 w-full" style="aspect-ratio: 1/1;">
                    <?php if (!empty($row['main_image'])): ?>
                        <img src="../../<?= $row['main_image'] ?>" loading="lazy"
                            alt="<?= htmlspecialchars($row['product_name']) ?>" class="w-full h-full object-contain p-1.5" />
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-image text-gray-300 text-3xl"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="px-0.5 flex flex-col h-full">
                    <!-- Product name -->
                    <h3 class="text-[13px] group-hover:text-orange-600 transition-colors mb-1.5 line-clamp-2 font-semibold uppercase">
                        <?= htmlspecialchars($row['product_name']) ?>
                        <?php if (!empty($row['size'])): ?>         <?= htmlspecialchars($row['size']) ?>     <?php endif; ?>
                        <?php if ($firstColor): ?>         <?= htmlspecialchars($firstColor) ?>     <?php endif; ?>
                    </h3>

                    <!-- Rating -->
                    <div class="flex items-center gap-1 mb-1.5">
                        <?php if ($total_raters > 0): ?>
                            <div class="flex text-yellow-400 text-[10px]">
                                <?php for ($i = 0; $i < $full; $i++)
                                    echo '<i class="fas fa-star"></i>'; ?>
                                <?php if ($half)
                                    echo '<i class="fas fa-star-half-alt"></i>'; ?>
                                <?php for ($i = 0; $i < $empty; $i++)
                                    echo '<i class="far fa-star text-gray-300"></i>'; ?>
                            </div>
                            <span class="text-[9px] text-gray-500 font-medium">(<?= $total_raters ?>)</span>
                        <?php else: ?>
                            <div class="flex text-gray-300 text-[10px]">
                                <?php for ($i = 0; $i < 5; $i++)
                                    echo '<i class="far fa-star"></i>'; ?>
                            </div>
                            <span class="text-[9px] text-gray-400">No rating</span>
                        <?php endif; ?>
                    </div>

                    <!-- 🔥 CORRECT PRICE DISPLAY (NO DISCOUNT DEDUCTION) -->
                    <div class="flex items-baseline gap-1 flex-wrap mb-1 mt-auto">
                        <?php if ($discount > 0): ?>
                            <!-- With Discount Badge (display only) -->
                            <p class="text-xs font-semibold">
                                <?= $displayPrice ?>
                            </p>
                            <span
                                class="text-[9px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">-<?= number_format($discount, 0) ?>%</span>
                        <?php else: ?>
                            <!-- No Discount Badge -->
                            <p class="text-xs font-semibold">
                                <?= $displayPrice ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- View count + Sold count -->
                    <?php if (!empty($row['view_count']) || $total_sold > 0): ?>
                        <div class="text-[9px] font-medium" >
                            <?php if (!empty($row['view_count']) && $row['view_count'] > 0): ?>
                                <?= formatViewCount($row['view_count']) ?> viewing
                            <?php endif; ?>

                            <?php if ($total_sold > 0): ?>
                                <?php if (!empty($row['view_count']) && $row['view_count'] > 0): ?> | <?php endif; ?>
                                <?= number_format($total_sold) ?> sold
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php
    }
    ?>

    <section id="recent-recommendations-section" class="px-4 sm:px-5 lg:px-7 py-4 bg-white">
        <?php if ($recommended_count > 0): ?>
            <div class="w-full">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="sm:text-2xl font-semibold">
                        TOP <span class="text-yellow-500 font-semibold">SELLER</span></h2>
                    <div class="flex gap-1.5">
                        <button
                            class="swiper-button-prev-recommended w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                            <i class="fas fa-chevron-left text-gray-600 text-sm"></i>
                        </button>
                        <button
                            class="swiper-button-next-recommended w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                            <i class="fas fa-chevron-right text-gray-600 text-sm"></i>
                        </button>
                    </div>
                </div>
                <div class="swiper mySwiper-recommended w-full">
                    <div class="swiper-wrapper">
                        <?php while ($row = mysqli_fetch_assoc($recommended_products)): ?>
                            <?php renderProductCard($row, $conn); ?>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- POPUP MODAL -->
    <div id="promoPopup" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50">
        <div class="relative max-w-4xl w-full mx-4">
            <!-- Close Button -->
            <button onclick="hidePromoModal()"
                class="absolute -top-4 -right-4 text-white hover:text-red-500 bg-black/70 rounded-full w-10 h-10 flex items-center justify-center text-2xl z-10 transition-colors duration-300">✕</button>

            <!-- Single Image Display -->
            <div class="relative overflow-hidden">
                <a href="index-allproduct-page-3.php?discount=20"
                    class="relative group flex items-center justify-center">
                    <img src="../img/sale/c.png" alt="Special Sale" class="max-w-full max-h-[80vh] object-contain">
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-50 transition-opacity duration-300 flex items-end justify-center pb-8">
                        <span class="text-white text-4xl font-extrabold tracking-wide">Shop Now!</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <script>
        const POPUP_DISPLAY_DURATION = 10000; // 10 seconds
        const POPUP_INTERVAL = 2 * 60 * 60 * 1000; // 2 hours in milliseconds
        let autoCloseTimer = null;

        function displayPromoModal() {
            document.getElementById('promoPopup').classList.remove('hidden');
            const timestamp = Date.now();

            // Store timestamp in localStorage (persists even after browser close)
            try {
                localStorage.setItem('promoModalLastShown', timestamp.toString());
            } catch (e) {
                console.error('Failed to save timestamp:', e);
            }

            // Auto-close after 10 seconds
            autoCloseTimer = setTimeout(() => {
                hidePromoModal();
            }, POPUP_DISPLAY_DURATION);
        }

        function hidePromoModal() {
            document.getElementById('promoPopup').classList.add('hidden');
            if (autoCloseTimer) {
                clearTimeout(autoCloseTimer);
                autoCloseTimer = null;
            }
        }

        function getLastShownTime() {
            try {
                const stored = localStorage.getItem('promoModalLastShown');
                return stored ? parseInt(stored) : null;
            } catch (e) {
                console.error('Failed to retrieve timestamp:', e);
                return null;
            }
        }

        function setupModalSchedule() {
            const currentTimestamp = Date.now();
            const lastShownTime = getLastShownTime();

            console.log('Current time:', new Date(currentTimestamp).toLocaleString());

            if (!lastShownTime) {
                // First visit - show popup immediately
                console.log('First visit - showing popup now');
                displayPromoModal();
            } else {
                const elapsedMilliseconds = currentTimestamp - lastShownTime;
                const elapsedMinutes = Math.floor(elapsedMilliseconds / 60000);
                const elapsedHours = Math.floor(elapsedMinutes / 60);

                console.log(`Last shown: ${new Date(lastShownTime).toLocaleString()}`);
                console.log(`Time elapsed: ${elapsedHours} hours and ${elapsedMinutes % 60} minutes`);

                if (elapsedMilliseconds >= POPUP_INTERVAL) {
                    // 2+ hours passed - show popup now
                    console.log('2+ hours passed - showing popup now');
                    displayPromoModal();
                } else {
                    // Calculate remaining time
                    const remainingMilliseconds = POPUP_INTERVAL - elapsedMilliseconds;
                    const remainingMinutes = Math.floor(remainingMilliseconds / 60000);
                    const remainingHours = Math.floor(remainingMinutes / 60);

                    console.log(`Popup will show in ${remainingHours} hours and ${remainingMinutes % 60} minutes`);

                    // Schedule popup for later
                    setTimeout(() => {
                        displayPromoModal();
                    }, remainingMilliseconds);
                }
            }
        }

        // Initialize popup logic when page loads
        setupModalSchedule();
    </script>



    <section class="px-4 sm:px-6 lg:px-8 ">
        <h2 class="sm:text-2xl font-semibold flex items-center gap-3 p-1">Build Your Future<span
                class="text-yellow-500 font-semibold">With Us</span>
            <span
                style="flex: 1; height: 2px; background: linear-gradient(to right, #ca8a04, transparent); display: inline-block; vertical-align: middle; max-width: 200px;"></span>
        </h2>
        <div class="max-w-full mx-auto">
            <!-- Banner -->
            <a href="https://www.yfk20.com/login"
                class="group block relative overflow-hidden transition-all duration-300 pointer-events-none cursor-not-allowed"
                data-aos="fade-up">
                <!-- Rectangle Container -->
                <div class="relative bg-white overflow-hidden flex items-center justify-center inline-block">
                    <!-- Skeleton Loading -->
                    <div
                        class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer">
                    </div>

                    <img src="../img/kd.png" alt="Promotion Banner"
                        class="banner-image w-full h-auto object-contain opacity-0 transition-opacity duration-300 z-5 rounded-lg"
                        onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';">
                </div>
            </a>
        </div>

        <style>
            @keyframes shimmer {
                0% {
                    background-position: -200% 0;
                }

                100% {
                    background-position: 200% 0;
                }
            }

            .animate-shimmer {
                animation: shimmer 1.5s ease-in-out infinite;
            }

            .banner-image {
                transition: opacity 0.3s ease-in-out;
            }
        </style>
    </section>

    <section class="py-6 bg-white" id="bestseller-section">
        <div class="w-full px-4 md:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3 flex-1">
                    <h2 class="sm:text-2xl font-semibold whitespace-nowrap">
                        BEST <span class="text-yellow-500 font-semibold">SELLER</span>
                    </h2>

                    <!-- Guhit katabi ng SELLER -->
                    <div class="h-0.5 w-20 sm:w-28"
                        style="background: linear-gradient(to right, #b8860b, transparent);"></div>
                </div>

                <!-- Arrows -->
                <div class="flex gap-1.5 ml-4">
                    <button
                        class="bs-prev w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                        <i class="fas fa-chevron-left text-gray-600 text-xs"></i>
                    </button>
                    <button
                        class="bs-next w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                        <i class="fas fa-chevron-right text-gray-600 text-xs"></i>
                    </button>
                </div>
            </div>
            <!-- Swiper -->
            <div class="swiper bestsellerSwiper2">
                <div class="swiper-wrapper p-2">
                    <?php foreach ($bestsellerData as $item): ?>
                        <div class="swiper-slide hover:shadow-lg rounded-lg p-2">
                            <a href="index-bestseller-detail-B.php?slug=<?= htmlspecialchars($item['slug']) ?>"
                                class="block group">

                                <!-- Image -->
                                <div class="relative bg-gray-50 rounded-lg overflow-hidden mb-2 w-full"
                                    style="aspect-ratio: 1/1;">
                                    <img src="<?= htmlspecialchars($item['image'] ?: '../img/promo/default.png') ?>"
                                        alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy"
                                        class="w-full h-full object-contain p-1.5 transition-transform duration-300 group-hover:scale-105" />
                                </div>

                                <!-- Info -->
                                <div class="px-0.5">
                                    <!-- BESTSELLER badge -->
                                    <div class="mb-1.5">
                                        <span
                                            class="text-[9px] font-bold text-white bg-red-600 px-1.5 py-0.5 rounded">BESTSELLER</span>
                                    </div>

                                    <!-- Title -->
                                    <h3 class="text-[13px] font-semibold line-clamp-2 uppercase">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </h3>
                                </div>

                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </section>

    <script>
        new Swiper('.bestsellerSwiper2', {
            slidesPerView: 7,
            spaceBetween: 16,
            navigation: {
                nextEl: '.bs-next',
                prevEl: '.bs-prev',
            },
            breakpoints: {
                320: { slidesPerView: 2, spaceBetween: 10 },
                640: { slidesPerView: 3, spaceBetween: 12 },
                768: { slidesPerView: 4, spaceBetween: 14 },
                1024: { slidesPerView: 5, spaceBetween: 16 },
                1280: { slidesPerView: 6, spaceBetween: 16 },
                1536: { slidesPerView: 7, spaceBetween: 16 },
            }
        });
    </script>

    <section class="px-4 sm:px-5 lg:px-7 py-4 bg-white">
        <?php $row_count = mysqli_num_rows($material_results); ?>
        <?php if ($row_count > 0): ?>
            <div class="w-full">
                <!-- Header - Matching New Arrival Style -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-base sm:text-2xl font-semibold whitespace-nowrap">
                            <span class="text-red-500 font-semibold">SALES</span>
                            <span class="text-gray-500 ml-1">Up to <?= number_format($maxDiscount, 0) ?>% OFF</span>
                        </h2>
                        <div class="h-0.5 w-20 sm:w-28"
                            style="background: linear-gradient(to right, #ef4444, transparent);"></div>
                    </div>
                    <div class="flex gap-1.5">
                        <button
                            class="ts-prev w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                            <i class="fas fa-chevron-left text-gray-600 text-xs"></i>
                        </button>
                        <button
                            class="ts-next w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                            <i class="fas fa-chevron-right text-gray-600 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Swiper - Matching New Arrival Style -->
                <div class="swiper topSalesSwiper w-full">
                    <div class="swiper-wrapper p-2">
                        <?php
                        mysqli_data_seek($material_results, 0);
                        while ($row = mysqli_fetch_assoc($material_results)):
                            $variantPrice = (float) ($row['price'] ?? 0);
                            $colorPrice = (float) ($row['color_price'] ?? 0);
                            $discount = (float) ($row['discount'] ?? 0);
                            $finalPrice = $variantPrice + $colorPrice;
                            $viewCount = (int) ($row['view_count'] ?? 0);
                            $soldCount = (int) ($row['total_sold'] ?? 0);
                            $avgRating = (float) ($row['avg_rating'] ?? 0);
                            $ratingCount = (int) ($row['rating_count'] ?? 0);

                            $full = floor($avgRating);
                            $half = ($avgRating - $full >= 0.5) ? 1 : 0;
                            $empty = 5 - $full - $half;
                            ?>
                            <div
                                class="swiper-slide rounded-lg transition-all duration-300 hover:shadow-lg hover:-translate-y-1 cursor-pointer p-2">
                                <a href="index-product_view-page-4-AA?id=<?= (int) $row['product_id'] ?>" class="block group">

                                    <!-- Image - Square aspect ratio like New Arrival -->
                                    <div class="relative bg-gray-50 rounded-lg overflow-hidden mb-2 w-full"
                                        style="aspect-ratio: 1/1;">
                                        <?php if (!empty($row['type_image'])): ?>
                                            <img src="../../<?= $row['type_image'] ?>" loading="lazy"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                class="w-full h-full object-contain p-1.5" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fas fa-image text-gray-300 text-3xl"></i>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Discount Badge - Top Right -->
                                        <?php if ($discount > 0): ?>
                                            <div
                                                class="absolute top-2 right-2 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded">
                                                -<?= number_format($discount, 0) ?>%
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info - Centered like New Arrival -->
                                    <div class="px-0.5">
                                        <!-- Title -->
                                        <h3 class="text-[13px] font-semibold line-clamp-2 mb-1.5 group-hover:text-orange-600 transition-colors text-left">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>

                                        <!-- Stars - Same style as New Arrival -->
                                        <div class="flex items-base justify-base gap-1 mb-1.5">
                                            <?php if ($ratingCount > 0): ?>
                                                <div class="flex text-yellow-400 text-[10px]">
                                                    <?php for ($i = 0; $i < $full; $i++)
                                                        echo '<i class="fas fa-star"></i>'; ?>
                                                    <?php if ($half)
                                                        echo '<i class="fas fa-star-half-alt"></i>'; ?>
                                                    <?php for ($i = 0; $i < $empty; $i++)
                                                        echo '<i class="far fa-star text-gray-300"></i>'; ?>
                                                </div>
                                                <span class="text-[9px] text-gray-500">(<?= $ratingCount ?>)</span>
                                            <?php else: ?>
                                                <div class="flex text-gray-300 text-[10px]">
                                                    <?php for ($i = 0; $i < 5; $i++)
                                                        echo '<i class="far fa-star"></i>'; ?>
                                                </div>
                                                <span class="text-[9px] text-gray-400">No rating</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Price - Same style as New Arrival -->
                                        <div class="flex items-base justify-base gap-1 flex-wrap mb-1">
                                            <p class="text-xs font-semibold">₱<?= number_format($finalPrice, 2) ?></p>
                                        </div>

                                        <!-- Viewing/Sold Stats -->
                                        <?php if ($viewCount > 0 || $soldCount > 0): ?>
                                            <div class="text-[9px] font-medium text-left">
                                                <?php if ($viewCount > 0): ?>
                                                    <?= number_format($viewCount) ?> viewing
                                                <?php endif; ?>
                                                <?php if ($viewCount > 0 && $soldCount > 0): ?> | <?php endif; ?>
                                                <?php if ($soldCount > 0): ?>
                                                    <?= number_format($soldCount) ?> sold
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <script>
        new Swiper('.topSalesSwiper', {
            slidesPerView: 7,
            spaceBetween: 16,
            navigation: {
                nextEl: '.ts-next',
                prevEl: '.ts-prev',
            },
            breakpoints: {
                320: { slidesPerView: 2, spaceBetween: 10 },
                640: { slidesPerView: 3, spaceBetween: 12 },
                768: { slidesPerView: 4, spaceBetween: 14 },
                1024: { slidesPerView: 5, spaceBetween: 16 },
                1280: { slidesPerView: 6, spaceBetween: 16 },
                1536: { slidesPerView: 7, spaceBetween: 16 },
            }
        });
    </script>

    <?php $row_count = mysqli_num_rows($material_resultstwo); ?>
    <section class="px-4 sm:px-5 lg:px-7 py-4 bg-white">
        <?php if ($row_count > 0): ?>
            <div class="w-full">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-base sm:text-2xl font-semibold whitespace-nowrap">
                            NEW <span class="text-yellow-500 font-semibold">ARRIVAL</span>
                        </h2>
                        <div class="h-0.5 w-20 sm:w-28"
                            style="background: linear-gradient(to right, #b8860b, transparent);"></div>
                    </div>
                    <div class="flex gap-1.5">
                        <button
                            class="na-prev w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                            <i class="fas fa-chevron-left text-gray-600 text-xs"></i>
                        </button>
                        <button
                            class="na-next w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                            <i class="fas fa-chevron-right text-gray-600 text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Swiper -->
                <div class="swiper newArrivalSwiper w-full">
                    <div class="swiper-wrapper p-2">
                        <?php
                        mysqli_data_seek($material_resultstwo, 0);
                        while ($row = mysqli_fetch_assoc($material_resultstwo)):
                            $variantPrice = (float) ($row['price'] ?? 0);
                            $colorPrice = (float) ($row['color_price'] ?? 0);
                            $discount = (float) ($row['discount'] ?? 0);
                            $finalPrice = $variantPrice + $colorPrice;
                            $viewCount = (int) ($row['view_count'] ?? 0);
                            $soldCount = (int) ($row['total_sold'] ?? 0);
                            $avgRating = (float) ($row['avg_rating'] ?? 0);
                            $ratingCount = (int) ($row['rating_count'] ?? 0);

                            $full = floor($avgRating);
                            $half = ($avgRating - $full >= 0.5) ? 1 : 0;
                            $empty = 5 - $full - $half;
                            ?>
                            <div
                                class="swiper-slide rounded-lg transition-all duration-300 hover:shadow-lg hover:-translate-y-1 cursor-pointer p-2">
                                <a href="index-product_view-page-4-AA?id=<?= (int) $row['product_id'] ?>" class="block group">

                                    <!-- Image -->
                                    <div class="relative bg-gray-50 rounded-lg overflow-hidden mb-2 w-full"
                                        style="aspect-ratio: 1/1;">
                                        <?php if (!empty($row['type_image'])): ?>
                                            <img src="../../<?= $row['type_image'] ?>" loading="lazy"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                class="w-full h-full object-contain p-1.5 " />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fas fa-image text-gray-300 text-3xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div class="px-0.5">
                                        <!-- Title -->
                                        <h3 class="text-[13px] font-semibold line-clamp-2 mb-1.5 group-hover:text-orange-600 transition-colors">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>

                                        <!-- Stars -->
                                        <div class="flex items-center gap-1 mb-1.5">
                                            <?php if ($ratingCount > 0): ?>
                                                <div class="flex text-yellow-400 text-[10px]">
                                                    <?php for ($i = 0; $i < $full; $i++)
                                                        echo '<i class="fas fa-star"></i>'; ?>
                                                    <?php if ($half)
                                                        echo '<i class="fas fa-star-half-alt"></i>'; ?>
                                                    <?php for ($i = 0; $i < $empty; $i++)
                                                        echo '<i class="far fa-star text-gray-300"></i>'; ?>
                                                </div>
                                                <span class="text-[9px] text-gray-500">(<?= $ratingCount ?>)</span>
                                            <?php else: ?>
                                                <div class="flex text-gray-300 text-[10px]">
                                                    <?php for ($i = 0; $i < 5; $i++)
                                                        echo '<i class="far fa-star"></i>'; ?>
                                                </div>
                                                <span class="text-[9px] text-gray-400">No rating</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Price -->
                                        <div class="flex items-baseline gap-1 flex-wrap mb-1">
                                            <?php if ($discount > 0): ?>
                                                <p class="text-xs font-semibold">₱<?= number_format($finalPrice, 2) ?></p>
                                                <span class="text-[9px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">
                                                    -<?= number_format($discount, 0) ?>%
                                                </span>
                                            <?php else: ?>
                                                <p class="text-xs font-semibold">₱<?= number_format($finalPrice, 2) ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Viewing -->
                                        <?php if ($viewCount > 0): ?>
                                            <div class="text-[9px] font-medium">
                                                <?= number_format($viewCount) ?> viewing
                                                <?php if ($soldCount > 0): ?> | <?= number_format($soldCount) ?> sold<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <script>
        new Swiper('.newArrivalSwiper', {
            slidesPerView: 7,
            spaceBetween: 16,
            navigation: {
                nextEl: '.na-next',
                prevEl: '.na-prev',
            },
            breakpoints: {
                320: { slidesPerView: 2, spaceBetween: 10 },
                640: { slidesPerView: 3, spaceBetween: 12 },
                768: { slidesPerView: 4, spaceBetween: 14 },
                1024: { slidesPerView: 5, spaceBetween: 16 },
                1280: { slidesPerView: 6, spaceBetween: 16 },
                1536: { slidesPerView: 7, spaceBetween: 16 },
            }
        });
    </script>


    <?php if (!$cookieAccepted): ?>
        <section id="cookie-banner"
            class="fixed bottom-4 left-4 right-4 bg-white border shadow-lg rounded-lg p-4 flex items-center justify-between z-50">
            <p class="text-sm text-gray-700">
                This website uses cookies to personalize content, improve your browsing experience,
                remember your preferences, and analyze site traffic. By clicking "Accept",
                you consent to the use of cookies in accordance with our Privacy Policy.
            </p>

            <form method="post">
                <button type="submit" name="acceptCookies"
                    class="ml-4 bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    Accept
                </button>
            </form>
        </section>
    <?php endif; ?>

    <?php include '../navbar/footer.php'; ?>

    <script>
        AOS.init();
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

        // Product form submit handler
        async function handleProductFormSubmit(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.textContent;
            const originalClasses = button.className;

            // Show loading state
            button.disabled = true;
            button.textContent = 'Adding...';
            button.classList.add('opacity-60');

            try {
                // Defaults
                formData.set('selected_color_id', formData.get('selected_color_id') || '1');
                formData.set('selected_color_name', formData.get('selected_color_name') || 'Default');
                formData.set('color_price', formData.get('color_price') || '0');

                const response = await fetch('../cart/add_to_cart', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message || 'Added to cart', 'success');
                    updateCartCount(data.cart_count);

                    // Simple text feedback - no styling changes
                    button.textContent = 'Added';
                    button.disabled = true;

                    setTimeout(() => {
                        button.textContent = originalText;
                        button.className = originalClasses;
                        button.disabled = false;
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Add to cart failed.');
                }
            } catch (error) {
                showNotification(' ' + error.message, 'error');
                console.error('Add to cart error:', error);

                button.textContent = originalText;
                button.className = originalClasses;
                button.disabled = false;
            }
        }

        // 🔔 Notification utility
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            }[type] || 'bg-blue-500';

            notification.className = `fixed top-4 text-xs left-1/2 -translate-x-1/2 p-2 rounded-lg z-50 ${bgColor} text-white shadow-lg transform transition-all duration-300`;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // 🛒 Cart count updater
        function updateCartCount(count) {
            document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]').forEach(el => {
                el.textContent = count;
                el.style.display = count > 0 ? 'inline' : 'none';
            });

            const bubble = document.getElementById('cart-count-bubble');
            if (bubble) {
                bubble.classList.toggle('hidden', count <= 0);
                bubble.style.display = count > 0 ? 'inline' : 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Swiper === 'undefined') {
                console.error('Swiper library is not loaded.');
                return;
            }

            // ✅ MAIN HERO SWIPER
            const heroSlideCount = document.querySelector('.mySwiper')?.querySelectorAll('.swiper-slide').length || 0;
            initSwiper('.mySwiper', {
                slidesPerView: 1,
                spaceBetween: 0,
                loop: heroSlideCount > 1,
                autoplay: heroSlideCount > 1 ? {
                    delay: 4000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true
                } : false,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev"
                },
                speed: 1000,
                effect: 'slide'
            });


            // 🛒 OTHER PRODUCTS SWIPER - Multi-instance (excludes furniture)
            document.querySelectorAll('.mySwiper-products').forEach((element, index) => {
                const productSlideCount = element.querySelectorAll('.swiper-slide').length || 0;

                new Swiper(element, {
                    slidesPerView: 2,
                    spaceBetween: 10,
                    loop: productSlideCount >= 4,
                    autoplay: productSlideCount > 2 ? {
                        delay: 3000 + (index * 500),
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true
                    } : false,
                    pagination: {
                        el: element.querySelector(".swiper-pagination"),
                        clickable: true
                    },
                    navigation: {
                        nextEl: element.querySelector(".swiper-button-next"),
                        prevEl: element.querySelector(".swiper-button-prev")
                    },
                    breakpoints: {
                        480: {
                            slidesPerView: 2,
                            spaceBetween: 12,
                            loop: productSlideCount >= 4
                        },
                        640: {
                            slidesPerView: 2,
                            spaceBetween: 15,
                            loop: productSlideCount >= 4
                        },
                        768: {
                            slidesPerView: 3,
                            spaceBetween: 15,
                            loop: productSlideCount >= 6
                        },
                        1024: {
                            slidesPerView: 4,
                            spaceBetween: 18,
                            loop: productSlideCount >= 10
                        },
                        1280: {
                            slidesPerView: 5,
                            spaceBetween: 20,
                            loop: productSlideCount >= 10
                        },
                        1536: {
                            slidesPerView: 7,
                            spaceBetween: 25,
                            loop: productSlideCount >= 14
                        }
                    }
                });
            });

            // Product forms
            document.querySelectorAll('.productForm').forEach(form => {
                form.addEventListener('submit', handleProductFormSubmit);
            });

            // Recent Views Swiper
            const recentSlideCount = document.querySelector('.mySwiper-recent')?.querySelectorAll('.swiper-slide').length || 0;
            if (recentSlideCount > 0) {
                initSwiper('.mySwiper-recent', {
                    slidesPerView: 2,
                    spaceBetween: 10,
                    loop: recentSlideCount >= 4,
                    autoplay: false,
                    navigation: {
                        nextEl: '.swiper-button-next-recent',
                        prevEl: '.swiper-button-prev-recent',
                    },
                    pagination: {
                        el: '.swiper-pagination-recent',
                        clickable: true,
                    },
                    breakpoints: {
                        480: {
                            slidesPerView: 2,
                            spaceBetween: 12,
                            loop: recentSlideCount >= 4
                        },
                        640: {
                            slidesPerView: 3,
                            spaceBetween: 15,
                            loop: recentSlideCount >= 6
                        },
                        768: {
                            slidesPerView: 4,
                            spaceBetween: 15,
                            loop: recentSlideCount >= 8
                        },
                        1024: {
                            slidesPerView: 4,
                            spaceBetween: 18,
                            loop: recentSlideCount >= 10
                        },
                        1536: {
                            slidesPerView: 5,
                            spaceBetween: 25,
                            loop: recentSlideCount >= 14
                        }
                    }
                });
            }

            const recommendedSlideCount = document.querySelector('.mySwiper-recommended')?.querySelectorAll('.swiper-slide').length || 0;

            new Swiper('.mySwiper-recommended', {
                slidesPerView: 2,
                spaceBetween: 10,
                loop: recommendedSlideCount >= 2,
                navigation: {
                    nextEl: '.swiper-button-next-recommended',
                    prevEl: '.swiper-button-prev-recommended',
                },
                breakpoints: {
                    480: { slidesPerView: 2, spaceBetween: 12, loop: recommendedSlideCount >= 2 },
                    640: { slidesPerView: 2, spaceBetween: 15, loop: recommendedSlideCount >= 2 },
                    768: { slidesPerView: 3, spaceBetween: 15, loop: recommendedSlideCount >= 6 },
                    1024: { slidesPerView: 4, spaceBetween: 18, loop: recommendedSlideCount >= 10 },
                    1280: { slidesPerView: 5, spaceBetween: 20, loop: recommendedSlideCount >= 10 },
                    1536: { slidesPerView: 7, spaceBetween: 25, loop: recommendedSlideCount >= 14 }
                }
            });

            function updateRecommendedNav(swiper) {
                const prevBtn = document.querySelector('.swiper-button-prev-recommended');
                const nextBtn = document.querySelector('.swiper-button-next-recommended');

                // Kunin ang ACTUAL na slidesPerView na ginagamit ngayon
                const perView = swiper.params.slidesPerView;
                const totalSlides = swiper.slides.length;
                const activeIndex = swiper.activeIndex;

                const isAtBeginning = activeIndex === 0;
                const isAtEnd = activeIndex + perView >= totalSlides; // ← key fix

                if (prevBtn) {
                    prevBtn.disabled = isAtBeginning;
                    prevBtn.classList.toggle('opacity-30', isAtBeginning);
                    prevBtn.classList.toggle('cursor-not-allowed', isAtBeginning);
                    prevBtn.classList.toggle('pointer-events-none', isAtBeginning);
                }

                if (nextBtn) {
                    nextBtn.disabled = isAtEnd;
                    nextBtn.classList.toggle('opacity-30', isAtEnd);
                    nextBtn.classList.toggle('cursor-not-allowed', isAtEnd);
                    nextBtn.classList.toggle('pointer-events-none', isAtEnd);
                }
            }
        });
    </script>

</body>

</html>