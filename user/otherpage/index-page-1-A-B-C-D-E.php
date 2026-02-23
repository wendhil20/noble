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

// 1. Fetch all variants (basic list) - WITH VIEW COUNT, RATING & SOLD COUNT
$query_variants1 = "
    SELECT 
        pv.id, 
        pv.type_id, 
        pv.color, 
        pv.size, 
        pv.price, 
        pv.percent, 
        pv.image, 
        pv.origin,
        p.view_count,
        p.unique_view_count,
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold
    FROM product_variants pv
    LEFT JOIN product_types pt ON pv.type_id = pt.id
    LEFT JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    GROUP BY pv.id
    ORDER BY pv.id DESC
";
$result_variants = mysqli_query($conn, $query_variants1);

// 2. Furniture product list - WITH VIEW COUNT, RATING & SOLD COUNT
$SYCJ_query = "
    SELECT 
        p.*,
        p.view_count,
        p.unique_view_count,
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold
    FROM products p 
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    WHERE p.codename = 'furniture' 
    AND p.is_archived = 0
    GROUP BY p.id
    ORDER BY p.id DESC
";
$SYCJ_result = mysqli_query($conn, $SYCJ_query);

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
        pc.id AS color_id,+
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
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
        $discount = (float)($row['discount'] ?? 0);
        if ($discount > $maxDiscount) {
            $maxDiscount = $discount;
        }
    }
    // Reset pointer back to beginning for the swiper loop
    mysqli_data_seek($material_results, 0);
}

// 4. Discount between 1-5% - WITH VIEW COUNT, RATING & SOLD COUNT
$material_querysone = "
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
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    LEFT JOIN product_colors pc 
        ON pc.id = (
            SELECT MIN(pc2.id) 
            FROM product_colors pc2 
            WHERE pc2.product_id = p.id
        )
    WHERE pv.discount BETWEEN 1 AND 5
    AND p.is_archived = 0
    GROUP BY p.id
    ORDER BY pv.percent ASC, p.view_count DESC, p.id ASC
";
$material_resultsone = mysqli_query($conn, $material_querysone);

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
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
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

// 6. Products without discount - WITH VIEW COUNT, RATING & SOLD COUNT
$discount_result = mysqli_query(
    $conn,
    "SELECT 
        pv.*,
        pv.origin,
        pt.type_image,
        pt.type_name,
        pt.product_id,
        p.product_name,
        p.main_image,
        p.codename,
        p.description,
        p.descrip6,
        p.descrip7,
        p.view_count,
        p.unique_view_count,
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price,
        pc.image
     FROM product_variants pv
     INNER JOIN product_types pt ON pv.type_id = pt.id
     INNER JOIN products p ON pt.product_id = p.id
     LEFT JOIN product_ratings r ON r.product_id = p.id
     LEFT JOIN sold_items si ON si.product_id = p.id
     LEFT JOIN product_colors pc ON p.id = pc.product_id
     WHERE pv.discount IS NULL OR pv.discount = 0
     AND p.is_archived = 0
     GROUP BY pv.id
     ORDER BY p.view_count DESC, RAND()
     LIMIT 10"
);

// 7. Filter by furniture codename - CORRECT PRICE (SIZE + COLOR SEPARATE)
$filter = 'furniture';
$query = "
    SELECT 
        p.*, 
        p.descrip6, 
        p.descrip7,
        p.view_count,
        p.unique_view_count,
        v.origin,
        v.discount,
        v.percent,
        v.status,
        -- 🔥 SIZE PRICES (variant prices only)
        COALESCE(MIN(pv.price), 0) as min_size_price,  
        COALESCE(MAX(pv.price), 0) as max_size_price,  
        -- 🔥 COLOR PRICES (color prices only - kept separate)
        COALESCE(MIN(pc.price), 0) as min_color_price,
        COALESCE(MAX(pc.price), 0) as max_color_price,
        COUNT(DISTINCT pc.id) as color_count,
        AVG(r.rating) AS avg_rating,
        COUNT(r.rating) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    WHERE p.codename = ?
    AND p.is_archived = 0
    GROUP BY p.id
    ORDER BY p.view_count DESC, RAND()
    LIMIT 10";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$result = $stmt->get_result();

// 8. Filter by material codename - CORRECT PRICE (SIZE + COLOR SEPARATE)
$filter = 'buildingmaterials';
$query = "
    SELECT 
        p.*, 
        p.descrip6, 
        p.descrip7,
        p.view_count,
        p.unique_view_count,
        v.origin,
        v.discount,
        v.percent,
        v.status,
        -- 🔥 SIZE PRICES (variant prices only)
        COALESCE(MIN(pv.price), 0) as min_size_price, 
        COALESCE(MAX(pv.price), 0) as max_size_price, 
        -- 🔥 COLOR PRICES (color prices only - kept separate)
        COALESCE(MIN(pc.price), 0) as min_color_price,
        COALESCE(MAX(pc.price), 0) as max_color_price,
        COUNT(DISTINCT pc.id) as color_count,
        AVG(r.rating) AS avg_rating,
        COUNT(r.rating) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold
    FROM products p
    LEFT JOIN (
        SELECT * FROM product_variants 
        GROUP BY product_id
    ) v ON v.product_id = p.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    WHERE p.codename = ?
    AND p.is_archived = 0
    GROUP BY p.id
    ORDER BY p.view_count DESC, RAND()
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$results = $stmt->get_result();

// 9. Filter by aircon - CORRECT PRICE (SIZE + COLOR SEPARATE)
$filters = 'aircon';
$query = "
    SELECT 
        p.*, 
        p.descrip6, 
        p.descrip7,
        p.view_count,
        p.unique_view_count,
        MAX(v.origin) as origin,
        MAX(v.discount) as discount,
        MAX(v.percent) as percent,
        MAX(v.status) as status,
        -- Get the first variant's subcategory_name
        (SELECT subcategory_name 
         FROM product_variants 
         WHERE product_id = p.id 
         LIMIT 1) as subcategory_name,
        -- 🔥 SIZE PRICES (variant prices only)
        COALESCE(MIN(pv.price), 0) as min_size_price,
        COALESCE(MAX(pv.price), 0) as max_size_price,  
        -- 🔥 COLOR PRICES (color prices only - kept separate)
        COALESCE(MIN(pc.price), 0) as min_color_price,
        COALESCE(MAX(pc.price), 0) as max_color_price,
        COUNT(DISTINCT pc.id) as color_count,
        AVG(r.rating) AS avg_rating,
        COUNT(r.rating) AS rating_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    LEFT JOIN sold_items si ON si.product_id = p.id
    WHERE p.codename = ?
    AND p.is_archived = 0
    GROUP BY p.id
    ORDER BY p.view_count DESC, RAND()
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filters);
$stmt->execute();
$resultss = $stmt->get_result();

// 🆕 10. Get Trending Products (High recent views)
$trending_products = getTrendingProducts($conn, 10);

// 🆕 11. Get Most Viewed Products (All time)
$most_viewed = getMostViewedProducts($conn, 10);

// 12. Organize discount products into columns
$products = [];
if ($discount_result) {
    while ($row = mysqli_fetch_assoc($discount_result)) {
        $products[] = $row;
    }
}

if (!empty($products)) {
    $columns = array_chunk($products, ceil(count($products) / 3));
} else {
    $columns = [[], [], []];
}

// 13. Slider images
$sql = "SELECT filename FROM discount_images ORDER BY uploaded_at DESC";
$slideresult = $conn->query($sql);

// Error handling function
function handleQueryError($conn, $query_name)
{
    if (mysqli_error($conn)) {
        error_log("Database Error in $query_name: " . mysqli_error($conn));
        return false;
    }
    return true;
}

// Check for errors in critical queries
handleQueryError($conn, "Discount 10% Query");
handleQueryError($conn, "Discount 1-5% Query");
handleQueryError($conn, "New Status Query");


// 🆕 Helper function to display trending badge
function displayTrendingBadge($view_count)
{
    if ($view_count >= 1000) {
        return '<span class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full flex items-center gap-1 shadow-lg">
                     Popular
                </span>';
    } elseif ($view_count >= 20) {
        return '<span class="absolute top-2 left-2 bg-orange-500 text-white text-xs px-2 py-1 rounded-full flex items-center gap-1 shadow-lg">
                     Popular
                </span>';
    }
    return '';
}


// In the bed furniture loop where you set $subcategory_name
$subcategory_name = 'uncategorized';
if (!empty($row['subcategory_name'])) {
    $decoded = json_decode($row['subcategory_name'], true);
    if (is_array($decoded) && count($decoded) > 0) {
        // Use the first subcategory for filtering
        $subcategory_name = $decoded[0];
    } else {
        $subcategory_name = $row['subcategory_name'];
    }
}
$sub_slug = strtolower(str_replace(' ', '-', $subcategory_name));

// Fetch active banners from discount_images table
$banners_query = "SELECT di.*, c.name as category_name FROM discount_images di LEFT JOIN categories c ON di.category_id = c.id WHERE di.is_active = 1 AND di.category_id IS NOT NULL ORDER BY di.uploaded_at DESC LIMIT 10";
$banners_result = $conn->query($banners_query);
$banners = [];
while ($row = $banners_result->fetch_assoc()) {
    $banners[] = $row;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Noble Home - Modern Furnishing Supplies</title>
    <link rel="preconnect" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="../css/promotionslide.css" rel="stylesheet">
    <link href="../css/bannerPromo.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link href="https://unpkg.com/aos@next/dist/aos.css" rel="stylesheet" />
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com?plugins=aspect-ratio"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js" defer></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.19/bundled/lenis.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Function to hide the notification after 5 seconds
        setTimeout(function() {
            const notification = document.getElementById('loginNotification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 5000); // 5000ms = 5 seconds

        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        // Sans-serif fonts
                        mont: ['Montserrat', 'sans-serif'],

                    }
                }
            }
        }
    </script>
    <style>
        .swiper-slide,
        .swiper-slide-active {
            opacity: 1 !important
        }

        .font-opensans {
            font-family: 'Open Sans', sans-serif
        }

        .font-roboto {
            font-family: Roboto, sans-serif
        }

        .hero-bg {
            background-image: linear-gradient(135deg, rgba(0, 0, 0, .7) 0, rgba(0, 0, 0, .4) 100%), url('img/bodyimg/a.png');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden
        }

        .floating-elements::after,
        .floating-elements::before {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, rgba(251, 146, 60, .1), rgba(251, 146, 60, .05));
            animation: 6s ease-in-out infinite float
        }

        .floating-elements::before {
            width: 300px;
            height: 300px;
            top: 10%;
            left: 10%;
            animation-delay: 0s
        }

        .floating-elements::after {
            width: 200px;
            height: 200px;
            bottom: 10%;
            right: 10%;
            animation-delay: 3s
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

        .mySwiper {
            position: relative;
            width: 100%;
        }

        .swiper-wrapper {
            display: flex;
        }

        .swiper-slide {
            width: 100%;
            display: flex;
            flex-shrink: 0;
        }

        .swiper-pagination {
            position: absolute !important;
            bottom: 10px !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 0 !important;
        }

        .swiper-pagination-bullet {
            background: rgba(20, 16, 16, 0.6) !important;
            opacity: 1 !important;
            width: 8px !important;
            height: 8px !important;
            margin: 0 !important;
            cursor: pointer;
        }

        .swiper-pagination-bullet-active {
            background: #000000ff !important;
            opacity: 1 !important;
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

<body class="font-roboto">

    <?php include '../navbar/top.php'; ?>

    <?php include 'index-flash_notification-D.php'; ?>

    <?php if (isset($_SESSION['toast'])): ?>
        <div id="toast" class="fixed top-5 right-5 bg-<?= $_SESSION['toast']['type'] === 'error' ? 'red' : 'green' ?>-500 text-white text-lg px-4 py-2 rounded shadow-lg z-50">
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
            <div class="bg-red-200 text-yellow-800 border-l-4 border-red-500 p-4 rounded shadow-lg max-w-md w-full text-center">
                <p><?= htmlspecialchars($notification_message) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['login_success'])): ?>
        <div id="login-alert" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50
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

    <!-- DYNAMIC BANNER SLIDER WITH CATEGORY LINKS -->
    <div class="w-full">
        <!-- Mobile: Full width slider, Tablet: 7 cols + 5 cols grid, Desktop: 8 cols + 4 cols grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 lg:grid-cols-12 gap-1">

            <!-- Main Slider -->
            <div class="md:col-span-12 lg:col-span-12 xl:col-span-8 w-full h-full">
                <div class="relative overflow-hidden h-full">
                    <div class="mySwiper h-full">
                        <div class="swiper-wrapper">
                            <?php if (!empty($banners)): ?>
                                <?php foreach ($banners as $idx => $banner): ?>
                                    <a href="../otherpage/index-subcategory_grid_page-14.php?category_name=<?= urlencode(strtolower($banner['category_name'])) ?>"
                                        class="swiper-slide block cursor-pointer hover:opacity-90 transition-opacity group">
                                        <div class="relative w-full h-full overflow-hidden flex items-center justify-center bg-gray-100 ">
                                            <!-- Skeleton Loading -->
                                            <div class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer"></div>

                                            <img src="../../uploads/<?= basename($banner['filename']) ?>"
                                                alt="<?= htmlspecialchars($banner['category_name'] ?? 'Banner') ?>"
                                                class="banner-image w-auto h-auto object-contain max-w-full max-h-full opacity-0 z-10"
                                                onerror="this.src='../../uploads/placeholder.jpg'; this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                                onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="swiper-slide bg-gray-800 flex items-center justify-center rounded-lg">
                                    <p class="text-gray-400">No banners available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="swiper-pagination"></div>
                    </div>
                </div>
            </div>

            <!-- Right Side Cards - Hidden on mobile, tablet, and smaller desktop - visible on large desktop only -->
            <div class="hidden xl:grid xl:col-span-4 gap-1 auto-rows-fr">

                <!-- Card 1: Flash Discount -->
                <a href="index-countdowntimer-page-17.php" class="relative overflow-hidden group transition-all h-full">
                    <div class="relative bg-gray-100 flex items-center justify-center h-full">
                        <!-- Skeleton Loading -->
                        <div class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer"></div>

                        <img src="../img/display1.webp"
                            alt="Flash Discount"
                            class="banner-image w-auto h-auto object-contain max-w-full max-h-full group-hover:scale-105 transition-transform duration-300 opacity-0 z-10"
                            onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />
                        <div class="absolute inset-0 flex flex-col justify-end p-4 bg-gradient-to-t from-black/70 via-black/30 to-transparent pointer-events-none z-20">
                            <div class="text-white text-sm group-hover:text-orange-400 transition-colors" style="font-family: 'Montserrat', sans-serif;">
                                Flash Discount ➜
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Card 2: Category/Deals - Links to first banner category -->
                <a href="<?= !empty($banners) ? '../otherpage/index-subcategory_grid_page-14.php?category_name=' . urlencode(strtolower($banners[0]['category_name'])) : '#' ?>"
                    class="relative overflow-hidden group transition-all h-full">
                    <div class="relative bg-gray-100 flex items-center justify-center h-full">
                        <!-- Skeleton Loading -->
                        <div class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer"></div>

                        <?php if (!empty($banners)): ?>
                            <img src="../../uploads/<?= basename($banners[0]['filename']) ?>"
                                alt="<?= htmlspecialchars($banners[0]['category_name']) ?>"
                                class="banner-image w-auto h-auto object-contain max-w-full max-h-full group-hover:scale-105 transition-transform duration-300 opacity-0 z-10"
                                onerror="this.src='../../uploads/placeholder.jpg'; this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />
                        <?php else: ?>
                            <img src="../img/gif1.gif" alt="Deals" class="banner-image w-auto h-auto object-contain max-w-full max-h-full opacity-0 z-10" onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />
                        <?php endif; ?>

                        <div class="absolute inset-0 flex flex-col justify-end p-4 bg-gradient-to-t from-black/70 via-black/30 to-transparent pointer-events-none z-20">
                            <div class="text-white text-sm group-hover:text-orange-400 transition-colors" style="font-family: 'Montserrat', sans-serif;">
                                <?php
                                $label = !empty($banners) ? $banners[0]['category_name'] : 'Holiday Deals';
                                echo ucfirst(htmlspecialchars($label));
                                ?>➜
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Mobile-only category cards (visible on small screens) -->
        <div class="md:hidden grid grid-cols-2 gap-1 mt-1">
            <a href="index-countdowntimer-page-17.php" class="relative overflow-hidden group transition-all ">
                <div class="relative bg-gray-100 flex items-center justify-center" style="min-height: 120px;">
                    <!-- Skeleton Loading -->
                    <div class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer"></div>

                    <img src="../img/gif1.gif"
                        alt="Flash Discount"
                        class="banner-image w-auto h-auto object-contain max-w-full max-h-full group-hover:scale-105 transition-transform duration-300 opacity-0 z-10"
                        onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />

                    <div class="absolute inset-0 flex flex-col justify-end p-3 bg-gradient-to-t from-black/30 to-transparent  pointer-events-none z-20">
                        <div class="text-white text-xs group-hover:text-orange-400 transition-colors" style="font-family: 'Montserrat', sans-serif;">
                            Flash Discount ➜
                        </div>
                    </div>
                </div>
            </a>

            <a href="<?= !empty($banners) ? '../otherpage/index-subcategory_grid_page-14.php?category_name=' . urlencode(strtolower($banners[0]['category_name'])) : '#' ?>"
                class="relative overflow-hidden group transition-all">
                <div class="relative bg-gray-100 flex items-center justify-center" style="min-height: 120px;">
                    <!-- Skeleton Loading -->
                    <div class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer"></div>

                    <?php if (!empty($banners)): ?>
                        <img src="../../uploads/<?= basename($banners[0]['filename']) ?>"
                            alt="<?= htmlspecialchars($banners[0]['category_name']) ?>"
                            class="banner-image w-auto h-auto object-contain max-w-full max-h-full group-hover:scale-105 transition-transform duration-300 opacity-0 z-10"
                            onerror="this.src='../../uploads/placeholder.jpg'; this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                            onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />
                    <?php else: ?>
                        <img src="../img/gif1.gif" alt="Deals" class="banner-image w-auto h-auto object-contain max-w-full max-h-full opacity-0 z-10" onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';" />
                    <?php endif; ?>

                    <div class="absolute inset-0 flex flex-col justify-end p-3 bg-gradient-to-t from-black/30 to-transparent  pointer-events-none z-20">
                        <div class="text-white text-xs group-hover:text-orange-400 transition-colors" style="font-family: 'Montserrat', sans-serif;">
                            <?php
                            $label = !empty($banners) ? $banners[0]['category_name'] : 'Deals';
                            echo ucfirst(htmlspecialchars($label));
                            ?>➜
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>


    <section class="bg-black hidden md:block border border-black/20">
        <div class="px-4 sm:px-8 lg:px-9 py-2">
            <!-- Clickable Banner Image - Auto Size based on actual image dimensions -->
            <a href="index-shop-page-2.php" class="block hover:opacity-90 transition-opacity duration-300 w-fit">
                <img src="../img/exclusive1.png"
                    alt="Exclusive Discounts - Shop Now"
                    class="h-auto w-auto object-contain"
                    loading="lazy">
            </a>
        </div>
    </section>

    <section class="py-3 tracking-wide px-2 bg-gray-100 block">
        <div class="max-w-full mx-auto grid grid-cols-2 md:grid-cols-4 justify-items-center hidden md:grid">

            <!-- Fast Delivery -->
            <div class="flex flex-col md:flex-row md:items-center md:gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 md:w-8 md:h-8 text-gray-700 mx-auto md:mx-0 mb-2 md:mb-0 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <div class="text-center md:text-left">
                    <h3 class="text-xs md:text-base " style="font-family: 'Montserrat', sans-serif; color: #2f1200">Fast Delivery</h3>
                    <p class="text-xs text-gray-500 ">Quick & reliable shipping</p>
                </div>
            </div>

            <!-- High Quality -->
            <div class="flex flex-col md:flex-row md:items-center md:gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 md:w-8 md:h-8 text-gray-700 mx-auto md:mx-0 mb-2 md:mb-0 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-center md:text-left">
                    <h3 class="text-xs md:text-base " style="font-family: 'Montserrat', sans-serif; color: #2f1200">High Quality</h3>
                    <p class="text-xs text-gray-500 ">Premium products guaranteed</p>
                </div>
            </div>

            <!-- Affordable Prices -->
            <div class="flex flex-col md:flex-row md:items-center md:gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 md:w-8 md:h-8 text-gray-700 mx-auto md:mx-0 mb-2 md:mb-0 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <div class="text-center md:text-left">
                    <h3 class="text-xs md:text-base " style="font-family: 'Montserrat', sans-serif; color: #2f1200">Affordable Prices</h3>
                    <p class="text-xs text-gray-500 ">Best deals & discounts</p>
                </div>
            </div>

            <!-- Secure Checkout -->
            <div class="flex flex-col md:flex-row md:items-center md:gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 md:w-8 md:h-8 text-gray-700 mx-auto md:mx-0 mb-2 md:mb-0 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div class="text-center md:text-left">
                    <h3 class="text-xs md:text-base " style="font-family: 'Montserrat', sans-serif; color: #2f1200">Secure Checkout</h3>
                    <p class="text-xs text-gray-500">Safe & encrypted payments</p>
                </div>
            </div>
        </div>
    </section>

    <?php
    $recent_views = getRecentViews($conn, 10);
    $recent_count = mysqli_num_rows($recent_views);
    $recommended_products = getRecommendedProducts($conn, 10);
    $recommended_count = mysqli_num_rows($recommended_products);

    function renderProductCard($row, $conn)
    {
        $product_id = (int)$row['id'];

        // ✅ GET SIZE PRICES (final prices from product_variants)
        $minSizePrice = (float)($row['min_size_price'] ?? 0);
        $maxSizePrice = (float)($row['max_size_price'] ?? 0);

        // ✅ GET COLOR PRICES (from product_colors)
        $minColorPrice = (float)($row['min_color_price'] ?? 0);
        $maxColorPrice = (float)($row['max_color_price'] ?? 0);

        // ✅ GET DISCOUNT (display only)
        $discount = (float)($row['discount'] ?? 0);

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
        $total_sold = (int)($sold_result['total_sold'] ?? 0);
        $sold_q->close();

        $full = floor($avg_rating);
        $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
        $empty = 5 - $full - $half;

        $colors = !empty($row['color_name']) ? explode(',', $row['color_name']) : [];
        $firstColor = !empty($colors) ? trim($colors[0]) : '';
    ?>
        <div class="swiper-slide">
            <a href="index-product_view-page-4-AA?id=<?= $product_id ?>" class="block group">
                <!-- Image container -->
                <div class="relative bg-gray-50 rounded-lg overflow-hidden mb-2 w-full" style="aspect-ratio: 1/1; max-height: 160px;">
                    <?php if (!empty($row['main_image'])): ?>
                        <img src="../../<?= $row['main_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['product_name']) ?>" class="w-full h-full object-contain p-1.5 transition-transform duration-300 group-hover:scale-105" />
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-image text-gray-300 text-3xl"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="px-0.5 flex flex-col h-full">
                    <!-- Product name -->
                    <h3 class="text-[13px] group-hover:text-blue-600 transition-colors mb-1.5 line-clamp-2 font-semibold" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                        <?= htmlspecialchars($row['product_name']) ?>
                        <?php if (!empty($row['size'])): ?> <?= htmlspecialchars($row['size']) ?><?php endif; ?>
                            <?php if ($firstColor): ?> <?= htmlspecialchars($firstColor) ?><?php endif; ?>
                    </h3>

                    <!-- Rating -->
                    <div class="flex items-center gap-1 mb-1.5">
                        <?php if ($total_raters > 0): ?>
                            <div class="flex text-yellow-400 text-[10px]">
                                <?php for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>'; ?>
                                <?php if ($half) echo '<i class="fas fa-star-half-alt"></i>'; ?>
                                <?php for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>'; ?>
                            </div>
                            <span class="text-[9px] text-gray-500 font-medium">(<?= $total_raters ?>)</span>
                        <?php else: ?>
                            <div class="flex text-gray-300 text-[10px]">
                                <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                            </div>
                            <span class="text-[9px] text-gray-400">No rating</span>
                        <?php endif; ?>
                    </div>

                    <!-- 🔥 CORRECT PRICE DISPLAY (NO DISCOUNT DEDUCTION) -->
                    <div class="flex items-baseline gap-1 flex-wrap mb-1 mt-auto" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                        <?php if ($discount > 0): ?>
                            <!-- With Discount Badge (display only) -->
                            <p class="text-md font-semibold">
                                <?= $displayPrice ?>
                            </p>
                            <span class="text-[9px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">-<?= number_format($discount, 0) ?>%</span>
                        <?php else: ?>
                            <!-- No Discount Badge -->
                            <p class="text-xs font-semibold">
                                <?= $displayPrice ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- View count + Sold count -->
                    <?php if (!empty($row['view_count']) || $total_sold > 0): ?>
                        <div class="text-[9px] font-medium" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
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

    <?php if ($recent_count > 0 || $recommended_count > 0): ?>
        <section id="recent-recommendations-section" class="px-4 sm:px-5 lg:px-7 py-4 bg-white">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Recently Viewed -->
                <div class="w-full">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">

                            <h2 class="text-base sm:text-xl " style="font-family: 'Montserrat', sans-serif; color: #2f1200">Recently Viewed ➜</h2>
                        </div>
                        <?php if ($recent_count > 0): ?>
                            <div class="flex gap-1.5">
                                <button class="swiper-button-prev-recent w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                                    <i class="fas fa-chevron-left text-gray-600 text-sm"></i>
                                </button>
                                <button class="swiper-button-next-recent w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                                    <i class="fas fa-chevron-right text-gray-600 text-sm"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($recent_count > 0): ?>
                        <div class="swiper mySwiper-recent w-full">
                            <div class="swiper-wrapper">
                                <?php while ($row = mysqli_fetch_assoc($recent_views)): ?>
                                    <?php renderProductCard($row, $conn); ?>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center py-8 px-4">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                <i class="fas fa-eye-slash text-gray-400 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 text-sm text-center">No items viewed yet</p>
                            <p class="text-gray-400 text-xs text-center mt-1">Start browsing to see your recently viewed products</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recommendations -->
                <?php if ($recommended_count > 0): ?>
                    <div class="w-full">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2.5">

                                <h2 class="text-base sm:text-xl" style="font-family: 'Montserrat', sans-serif; color: #2f1200">Recommended for You ➜</h2>
                            </div>
                            <div class="flex gap-1.5">
                                <button class="swiper-button-prev-recommended w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                                    <i class="fas fa-chevron-left text-gray-600 text-sm"></i>
                                </button>
                                <button class="swiper-button-next-recommended w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
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
            </div>
        </section>
    <?php endif; ?>


    <!-- Modal for "Not Available" -->
    <div id="unavailableModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full p-6 transform transition-all">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-neutral-900 mb-2">Not Available</h3>
                <p class="text-sm text-neutral-600 mb-6">This promotion is currently unavailable. Please check back later.</p>
                <button onclick="closeModal()" class="w-full bg-neutral-900 text-white px-4 py-2 rounded-lg hover:bg-neutral-800 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <section class="px-4 sm:px-6 lg:px-8 py-3">
        <div class="max-w-full mx-auto">
            <!-- 2x2 Grid Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Box 1 -->
                <a href="#" onclick="showUnavailable(event)" class="group block">
                    <div class="overflow-hidden">
                        <img src="../img/display2.webp"
                            alt="Promo 1"
                            class="w-full h-auto object-cover transition-transform duration-500 group-hover:scale-105">
                    </div>
                    <h3 class="text-sm sm:text-lg font-semibold mt-4" style="font-family: 'Montserrat', sans-serif; color: #2f1200">Style to Modern your house!</h3>
                </a>

                <!-- Box 2 -->
                <a href="#" onclick="showUnavailable(event)" class="group block">
                    <div class="overflow-hidden ">
                        <img src="../img/display1.webp"
                            alt="Promo 2"
                            class="w-full h-auto object-cover transition-transform duration-500 group-hover:scale-105">
                    </div>
                    <h3 class="text-sm sm:text-lg font-semibold mt-4" style="font-family: 'Montserrat', sans-serif; color: #2f1200">Chair furniture deals below ₱5,000</h3>
                </a>
            </div>
        </div>
    </section>

    <script>
        function showUnavailable(event) {
            event.preventDefault();
            document.getElementById('unavailableModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('unavailableModal').classList.add('hidden');
        }

        document.getElementById('unavailableModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>

    <!-- POPUP MODAL -->
    <div id="promoPopup" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50">
        <div class="relative max-w-4xl w-full mx-4">
            <!-- Close Button -->
            <button onclick="hidePromoModal()"
                class="absolute -top-4 -right-4 text-white hover:text-red-500 bg-black/70 rounded-full w-10 h-10 flex items-center justify-center text-2xl z-10 transition-colors duration-300">✕</button>

            <!-- Single Image Display -->
            <div class="relative overflow-hidden">
                <a href="index-allproduct-page-3.php?discount=20" class="relative group flex items-center justify-center">
                    <img src="../img/sale/c.png" alt="Special Sale" class="max-w-full max-h-[80vh] object-contain">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-50 transition-opacity duration-300 flex items-end justify-center pb-8">
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



    <?php
    // Fetch categories from database
    $query = "SELECT id, name, image_pathtwo FROM categories ORDER BY id";
    $resultdepartment = mysqli_query($conn, $query);
    $categories = mysqli_fetch_all($resultdepartment, MYSQLI_ASSOC);

    // Category URL mapping - NO LONGER NEEDED but kept for reference
    $categoryUrlMap = [
        'furniture' => 'furniture',
        'Tiles' => 'tiles',
        'Bedfurniture' => 'bedfurniture',
        'BathroomFixtures' => 'bathroom',
        'AACBlock' => 'aacblock',
        'aircon' => 'aircon',
        'KitchenFixtures' => 'kitchen',
        'lightingfixture' => 'lighting',
        'Doors' => 'doors',
        'windows' => 'windows',
        'buildingmaterials' => 'buildingmaterials'
    ];
    ?>

    <section class="py-6 md:py-8 bg-white">
        <!-- Header -->
        <div class="mb-4 md:mb-6 lg:mb-8 px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <h2 class="text-lg md:text-2xl lg:text-3xl font-bold" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                        Shop by Department
                    </h2>
                    <p class="text-xs md:text-sm mt-1" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                        Explore our complete range of products
                    </p>
                </div>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="px-2 sm:px-3 lg:px-4 max-w-8xl mx-auto">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 md:gap-3 lg:gap-4">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryName = $category['name'];
                    $imagePath = '../../uploads/categories/' . $category['image_pathtwo'];

                    // Format display name
                    $displayName = $categoryName;
                    if ($categoryName === 'BathroomFixtures') {
                        $displayName = 'Bathroom';
                    } elseif ($categoryName === 'KitchenFixtures') {
                        $displayName = 'Kitchen Fixtures';
                    } elseif ($categoryName === 'lightingfixture') {
                        $displayName = 'Lighting';
                    } elseif ($categoryName === 'Bedfurniture') {
                        $displayName = 'Bedroom';
                    } elseif ($categoryName === 'buildingmaterials') {
                        $displayName = 'Building Materials';
                    } elseif ($categoryName === 'AACBlock') {
                        $displayName = 'AAC Block';
                    } else {
                        $displayName = ucfirst($categoryName);
                    }
                    ?>
                    <!-- UPDATED: Changed from category_id to category_name -->
                    <a href="index-subcategory_grid_page-14.php?category_name=<?php echo urlencode($categoryName); ?>" class="group block">
                        <div class="rounded-lg overflow-hidden ">
                            <!-- Image -->
                            <div class="h-32 sm:h-40 md:h-48 lg:h-56  flex items-center justify-center p-2 sm:p-3 md:p-4">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                    alt="<?php echo htmlspecialchars($displayName); ?>"
                                    class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy">
                            </div>

                            <!-- Text -->
                            <div class="py-2 md:py-3 px-2 md:px-4 bg-white">
                                <h3 class="text-xs sm:text-sm md:text-base text-center transition-colors uppercase font-semibold" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                    <?php echo htmlspecialchars($displayName); ?>
                                </h3>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>

                <!-- See More Box -->
                <a href="index-shop-page-2.php" class="group block">
                    <div class="rounded-lg overflow-hidden transition-shadow duration-300 h-full flex items-center justify-center">
                        <div class="h-32 sm:h-40 md:h-48 lg:h-56 bg-white flex flex-col items-center justify-center p-2 sm:p-3 md:p-4 w-full">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 bg-black rounded-full flex items-center justify-center group-hover:bg-gray-800 transition-colors mb-2 md:mb-4">
                                <i class="fas fa-arrow-right text-lg sm:text-xl md:text-2xl text-white"></i>
                            </div>
                            <h3 class="text-xs sm:text-sm md:text-base  text-center group-hover:text-blue-600 transition-colors uppercase font-semibold" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                See More
                            </h3>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="px-4 sm:px-6 lg:px-8 ">
        <div class="max-w-full mx-auto">
            <!-- Banner -->
            <a href="https://www.yfk20.com/login" class="group block relative overflow-hidden transition-all duration-300 pointer-events-none cursor-not-allowed" data-aos="fade-up">
                <!-- Rectangle Container -->
                <div class="relative bg-white overflow-hidden flex items-center justify-center inline-block">
                    <!-- Skeleton Loading -->
                    <div class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer"></div>

                    <!-- Image -->
                    <img src="../img/kd.png"
                        alt="Promotion Banner"
                        class="banner-image w-full h-auto object-contain opacity-0 transition-opacity duration-300 z-5"
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



    <?php
    // MAIN query for bestseller items
    $bestsellerItems = $conn->query("SELECT * FROM bestseller ORDER BY id DESC");
    $bestsellerData = $bestsellerItems->fetch_all(MYSQLI_ASSOC);
    ?>
    <section class="py-10 bg-white" id="bestseller-section">
        <div class="max-w-full mx-auto w-full px-4 md:px-6 lg:px-8">

            <!-- Section Header -->
            <div class="mb-8">
                <h2 class="text-2xl sm:text-3xl md:text-4xl mb-2" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                    Best Sellers
                </h2>
                <p class="text-xs sm:text-sm md:text-base" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                    Top quality products
                </p>
            </div>

            <!-- Carousel Container with Arrows -->
            <div class="carousel-container">
                <!-- Left Arrow -->
                <button class="carousel-arrow carousel-arrow-prev" aria-label="Previous slide">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>

                <!-- Swiper Carousel -->
                <div class="swiper bestsellerSwiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($bestsellerData as $item): ?>
                            <div class="swiper-slide">
                                <a href="index-bestseller-detail-B.php?slug=<?= htmlspecialchars($item['slug']) ?>" class="product-card block text-center">
                                    <!-- Image Container -->
                                    <div class="product-image rounded-lg overflow-hidden shadow-sm bg-gray-100 mb-3">
                                        <img
                                            src="<?= htmlspecialchars($item['image'] ?: '../img/promo/default.png') ?>"
                                            alt="<?= htmlspecialchars($item['title']) ?>"
                                            class="w-full h-full object-cover">
                                    </div>

                                    <!-- Product Info -->
                                    <div class="product-info">
                                        <div class="sale-badge mb-2">BESTSELLER</div>

                                        <h3 class="product-title font-medium text-gray-800 mb-2">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </h3>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Arrow -->
                <button class="carousel-arrow carousel-arrow-next" aria-label="Next slide">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6" />
                    </svg>
                </button>
            </div>

        </div>
    </section>

    <style>
        .carousel-container {
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
        }

        .carousel-arrow {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #1f2937;
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        /* Hide arrows on mobile */
        @media (max-width: 1024px) {
            .carousel-arrow {
                display: none;
            }
        }

        .carousel-arrow:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        .carousel-arrow:active {
            transform: scale(0.95);
        }

        .carousel-arrow svg {
            width: 24px;
            height: 24px;
        }

        .product-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-6px);
        }

        .product-image {
            overflow: hidden;
            background: #f5f5f5;
            aspect-ratio: 1;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.08);
        }

        .sale-badge {
            background: #dc2626;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 3px;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
        }

        .product-title {
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            line-height: 1.4;
            min-height: 36px;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-family: 'Montserrat', sans-serif;
        }

        .current-price {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            font-family: 'Montserrat', sans-serif;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: #6b7280;
            justify-content: center;
            margin-top: 6px;
            font-family: 'Montserrat', sans-serif;
        }

        .stars {
            color: #fbbf24;
            font-size: 12px;
        }

        .free-shipping {
            font-size: 12px;
            color: #059669;
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
        }

        .bestsellerSwiper {
            flex: 1;
            width: 100%;
            padding-bottom: 5px;
            position: relative;
        }


        @media (max-width: 1024px) {
            .carousel-arrow {
                display: none;
            }
        }
    </style>

    <script>
        const swiper = new Swiper('.bestsellerSwiper', {
            slidesPerView: 4,
            spaceBetween: 20,
            navigation: {
                nextEl: '.carousel-arrow-next',
                prevEl: '.carousel-arrow-prev',
            },
            pagination: {
                el: '.bestseller-pagination',
                clickable: true,
            },
            breakpoints: {
                320: {
                    slidesPerView: 2,
                    spaceBetween: 12,
                },
                640: {
                    slidesPerView: 3,
                    spaceBetween: 16,
                },
                768: {
                    slidesPerView: 4,
                    spaceBetween: 16,
                },
                1024: {
                    slidesPerView: 5,
                    spaceBetween: 20,
                },
                1280: {
                    slidesPerView: 6,
                    spaceBetween: 24,
                }
            }
        });
    </script>

    <section class=" p-2">
        <!-- Full Width Container for Top Sales -->
        <div class="w-full">

            <!-- TOP SALES SECTION -->
            <?php if (mysqli_num_rows($material_results) > 0): ?>
                <div class="p-6 rounded-lg">
                    <!-- Header -->
                    <div class="text-base mb-6">
                        <h2 class="text-2xl sm:text-3xl mb-1 tracking-tight" style="font-family: 'Montserrat', sans-serif; color: #2f1200" data-aos="fade-up">Sales Up to <span class="text-red-500" style="font-family: 'Montserrat', sans-serif;"><?= number_format($maxDiscount, 0) ?>% OFF ➜</span></h2>

                    </div>

                    <!-- Swiper Container -->
                    <div class="swiper mySwiper-topsales w-full">
                        <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                            <?php
                            mysqli_data_seek($material_results, 0);
                            while ($row = mysqli_fetch_assoc($material_results)) :
                                $variantPrice = (float)($row['price'] ?? 0);
                                $colorPrice = (float)($row['color_price'] ?? 0);
                                $discount = (float)($row['discount'] ?? 0);
                                $finalPrice = $variantPrice + $colorPrice;
                                $viewCount = (int)($row['view_count'] ?? 0);
                                $soldCount = (int)($row['total_sold'] ?? 0);
                                $avgRating = (float)($row['avg_rating'] ?? 0);
                                $ratingCount = (int)($row['rating_count'] ?? 0);
                            ?>
                                <div class="swiper-slide p-1">
                                    <div class="bg-white p-2 lg:p-3 group hover:shadow-lg transition duration-300 flex flex-col justify-between h-[320px] text-center relative rounded-md">


                                        <!-- Product Image -->
                                        <div class="w-32 h-32 mx-auto rounded-lg overflow-hidden mb-2">
                                            <?php if (!empty($row['type_image'])): ?>
                                                <img src="../../<?= $row['type_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['size']) ?>" class="w-full h-full object-cover transition-transform duration-300" />
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Product Info -->
                                        <div>
                                            <div class="px-1 text-left">
                                                <p class="text-xs leading-relaxed text-left">
                                                    <span class="font-medium group-hover:text-orange-600 transition-colors duration-300" style="font-family: 'Montserrat', sans-serif; color: #2f1200"><?= htmlspecialchars($row['product_name']) ?></span>
                                                    <span class="" style="font-family: 'Montserrat', sans-serif; color: #2f1200"><?= !empty($row['size']) ? ' ' . htmlspecialchars($row['size']) : '' ?></span>
                                                    <span class="">
                                                        <?php if (!empty($row['color'])): ?>
                                                            <?php if (!empty($row['color_code'])): ?>
                                                                <span class="inline-block w-2.5 h-2.5 rounded-full border border-gray-300 ml-1 mr-0.5 align-middle" style="background-color: <?= htmlspecialchars($row['color_code']) ?>; font-family: 'Montserrat', sans-serif;"></span>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($row['color']) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </p>
                                            </div>

                                            <!-- Stats -->
                                            <div class="flex items-center justify-start gap-1 mt-1 text-xs px-1" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                                <span><?= number_format($viewCount) ?> viewing</span>
                                                <span>|</span>
                                                <span><?= number_format($soldCount) ?> sold</span>
                                                <?php if ($ratingCount > 0): ?>
                                                    <span>|</span>
                                                    <div class="flex items-center gap-0.5">
                                                        <i class="fa-solid fa-star text-yellow-500 text-xs"></i>
                                                        <span><?= number_format($avgRating, 1) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Price -->
                                            <div class="my-1 text-left px-1" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                                <?php if ($discount > 0): ?>
                                                    <p class="text-sm font-semibold">
                                                        ₱<?= number_format($finalPrice, 2) ?>
                                                        <span class="text-xs text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-sm text-green-600 font-bold">₱<?= number_format($finalPrice, 2) ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="flex gap-2 mt-2 px-1">
                                                <form action="index-product_view-page-4-AA" method="GET" class="flex-1">
                                                    <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                    <button type="submit" class="w-full text-black hover:text-orange-500 transition font-medium text-xs py-2 border border-gray-300 rounded hover:border-orange-500" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                                        View
                                                    </button>
                                                </form>

                                                <form class="productForm flex-1" data-product-id="<?= (int)$row['product_id'] ?>">
                                                    <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                                                    <input type="hidden" name="selected_type" value="<?= htmlspecialchars($row['type_name'] ?? '') ?>">
                                                    <input type="hidden" name="selected_variant" value="<?= htmlspecialchars($row['size'] ?? '') ?>">
                                                    <input type="hidden" name="variant_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="selected_color_id" value="<?= (int)($row['color_id'] ?? 0) ?>">
                                                    <input type="hidden" name="selected_color_name" value="<?= htmlspecialchars($row['color'] ?? '') ?>">
                                                    <input type="hidden" name="color_price" value="<?= floatval($row['color_price'] ?? 0) ?>">
                                                    <input type="hidden" name="variant_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                    <input type="hidden" name="total_price" value="<?= floatval($finalPrice) ?>">
                                                    <input type="hidden" name="return_url" value="index">
                                                    <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white text-xs py-2 rounded transition-all" style="font-family: 'Montserrat', sans-serif;">
                                                        Add to Cart
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commonBreakpoints = {
                320: {
                    slidesPerView: 2,
                    spaceBetween: 8
                },
                480: {
                    slidesPerView: 2,
                    spaceBetween: 8
                },
                640: {
                    slidesPerView: 2,
                    spaceBetween: 8
                },
                768: {
                    slidesPerView: 3,
                    spaceBetween: 12
                },
                820: {
                    slidesPerView: 4,
                    spaceBetween: 12
                },
                1024: {
                    slidesPerView: 5,
                    spaceBetween: 15
                },
                1280: {
                    slidesPerView: 6,
                    spaceBetween: 15
                },
                1536: {
                    slidesPerView: 7,
                    spaceBetween: 20
                }
            };

            new Swiper('.mySwiper-topsales', {
                slidesPerView: 1,
                spaceBetween: 10,
                loop: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: commonBreakpoints
            });
        });
    </script>

    <!-- Featured Categories Section - Dynamic from Database -->
    <?php
    // Fetch categories for featured section
    $featured_query = "
    SELECT 
        c.id,
        c.name,
        c.image_path,
        (SELECT COUNT(DISTINCT pv.product_id) 
         FROM product_variants pv 
         WHERE pv.category_id = c.id
        ) as product_count
    FROM categories c
    WHERE c.id IS NOT NULL
    ORDER BY c.name
    LIMIT 8
";

    $featured_result = $conn->query($featured_query);
    $featured_categories = [];

    if ($featured_result) {
        while ($row = $featured_result->fetch_assoc()) {
            $featured_categories[] = $row;
        }
    }
    ?>


    <section>
        <?php
        // Check if there are any products
        $row_count = mysqli_num_rows($material_resultstwo);
        ?>

        <?php if ($row_count > 0): ?>
            <!-- Top Sales Section -->
            <section class="p-6 px-4 py-4">
                <!-- Header -->
                <div class="text-base mb-10 relative">
                    <h2 class="text-3xl mb-2 tracking-tight" style="font-family: 'Montserrat', sans-serif; color: #2f1200" data-aos="fade-up">New Arrival</h2>
                </div>

                <?php
                // Check if there are any products
                $row_count = mysqli_num_rows($material_resultstwo);
                ?>

                <?php if ($row_count > 0): ?>
                    <!-- Swiper Container -->
                    <div class="swiper mySwiper-products w-full">
                        <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                            <?php
                            mysqli_data_seek($material_resultstwo, 0);
                            while ($row = mysqli_fetch_assoc($material_resultstwo)) :
                                // ✅ DIRECT CALCULATION - size_price + color_price
                                $variantPrice = (float)($row['price'] ?? 0);
                                $colorPrice = (float)($row['color_price'] ?? 0);
                                $discount = (float)($row['discount'] ?? 0);

                                // 🔥 SIMPLE FORMULA: variant + color (no markup deduction)
                                $finalPrice = $variantPrice + $colorPrice;

                                $viewCount = (int)($row['view_count'] ?? 0);
                                $soldCount = (int)($row['total_sold'] ?? 0);
                                $avgRating = (float)($row['avg_rating'] ?? 0);
                                $ratingCount = (int)($row['rating_count'] ?? 0);
                            ?>
                                <div class="swiper-slide p-1">
                                    <div class="bg-white p-2 lg:p-3 group hover:shadow-lg transition duration-300 flex flex-col justify-between h-[320px] text-center relative rounded-md">


                                        <!-- Product Image -->
                                        <div class="w-32 h-32 mx-auto rounded-lg overflow-hidden mb-2">
                                            <?php if (!empty($row['type_image'])): ?>
                                                <img src="../../<?= $row['type_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['size']) ?>" class="w-full h-full object-cover transition-transform duration-300" />
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Product Info -->
                                        <div>
                                            <div class="px-1 text-left">
                                                <p class="text-xs leading-relaxed text-left">
                                                    <span class="font-medium group-hover:text-orange-600 transition-colors duration-300" style="font-family: 'Montserrat', sans-serif; color: #2f1200"><?= htmlspecialchars($row['product_name']) ?></span>
                                                    <span class="" style="font-family: 'Montserrat', sans-serif; color: #2f1200"><?= !empty($row['size']) ? ' ' . htmlspecialchars($row['size']) : '' ?></span>
                                                    <span class="">
                                                        <?php if (!empty($row['color'])): ?>
                                                            <?php if (!empty($row['color_code'])): ?>
                                                                <span class="inline-block w-2.5 h-2.5 rounded-full border border-gray-300 ml-1 mr-0.5 align-middle" style="background-color: <?= htmlspecialchars($row['color_code']) ?>  font-family: 'Montserrat', sans-serif;"></span>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($row['color']) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </p>
                                            </div>

                                            <!-- Stats -->
                                            <div class="flex items-center justify-start gap-1 mt-1 text-xs px-1" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                                <span><?= number_format($viewCount) ?> viewing</span>
                                                <span>|</span>
                                                <span><?= number_format($soldCount) ?> sold</span>
                                                <?php if ($ratingCount > 0): ?>
                                                    <span>|</span>
                                                    <div class="flex items-center gap-0.5">
                                                        <i class="fa-solid fa-star text-yellow-500 text-xs"></i>
                                                        <span><?= number_format($avgRating, 1) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Price -->
                                            <div class="my-1 text-left px-1" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                                <?php if ($discount > 0): ?>
                                                    <p class="text-sm font-semibold">
                                                        ₱<?= number_format($finalPrice, 2) ?>
                                                        <span class="text-xs text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-sm text-green-600 font-bold">₱<?= number_format($finalPrice, 2) ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="flex gap-2 mt-2 px-1">
                                                <form action="index-product_view-page-4-AA" method="GET" class="flex-1">
                                                    <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                    <button type="submit" class="w-full text-black hover:text-orange-500 transition font-medium text-xs py-2 border border-gray-300 rounded hover:border-orange-500" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                                        View
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Products Available Message -->
                    <div class="flex flex-col items-center justify-center py-16 px-4" data-aos="fade-up">
                        <div class="text-center max-w-md">
                            <!-- Icon -->
                            <div class="mb-6">
                                <svg class="w-24 h-24 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                            </div>

                            <!-- Message -->
                            <h3 class="text-xl sm:text-2xl font-semibold text-gray-700 mb-3">
                                No Products Available
                            </h3>
                            <p class="text-gray-500 text-sm sm:text-base mb-6">
                                There are currently no discounted products available in this section. Please check back later for amazing deals!
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                <style>
                    /* Animated View Details Button Styles */
                    .animated-view-btn {
                        display: flex;
                        align-items: center;
                        justify-content: flex-start;
                        width: 45px;
                        height: 40px;
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
                        font-size: 1em;
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
                        font-size: 0.85em;
                        font-weight: 600;
                        transition-duration: .3s;
                        white-space: nowrap;
                    }

                    /* Hover effect */
                    .animated-view-btn:hover {
                        width: 120px;
                        transition-duration: .3s;
                        background: linear-gradient(135deg, #000000 0%, #000000 100%);
                    }

                    .animated-view-btn:hover .btn-sign {
                        width: 35%;
                        transition-duration: .3s;
                        padding-left: 12px;
                    }

                    .animated-view-btn:hover .btn-text {
                        opacity: 1;
                        width: 65%;
                        transition-duration: .3s;
                        padding-right: 12px;
                    }

                    /* Click effect */
                    .animated-view-btn:active {
                        transform: translate(1px, 1px);
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                    }

                    /* Mobile adjustments */
                    @media (max-width: 1024px) {
                        .animated-view-btn {
                            width: 42px;
                            height: 38px;
                        }

                        .animated-view-btn .btn-sign {
                            font-size: 0.9em;
                        }

                        .animated-view-btn:hover {
                            width: 100px;
                        }
                    }
                </style>
            </section>


        <?php endif; ?>
    </section>

    <!-- Floating Chatbot Widget -->
    <div id="chatbot-widget" class="fixed bottom-5 right-5 z-50">
        <!-- Compare Button -->
        <button id="compareBtn" onclick="goToComparison()"

            class="fixed bottom-28 right-6 hidden bg-gray-800 text-white px-5 py-2 rounded-full shadow-lg 
           transition-all duration-300 z-40 flex items-center gap-2 text-sm hover:bg-gray-700">
            <span id="compareCount">0</span>
            <span>Compare</span>
        </button>


        <!-- Chatbot Toggle -->
        <button
            id="chatbot-toggle"
            class="fixed bottom-6 right-6 w-16 h-16 bg-orange-400 text-white rounded-full shadow-lg 
           hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center justify-center">

            <!-- Chat Icon -->
            <svg id="chat-icon" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 
              15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>

            <!-- Close Icon -->
            <svg id="close-icon" class="w-8 h-8 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>


        <!-- Chat Container -->
        <div
            id="chatbot-container"
            class="absolute bottom-20 right-0 w-96 h-[500px] bg-white rounded-2xl shadow-2xl transform scale-0 opacity-0 transition-all duration-300 ease-out origin-bottom-right overflow-hidden">
            <!-- Header -->
            <div class="bg-orange-400 text-white p-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                    <h3 class="font-semibold text-lg">Noblehome Assistant</h3>
                </div>
                <button id="minimize-chat" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>

            <!-- Messages Area -->
            <div id="chat-messages" class="h-80 overflow-y-auto p-4 bg-gray-50 space-y-3">
                <!-- Welcome Message -->
                <div class="flex">
                    <div class="bg-white rounded-2xl rounded-bl-sm p-3 max-w-xs shadow-sm">
                        <p class="text-gray-700 text-sm">👋 Hi! I'm your Noblehome product assistant. Ask me about our products, prices, or availability!</p>
                    </div>
                </div>
            </div>

            <!-- Quick Replies -->
            <div class="px-4 py-2 bg-gray-50 border-t border-gray-100">
                <div class="flex flex-wrap gap-2">
                    <button onclick="sendQuickMessage('Show me some products')" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded-full text-xs transition-colors">
                        Show products
                    </button>
                    <button onclick="sendQuickMessage('Best sellers')" class="bg-purple-100 hover:bg-purple-200 text-purple-700 px-3 py-1 rounded-full text-xs transition-colors">
                        Best sellers
                    </button>
                    <button onclick="sendQuickMessage('Under ₱5000')" class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded-full text-xs transition-colors">
                        Under ₱5000
                    </button>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-4 bg-white border-t border-gray-100">
                <div class="flex items-center space-x-2">
                    <input
                        id="chat-input"
                        type="text"
                        placeholder="Ask about products..."
                        class="flex-1 border border-gray-300 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        onkeypress="handleChatKeyPress(event)" />
                    <button
                        id="send-btn"
                        onclick="sendMessage()"
                        class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-2 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .typing-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid hsla(0, 0%, 100%, 1.00);
            border-radius: 50%;
            border-top-color: #e79a25ff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .chat-message img {
            max-width: 150px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin: 4px 0;
        }
    </style>


    <?php if (!$cookieAccepted): ?>
        <section id="cookie-banner" class="fixed bottom-4 left-4 right-4 bg-white border shadow-lg rounded-lg p-4 flex items-center justify-between z-50">
            <p class="text-sm text-gray-700">
                This website uses cookies to personalize content, improve your browsing experience,
                remember your preferences, and analyze site traffic. By clicking "Accept",
                you consent to the use of cookies in accordance with our Privacy Policy.
            </p>

            <form method="post">
                <button type="submit" name="acceptCookies" class="ml-4 bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    Accept
                </button>
            </form>
        </section>
    <?php endif; ?>

    <script>
        let chatOpen = false;

        // Toggle chat
        document.getElementById('chatbot-toggle').addEventListener('click', function() {
            const container = document.getElementById('chatbot-container');
            const chatIcon = document.getElementById('chat-icon');
            const closeIcon = document.getElementById('close-icon');

            if (!chatOpen) {
                container.classList.remove('scale-0', 'opacity-0');
                container.classList.add('scale-100', 'opacity-100');
                chatIcon.classList.add('hidden');
                closeIcon.classList.remove('hidden');
                chatOpen = true;
            } else {
                container.classList.add('scale-0', 'opacity-0');
                container.classList.remove('scale-100', 'opacity-100');
                chatIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
                chatOpen = false;
            }
        });

        // Minimize chat
        document.getElementById('minimize-chat').addEventListener('click', function() {
            document.getElementById('chatbot-toggle').click();
        });

        function handleChatKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

        function sendQuickMessage(message) {
            document.getElementById('chat-input').value = message;
            sendMessage();
        }

        async function sendMessage() {
            const input = document.getElementById('chat-input');
            const messagesContainer = document.getElementById('chat-messages');
            const sendBtn = document.getElementById('send-btn');

            const message = input.value.trim();
            if (!message) return;

            // Add user message
            const userMessage = document.createElement('div');
            userMessage.className = 'flex justify-end';
            userMessage.innerHTML = `
    <div class="bg-blue-600 text-white rounded-2xl rounded-br-sm p-3 max-w-xs">
      <p class="text-sm">${escapeHtml(message)}</p>
    </div>
  `;
            messagesContainer.appendChild(userMessage);

            input.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Show typing indicator
            const typingMessage = document.createElement('div');
            typingMessage.className = 'flex';
            typingMessage.id = 'typing-message';
            typingMessage.innerHTML = `
    <div class="bg-white rounded-2xl rounded-bl-sm p-3 shadow-sm">
      <div class="typing-indicator"></div>
      <span class="ml-2 text-gray-500 text-sm">Thinking...</span>
    </div>
  `;
            messagesContainer.appendChild(typingMessage);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Disable send button
            sendBtn.disabled = true;

            try {
                const response = await fetch('chatbot_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        question: message
                    })
                });

                const data = await response.json();

                // Remove typing indicator
                document.getElementById('typing-message')?.remove();

                // Add bot response
                const botMessage = document.createElement('div');
                botMessage.className = 'flex';

                let reply = '';
                if (data.error) {
                    reply = `Sorry, there was an error: ${data.error}`;
                } else {
                    reply = data.candidates?.[0]?.content?.parts?.[0]?.text || "I couldn't process your request.";
                }

                botMessage.innerHTML = `
      <div class="bg-white rounded-2xl rounded-bl-sm p-3 max-w-xs shadow-sm">
        <div class="text-gray-700 text-sm">${formatChatMessage(reply)}</div>
      </div>
    `;
                messagesContainer.appendChild(botMessage);

            } catch (error) {
                document.getElementById('typing-message')?.remove();

                const errorMessage = document.createElement('div');
                errorMessage.className = 'flex';
                errorMessage.innerHTML = `
      <div class="bg-red-100 text-red-700 rounded-2xl rounded-bl-sm p-3 max-w-xs">
        <p class="text-sm">Sorry, couldn't connect to server.</p>
      </div>
    `;
                messagesContainer.appendChild(errorMessage);
            }

            sendBtn.disabled = false;
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatChatMessage(message) {

            // Convert markdown links
            message = message.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" class="text-blue-600 underline">$1</a>');

            // Convert markdown bold
            message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Convert line breaks
            message = message.replace(/\n/g, '<br>');

            // Format prices
            message = message.replace(/₱(\d+(?:,\d{3})*(?:\.\d{2})?)/g, '<span class="font-semibold text-green-600">₱$1</span>');

            return message;
        }
    </script>

    <style>
        /* Testimonial card */
        .testimonial-card {
            background: white;
            border-radius: 1.25rem;

            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .testimonial-card:hover {
            transform: translateY(-6px);

        }

        /* Decorative gradient strip */
        .testimonial-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;

        }

        /* Profile ring */
        .profile-ring {
            padding: 3px;
            border-radius: 50%;

            display: inline-block;
        }

        /* Name highlight */
        .name-highlight {
            background: linear-gradient(90deg, #ffb006ff, #ffb006ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>

    <!-- Modal for "Not Available" -->
    <div id="unavailableModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-md w-full p-6 transform transition-all">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-neutral-900 mb-2">Not Available</h3>
                <p class="text-sm text-neutral-600 mb-6">This promotion is currently unavailable. Please check back later.</p>
                <button onclick="closeModal()" class="w-full bg-neutral-900 text-white px-4 py-2 rounded-lg hover:bg-neutral-800 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <section class="px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-full mx-auto">
            <!-- 2x2 Grid Content -->
            <div class="grid grid-cols-1">
                <!-- Box 3 -->
                <a href="#" onclick="showUnavailable(event)" class="group block">
                    <div class="overflow-hidden ">
                        <img src="../img/gif2.gif"
                            alt="Promo 3"
                            class="w-full h-auto object-cover transition-transform duration-500 group-hover:scale-105">
                    </div>
                    <h3 class="text-lg font-semibold " style="font-family: 'Montserrat', sans-serif; color: #2f1200">Keep Shopping For Holiday ➜</h3>
                </a>
            </div>
        </div>
    </section>

    <script>
        function showUnavailable(event) {
            event.preventDefault();
            document.getElementById('unavailableModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('unavailableModal').classList.add('hidden');
        }

        document.getElementById('unavailableModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>


    <?php include '../navbar/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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

        // Attach handler to all product forms
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.productForm');
            forms.forEach(form => {
                form.addEventListener('submit', handleProductFormSubmit);
            });
        });

        // 🔔 Notification utility
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            } [type] || 'bg-blue-500';

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

        // 💬 Chat toggle
        function openChat() {
            document.getElementById('chat-box').style.display = 'block';
            document.getElementById('chat-toggle').style.display = 'none';
        }

        function closeChat() {
            document.getElementById('chat-box').style.display = 'none';
            document.getElementById('chat-toggle').style.display = 'inline-block';
        }

        // 🔃 Auto vertical swiper loader
        function initAutoVerticalSwipers() {
            const containers = document.querySelectorAll('[class*="swiper-auto-"]');
            containers.forEach((container, index) => {
                const slides = container.querySelectorAll('.swiper-slide');
                if (slides.length > 0) {
                    new Swiper(container, {
                        direction: 'vertical',
                        loop: slides.length > 1,
                        slidesPerView: 1,
                        spaceBetween: 0,
                        autoplay: slides.length > 1 ? {
                            delay: 3000 + (index * 500),
                            disableOnInteraction: false,
                            pauseOnMouseEnter: false,
                            waitForTransition: true,
                        } : false,
                        speed: 1000,
                        effect: 'slide',
                        on: {
                            init: () => console.log(`Swiper ${index} initialized with ${slides.length} slides`),
                            slideChange: () => console.log(`Swiper ${index} slide changed`)
                        }
                    });
                }
            });
        }
        // Helper function to check if loop should be enabled
        function shouldEnableLoop(selector, slidesPerView) {
            const container = document.querySelector(selector);
            if (!container) return false;

            const slideCount = container.querySelectorAll('.swiper-slide').length;
            // Loop needs at least slidesPerView * 2 slides to work properly
            return slideCount >= slidesPerView * 2;
        }
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Swiper === 'undefined') {
                console.error('Swiper library is not loaded.');
                return;
            }

            // 🏬 DEPARTMENT SWIPER
            const deptSlideCount = document.querySelector('.department-swiper-container')?.querySelectorAll('.swiper-slide').length || 0;
            initSwiper('.department-swiper-container', {
                slidesPerView: 2,
                spaceBetween: 12,
                centeredSlides: false,
                loop: deptSlideCount >= 4,
                grabCursor: true,
                navigation: {
                    nextEl: '.department-next-btn',
                    prevEl: '.department-prev-btn',
                },
                autoplay: deptSlideCount > 2 ? {
                    delay: 3500,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true
                } : false,
                breakpoints: {
                    480: {
                        slidesPerView: 2.5,
                        spaceBetween: 14,
                        loop: deptSlideCount >= 5
                    },
                    640: {
                        slidesPerView: 3,
                        spaceBetween: 16,
                        loop: deptSlideCount >= 6
                    },
                    768: {
                        slidesPerView: 4,
                        spaceBetween: 16,
                        loop: deptSlideCount >= 8
                    },
                    1024: {
                        slidesPerView: 5,
                        spaceBetween: 18,
                        loop: deptSlideCount >= 10
                    },
                    1280: {
                        slidesPerView: 6,
                        spaceBetween: 20,
                        loop: deptSlideCount >= 12
                    },
                    1536: {
                        slidesPerView: 7,
                        spaceBetween: 20,
                        loop: deptSlideCount >= 14
                    }
                }
            });

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

            // 💎 MATERIALS SWIPER
            const materialSlideCount = document.querySelector('.mySwiper-material')?.querySelectorAll('.swiper-slide').length || 0;
            initSwiper('.mySwiper-material', {
                slidesPerView: 2,
                spaceBetween: 8,
                loop: materialSlideCount >= 4,
                autoplay: materialSlideCount > 2 ? {
                    delay: 2500,
                    disableOnInteraction: false
                } : false,
                breakpoints: {
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 10,
                        loop: materialSlideCount >= 4
                    },
                    640: {
                        slidesPerView: 2,
                        spaceBetween: 12,
                        loop: materialSlideCount >= 4
                    },
                    768: {
                        slidesPerView: 4,
                        spaceBetween: 12,
                        loop: materialSlideCount >= 6
                    },
                    1024: {
                        slidesPerView: 4,
                        spaceBetween: 15,
                        loop: materialSlideCount >= 8
                    },
                    1280: {
                        slidesPerView: 4,
                        spaceBetween: 18,
                        loop: materialSlideCount >= 8
                    },
                    1536: {
                        slidesPerView: 8,
                        spaceBetween: 20,
                        loop: materialSlideCount >= 16
                    }
                }
            });


            // Auto vertical swipers
            initAutoVerticalSwipers();

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

            // Recommended Products Swiper - SAME PATTERN!
            const recommendedSlideCount = document.querySelector('.mySwiper-recommended')?.querySelectorAll('.swiper-slide').length || 0;
            if (recommendedSlideCount > 0) {
                initSwiper('.mySwiper-recommended', {
                    slidesPerView: 2,
                    spaceBetween: 10,
                    loop: recommendedSlideCount >= 4,
                    autoplay: false,
                    navigation: {
                        nextEl: '.swiper-button-next-recommended',
                        prevEl: '.swiper-button-prev-recommended',
                    },
                    pagination: {
                        el: '.swiper-pagination-recommended',
                        clickable: true,
                    },
                    breakpoints: {
                        480: {
                            slidesPerView: 2,
                            spaceBetween: 12,
                            loop: recommendedSlideCount >= 4
                        },
                        640: {
                            slidesPerView: 3,
                            spaceBetween: 15,
                            loop: recommendedSlideCount >= 6
                        },
                        768: {
                            slidesPerView: 4,
                            spaceBetween: 15,
                            loop: recommendedSlideCount >= 8
                        },
                        1024: {
                            slidesPerView: 4,
                            spaceBetween: 18,
                            loop: recommendedSlideCount >= 10
                        },
                        1536: {
                            slidesPerView: 5,
                            spaceBetween: 25,
                            loop: recommendedSlideCount >= 14
                        }
                    }
                });
            }
        });
    </script>

</body>

</html>