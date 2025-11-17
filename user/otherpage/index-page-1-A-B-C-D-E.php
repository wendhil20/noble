<?php
ob_start();
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
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
        ON pc.product_id = p.id
       AND pc.id = (
           SELECT MIN(pc2.id) 
           FROM product_colors pc2 
           WHERE pc2.product_id = p.id
       )
    WHERE pv.discount = 10
    GROUP BY pv.id
    ORDER BY p.view_count DESC, RAND()
    LIMIT 10
";
$material_results = mysqli_query($conn, $material_querys);

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
     GROUP BY pv.id
     ORDER BY p.view_count DESC, RAND()
     LIMIT 10"
);

// 7. Filter by furniture codename - WITH VIEW COUNT & RATING & SOLD COUNT & PRICE RANGE
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
      COALESCE(MIN(pv.price), 0) as min_size_price,  
      COALESCE(MAX(pv.price), 0) as max_size_price,  
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
    GROUP BY p.id
    ORDER BY p.view_count DESC, RAND()
    LIMIT 10";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$result = $stmt->get_result();

// 8. Filter by material codename - WITH PRICE RANGE
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
     COALESCE(MIN(pv.price), 0) as min_size_price, 
COALESCE(MAX(pv.price), 0) as max_size_price, 
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
    GROUP BY p.id
    ORDER BY p.view_count DESC, RAND()
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$results = $stmt->get_result();

// 9. Filter by bedfurniture - WITH SUBCATEGORIES (BETTER APPROACH)
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
        -- Get the first variant's subcategory_name (or aggregate all unique ones)
        (SELECT subcategory_name 
         FROM product_variants 
         WHERE product_id = p.id 
         LIMIT 1) as subcategory_name,
        COALESCE(MIN(pv.price), 0) as min_size_price,
        COALESCE(MAX(pv.price), 0) as max_size_price,  
        COALESCE(MIN(pc.price), 0) as min_color_price,
        COALESCE(MAX(pc.price), 0) as max_color_price,
        COUNT(DISTINCT pc.id) as color_count,
        COALESCE(SUM(si.quantity), 0) AS total_sold
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN sold_items si ON si.product_id = p.id
    WHERE p.codename = ?
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
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="preconnect" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../css/promotionslide.css" rel="stylesheet">
    <link href="../css/bannerPromo.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link href="https://unpkg.com/aos@next/dist/aos.css" rel="stylesheet" />
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
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

        .swiper-pagination-bullet-active {
            background: #ffffffff !important;
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
    <div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-1">

        <!-- Main Slider - Takes 8 columns -->
        <div class="lg:col-span-8 relative overflow-hidden bg-gray-900">
            <div class="mySwiper h-[250px] sm:h-[350px] lg:h-[400px]">
                <div class="swiper-wrapper">
                    <?php if (!empty($banners)): ?>
                        <?php foreach ($banners as $idx => $banner): ?>
                            <a href="../otherpage/index-subcategory_grid_page-14.php?category_name=<?= urlencode(strtolower($banner['category_name'])) ?>"
                                class="swiper-slide block cursor-pointer hover:opacity-90 transition-opacity group">
                                <div class="relative w-full h-full overflow-hidden">
                                    <img src="../../uploads/<?= basename($banner['filename']) ?>"
                                        alt="<?= htmlspecialchars($banner['category_name'] ?? 'Banner') ?>"
                                        class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500"
                                        onerror="this.src='../../uploads/placeholder.jpg'" />
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide bg-gray-800 flex items-center justify-center">
                            <p class="text-gray-400">No banners available</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>

        <!-- Right Side Grid - Takes 4 columns, split into 2 cards -->
        <div class="lg:col-span-4 grid grid-cols-2 lg:grid-cols-1 gap-1">

            <!-- Card 1: Recent Views -->
            <a href="#recent" class="relative overflow-hidden h-[125px] sm:h-[175px] lg:h-[197px] group  transition-all ">
                <div class="absolute inset-0">
                    <img src="../img/gif1.gif" alt="Recent" class="w-full h-full object-cover mix-blend-overlay" />
                </div>
                <div class="relative h-full flex flex-col justify-end p-4 bg-gradient-to-t from-black/60 to-transparent">
                    <div class="text-white/80 text-[10px] uppercase mb-1 font-semibold">Quick Access</div>
                    <div class="text-white font-bold text-sm group-hover:text-orange-400 transition-colors">Recent View →</div>
                </div>
            </a>

            <!-- Card 2: Deals - Links to first banner category -->
            <a href="<?= !empty($banners) ? '../otherpage/index-subcategory_grid_page-14.php?category_name=' . urlencode(strtolower($banners[0]['category_name'])) : '#' ?>"
                class="relative overflow-hidden h-[125px] sm:h-[175px] lg:h-[197px] group transition-all ">
                <div class="absolute top-2 right-2 bg-yellow-400 text-black text-[9px] font-black px-2 py-1  z-10">
                    HOT
                </div>
                <div class="absolute inset-0">
                    <?php if (!empty($banners)): ?>
                        <img src="../../uploads/<?= basename($banners[0]['filename']) ?>"
                            alt="<?= htmlspecialchars($banners[0]['category_name']) ?>"
                            class="w-full h-full object-cover"
                            onerror="this.src='../../uploads/placeholder.jpg'" />
                    <?php else: ?>
                        <img src="../img/gif1.gif" alt="Deals" class="w-full h-full object-cover mix-blend-overlay" />
                    <?php endif; ?>
                </div>
                <div class="relative h-full flex flex-col justify-end p-4 bg-gradient-to-t from-black/60 to-transparent">
                    <div class="text-white/80 text-[10px] uppercase mb-1 font-semibold">Limited Time</div>
                    <div class="text-white font-bold text-sm group-hover:text-orange-400 transition-colors">
                        <?= !empty($banners) ? htmlspecialchars($banners[0]['category_name']) : 'Holiday Deals' ?> →
                    </div>
                </div>
            </a>
        </div>
    </div>

    <section class="bg-black hidden md:block border border-black/20">
        <div class="px-4 sm:px-8 lg:px-9">
            <!-- Clickable Banner Image - Left Aligned -->
            <a href="index-shop-page-2.php" class="block hover:opacity-90 transition-opacity duration-300 w-fit">
                <img src="../img/exclusive1.png"
                    alt="Exclusive Discounts - Shop Now"
                    class="h-auto object-contain max-h-[30px] sm:max-h-[40px] md:max-h-[50px] lg:max-h-[60px]">
            </a>
        </div>
    </section>


    <section class="py-3 tracking-wide px-4 hidden md:block bg-gray-100">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <!-- Fast Delivery -->
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <div>
                    <h3 class="text-base text-black font-bold">Fast Delivery</h3>
                    <p class="text-sm text-gray-500">Quick and reliable shipping</p>
                </div>
            </div>

            <!-- High Quality -->
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h3 class="text-base text-black font-bold ">High Quality</h3>
                    <p class="text-sm text-gray-500">Products you can trust</p>
                </div>
            </div>

            <!-- Affordable Prices -->
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <div>
                    <h3 class="text-base text-black font-bold">Affordable Prices</h3>
                    <p class="text-sm text-gray-500">Great value for your money</p>
                </div>
            </div>

            <!-- Secure Checkout -->
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div>
                    <h3 class="text-base text-black font-bold">Secure Checkout</h3>
                    <p class="text-sm text-gray-500">Safe and easy payments</p>
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
        // 🔥 USE SMART PRICE DISPLAY FUNCTION
        $priceData = calculateSmartPriceDisplay($row);

        // Get discount info
        $discount = (float)($row['discount'] ?? 0);
        $percent = (float)($row['percent'] ?? 0);

        $product_id = (int)$row['id'];

        // ✅ Get rating
        $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
        $rating_q->bind_param("i", $product_id);
        $rating_q->execute();
        $rating_result = $rating_q->get_result()->fetch_assoc();
        $avg_rating = $rating_result['avg_rating'] ?? 0;
        $total_raters = $rating_result['total_raters'] ?? 0;
        $rating_q->close();

        // 🆕 Get sold count
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
                    <h3 class="text-[13px] font-medium text-gray-900 group-hover:text-blue-600 transition-colors mb-1.5 line-clamp-2">
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

                    <!-- 🔥 SMART PRICE DISPLAY -->
                    <div class="flex items-baseline gap-1 flex-wrap mb-1 mt-auto">
                        <?php if ($discount > 0): ?>
                            <!-- With Discount -->
                            <p class="text-md font-bold text-gray-900">
                                <?= $priceData['display_price'] ?>
                            </p>
                            <span class="text-[9px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">-<?= number_format($discount, 0) ?>%</span>
                        <?php else: ?>
                            <!-- No Discount -->
                            <p class="text-xs font-bold text-gray-900">
                                <?= $priceData['display_price'] ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- View count + Sold count -->
                    <?php if (!empty($row['view_count']) || $total_sold > 0): ?>
                        <div class="text-[9px] text-gray-500 font-medium">
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
                            <div class="w-1 h-6 bg-neutral-900"></div>
                            <h2 class="text-base sm:text-xl font-bold text-neutral-900">Recently Viewed</h2>
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
                                <div class="w-1 h-6 bg-black"></div>
                                <h2 class="text-base sm:text-xl font-bold text-neutral-900">Recommended for You</h2>
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

    <section class="px-4 sm:px-6 lg:px-8 py-10 ">
        <div class="max-w-full mx-auto">
            <!-- Banner -->
            <a href="https://www.yfk20.com/login" class="group block relative overflow-hidden transition-all duration-300 pointer-events-none cursor-not-allowed" data-aos="fade-up">
                <!-- Rectangle Container -->
                <div class="relative w-full aspect-[16/9] sm:aspect-auto sm:h-auto bg-white border border-neutral-200 rounded-2xl overflow-hidden">
                    <!-- Skeleton Loading -->
                    <div class="skeleton-loader absolute inset-0 bg-gradient-to-r from-neutral-200 via-neutral-300 to-neutral-200 bg-[length:200%_100%] animate-shimmer sm:relative sm:max-h-[220px] md:max-h-[280px] lg:max-h-[380px] xl:max-h-[500px]"></div>

                    <!-- Image -->
                    <img src="../img/sunpina.png"
                        alt="Promotion Banner"
                        class="banner-image absolute inset-0 w-full h-full object-cover sm:relative sm:object-contain sm:max-h-[220px] md:max-h-[280px] lg:max-h-[380px] xl:max-h-[500px] opacity-0 transition-opacity duration-300"
                        onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';">

                    <!-- Temporary Overlay Badge -->
                    <div class="absolute inset-0 flex items-center justify-center z-10">
                        <div class="bg-orange-500 text-white px-5 py-2 sm:px-6 sm:py-3 rounded-lg shadow-2xl transform rotate-[-5deg]">
                            <p class="text-lg sm:text-xl md:text-xl lg:text-2xl font-bold">temporary</p>
                        </div>
                    </div>
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

    <section>
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

        <script>
            function showUnavailable(event) {
                event.preventDefault();
                document.getElementById('unavailableModal').classList.remove('hidden');
            }

            function closeModal() {
                document.getElementById('unavailableModal').classList.add('hidden');
            }

            // Close modal when clicking outside
            document.getElementById('unavailableModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        </script>

        <section class=" px-4 sm:px-6 lg:px-8">
            <div class="max-w-full mx-auto">
                <!-- 2x2 Grid Content -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <!-- Box 1 -->
                    <a href="#" onclick="showUnavailable(event)" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
                        <div class="relative h-[240px] sm:h-[300px] lg:h-[380px] overflow-hidden bg-neutral-100">
                            <img src="../img/display2.webp"
                                alt="Promo 1"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                            <!-- Dark Overlay on Hover -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-500"></div>

                            <!-- Text Overlay -->
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent p-5 sm:p-6 lg:p-8">
                                <h3 class="text-base sm:text-lg lg:text-xl font-light text-white flex items-center gap-2 transform translate-y-2 group-hover:translate-y-0 transition-transform duration-500">
                                    Style to Modern your house!
                                    <svg class="w-5 h-5 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </h3>
                            </div>
                        </div>
                    </a>

                    <!-- Box 2 -->
                    <a href="#" onclick="showUnavailable(event)" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
                        <div class="relative h-[240px] sm:h-[300px] lg:h-[380px] overflow-hidden bg-neutral-100">
                            <img src="../img/display1.webp"
                                alt="Promo 2"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                            <!-- Dark Overlay on Hover -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-500"></div>

                            <!-- Text Overlay -->
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent p-5 sm:p-6 lg:p-8">
                                <h3 class="text-base sm:text-lg lg:text-xl font-light text-white flex items-center gap-2 transform translate-y-2 group-hover:translate-y-0 transition-transform duration-500">
                                    Chair furniture deals below ₱5,000
                                    <svg class="w-5 h-5 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </h3>
                            </div>
                        </div>
                    </a>

                    <!-- Box 3 -->
                    <a href="#" onclick="showUnavailable(event)" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
                        <div class="relative h-[240px] sm:h-[300px] lg:h-[380px] overflow-hidden bg-neutral-100">
                            <img src="../img/gif2.gif"
                                alt="Promo 3"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                            <!-- Dark Overlay on Hover -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-500"></div>

                            <!-- Text Overlay -->
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent p-5 sm:p-6 lg:p-8">
                                <h3 class="text-base sm:text-lg lg:text-xl font-light text-white flex items-center gap-2 transform translate-y-2 group-hover:translate-y-0 transition-transform duration-500">
                                    Keep Shopping For Holiday
                                    <svg class="w-5 h-5 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </h3>
                            </div>
                        </div>
                    </a>

                    <!-- Box 4 -->
                    <a href="#" onclick="showUnavailable(event)" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
                        <div class="relative h-[240px] sm:h-[300px] lg:h-[380px] overflow-hidden bg-neutral-100">
                            <img src="../img/display3.webp"
                                alt="Promo 4"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                            <!-- Dark Overlay on Hover -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-500"></div>

                            <!-- Text Overlay -->
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent p-5 sm:p-6 lg:p-8">
                                <h3 class="text-base sm:text-lg lg:text-xl font-light text-white flex items-center gap-2 transform translate-y-2 group-hover:translate-y-0 transition-transform duration-500">
                                    Upgrade Your Living Space Today!
                                    <svg class="w-5 h-5 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </h3>
                            </div>
                        </div>
                    </a>

                </div>
            </div>
        </section>
    </section>

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
                    <h2 class="text-lg md:text-2xl lg:text-3xl text-black">
                        Shop by Department
                    </h2>
                    <p class="text-gray-600 text-xs md:text-sm mt-1">
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
                                <h3 class="text-xs sm:text-sm md:text-base font-light text-gray-900 text-center group-hover:text-blue-600 transition-colors uppercase">
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
                            <h3 class="text-xs sm:text-sm md:text-base font-light text-gray-900 text-center group-hover:text-blue-600 transition-colors uppercase">
                                See More
                            </h3>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="px-2 sm:px-4 lg:px-6 py-1 sm:py-1 mt-4">
        <!-- Main Container with Border -->
        <div class="bg-white p-4 sm:p-6">
            <!-- Grid Layout: Left (Furniture) and Right (Bed Furniture) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">

                <!-- LEFT SIDE: FURNITURE -->
                <div class="space-y-4 border-r-0 lg:border-r-2 lg:border-gray-200 lg:pr-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-8 bg-neutral-900"></div>
                            <h2 class="text-xl sm:text-2xl lg:text-3xl font-light text-neutral-900 tracking-tight">
                                Furniture
                            </h2>
                        </div>
                        <a href="#" class="text-xs sm:text-sm text-neutral-900 hover:text-neutral-600 font-light flex items-center gap-1 transition-colors duration-300 group">
                            See All
                            <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                            </svg>
                        </a>
                    </div>

                    <!-- Label: Suggestions -->
                    <div class="mb-3">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-medium mb-2">

                            Suggestions for you
                        </p>
                    </div>

                    <!-- ✅ UPDATED: Filter Buttons now link to Recommendations Page -->
                    <div class="flex flex-wrap gap-2 mb-3">
                        <?php
                        // Get 3 random subcategories for Furniture (category_id = 1)
                        $furniture_cat_id = 1;

                        // First get the category name
                        $cat_name_query = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                        $cat_name_query->bind_param("i", $furniture_cat_id);
                        $cat_name_query->execute();
                        $cat_name_result = $cat_name_query->get_result();
                        $furniture_cat_name = $cat_name_result->fetch_assoc()['name'] ?? 'furniture';
                        $cat_name_query->close();

                        $sub_query = $conn->prepare("
            SELECT DISTINCT id, subcategory_name 
            FROM product_subcategories 
            WHERE category_id = ? 
            ORDER BY RAND() 
            LIMIT 3
        ");
                        $sub_query->bind_param("i", $furniture_cat_id);
                        $sub_query->execute();
                        $sub_result = $sub_query->get_result();

                        while ($sub_row = $sub_result->fetch_assoc()):
                            $sub_name = $sub_row['subcategory_name'];
                            $sub_id = $sub_row['id'];
                        ?>
                            <!-- ✅ CHANGED: Now goes to Recommendations page with visual indicator -->
                            <a href="../otherpage/index-subcategory-recommendations-page-15.php?subcategory_id=<?= $sub_id ?>&from=home"
                                class="furniture-filter-btn px-4 py-2.5 text-sm font-semibold rounded-lg border-2 transition-all duration-300 hover:shadow-md hover:scale-105 inline-flex items-center gap-2 group">

                                <?= ucfirst(htmlspecialchars($sub_name)) ?>
                            </a>
                        <?php endwhile; ?>
                    </div>

                    <!-- Product Swiper -->
                    <div class="swiper mySwiper-furniture w-full">
                        <div class="swiper-wrapper">
                            <?php
                            mysqli_data_seek($result, 0);
                            while ($row = mysqli_fetch_assoc($result)) :
                                $priceData = calculateSmartPriceDisplay($row);
                                $discount = (float)($row['discount'] ?? 0);
                                $product_id = (int)$row['id'];

                                $subcategory_name = $row['subcategory_name'] ?? 'uncategorized';
                                $sub_slug = strtolower(str_replace(' ', '-', $subcategory_name));

                                $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                                $rating_q->bind_param("i", $product_id);
                                $rating_q->execute();
                                $rating_result = $rating_q->get_result()->fetch_assoc();
                                $avg_rating = $rating_result['avg_rating'] ?? 0;
                                $total_raters = $rating_result['total_raters'] ?? 0;
                                $rating_q->close();

                                $sold_q = $conn->prepare("SELECT SUM(quantity) as total_sold FROM sold_items WHERE product_id = ?");
                                $sold_q->bind_param("i", $product_id);
                                $sold_q->execute();
                                $sold_result = $sold_q->get_result()->fetch_assoc();
                                $total_sold = (int)($sold_result['total_sold'] ?? 0);
                                $sold_q->close();

                                $full = floor($avg_rating);
                                $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                $empty = 5 - $full - $half;
                                $view_count = (int)($row['view_count'] ?? 0);
                            ?>
                                <div class="swiper-slide p-1 furniture-item" data-subcategory="<?= $sub_slug ?>">
                                    <div class="relative rounded overflow-hidden group hover:shadow-2xl transition-all duration-500 ease-out h-[240px] sm:h-[280px] lg:h-[340px] bg-white">
                                        <div class="absolute top-2 right-2 z-20">
                                            <label class="flex items-center justify-center w-7 h-7 bg-white hover:bg-gray-100 rounded cursor-pointer transition-all duration-300 hover:scale-110 shadow">
                                                <input type="checkbox" class="compare-checkbox hidden" data-product-id="<?= $product_id ?>" onchange="toggleCompare(this, <?= $product_id ?>)">
                                                <i class="fas fa-plus text-black text-xs compare-icon"></i>
                                                <i class="fas fa-check text-black text-xs compare-icon-checked hidden"></i>
                                            </label>
                                        </div>

                                        <a href="index-product_view-page-4-AA?id=<?= $product_id ?>" class="block h-full">
                                            <div class="relative h-[110px] sm:h-[130px] lg:h-[180px] overflow-hidden">
                                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>
                                                <?php if (!empty($row['main_image'])): ?>
                                                    <img src="../../<?= $row['main_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['product_name']) ?>" class="w-full h-full object-cover sm:object-contain transition-all duration-700 group-hover:brightness-105" />
                                                <?php else: ?>
                                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                                        <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="p-1.5 sm:p-2 lg:p-2.5 flex flex-col justify-between h-[130px] sm:h-[150px] lg:h-[160px]">
                                                <div class="space-y-0.5 sm:space-y-1">
                                                    <div class="relative w-full max-w-xs">
                                                        <h3 class="text-[13px] sm:text-[13px] line-clamp-1 font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 line-clamp-2 sm:truncate pr-4">
                                                            <?= htmlspecialchars($row['product_name']) ?>
                                                        </h3>
                                                        <div class="hidden sm:block absolute top-0 right-0 h-full w-4 bg-gradient-to-l from-white to-transparent"></div>
                                                    </div>

                                                    <div class="flex items-center justify-between">
                                                        <?php if ($total_raters > 0): ?>
                                                            <div class="flex items-center space-x-0.5">
                                                                <div class="flex text-yellow-400 text-[8px] sm:text-[9px]">
                                                                    <?php
                                                                    for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                                                    if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                                                    for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                                                                    ?>
                                                                </div>
                                                                <span class="text-[8px] sm:text-[9px] text-gray-500 font-medium"><?= $avg_rating ?></span>
                                                            </div>
                                                            <span class="text-[8px] sm:text-[9px] text-gray-400">(<?= $total_raters ?>)</span>
                                                        <?php else: ?>
                                                            <div class="flex items-center space-x-0.5">
                                                                <div class="flex text-gray-300 text-[8px] sm:text-[9px]">
                                                                    <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                                </div>
                                                                <span class="text-[8px] sm:text-[9px] text-gray-400">No rating</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($row['description'])): ?>
                                                        <p class="text-[13px] sm:text-[13px] text-gray-600 leading-relaxed line-clamp-1 sm:line-clamp-2">
                                                            <?= htmlspecialchars($row['description']) ?>
                                                        </p>
                                                    <?php else: ?>
                                                        <p class="text-[8px] sm:text-[9px] text-gray-400 italic">No description</p>
                                                    <?php endif; ?>

                                                    <div class="flex items-baseline gap-1 flex-wrap">
                                                        <?php if ($discount > 0): ?>
                                                            <p class="text-[11px] font-bold text-gray-900"><?= $priceData['display_price'] ?></p>
                                                            <span class="text-[8px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">-<?= number_format($discount, 0) ?>%</span>
                                                        <?php else: ?>
                                                            <p class="text-[11px] font-bold text-gray-900"><?= $priceData['display_price'] ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="mt-2 flex items-center justify-between gap-2 border-t border-gray-100 pt-2">
                                                    <form action="index-product_view-page-4-AA" method="GET" class="pointer-events-auto hidden sm:block" onclick="event.stopPropagation()">
                                                        <input type="hidden" name="id" value="<?= $product_id ?>">
                                                        <button type="submit" class="flex items-center gap-1 text-black hover:text-orange-500 transition font-medium text-[11px] px-2 py-1 border border-gray-300 rounded hover:border-orange-500">
                                                            <i class="fa-solid fa-bag-shopping text-[11px]"></i>
                                                            <span>View</span>
                                                        </button>
                                                    </form>

                                                    <div class="flex items-center gap-2 text-[9px] text-gray-500">
                                                        <?php if ($view_count > 0): ?>
                                                            <div class="flex items-center gap-1 bg-blue-50 px-2 py-1 rounded">
                                                                viewing
                                                                <span class="font-medium"><?= formatViewCount($view_count) ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($total_sold > 0): ?>
                                                            <div class="flex items-center gap-1 bg-green-50 px-2 py-1 rounded">
                                                                Sold
                                                                <span class="font-medium"><?= number_format($total_sold) ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE: BED FURNITURE -->
                <div class="space-y-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-8 bg-neutral-900"></div>
                            <h2 class="text-xl sm:text-2xl lg:text-3xl font-light text-neutral-900 tracking-tight">
                                Tiles
                            </h2>
                        </div>
                        <a href="#" class="text-xs sm:text-sm text-neutral-900 hover:text-neutral-600 font-light flex items-center gap-1 transition-colors duration-300 group">
                            See All
                            <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                            </svg>
                        </a>
                    </div>

                    <!-- Label: Suggestions -->
                    <div class="mb-3">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-medium mb-2">

                            Suggestions for you
                        </p>
                    </div>

                    <!-- ✅ UPDATED: Filter Buttons now link to Recommendations Page -->
                    <div class="flex flex-wrap gap-2 mb-3">
                        <?php
                        // Get subcategories for Tiles/Aircon from actual product variants
                        $tiles_cat_query = $conn->prepare("SELECT id FROM categories WHERE name LIKE '%aircon%' OR name LIKE '%tiles%' LIMIT 1");
                        $tiles_cat_query->execute();
                        $tiles_cat_result = $tiles_cat_query->get_result();

                        if ($tiles_cat_row = $tiles_cat_result->fetch_assoc()) {
                            $tiles_cat_id = $tiles_cat_row['id'];
                            $tiles_cat_query->close();

                            // Get 3 random unique subcategories with their IDs
                            $tiles_sub_query = $conn->prepare("
                SELECT DISTINCT ps.id, ps.subcategory_name
                FROM product_subcategories ps
                WHERE ps.category_id = ?
                ORDER BY RAND()
                LIMIT 3
            ");
                            $tiles_sub_query->bind_param("i", $tiles_cat_id);
                            $tiles_sub_query->execute();
                            $tiles_sub_result = $tiles_sub_query->get_result();

                            // ✅ CHANGED: Display filter buttons as RECOMMENDATIONS LINKS
                            while ($tiles_sub_row = $tiles_sub_result->fetch_assoc()):
                                $tiles_sub_name = $tiles_sub_row['subcategory_name'];
                                $tiles_sub_id = $tiles_sub_row['id'];
                        ?>
                                <a href="../otherpage/index-subcategory-recommendations-page-15.php?subcategory_id=<?= $tiles_sub_id ?>&from=home"
                                    class="bed-filter-btn px-4 py-2.5 text-sm font-semibold rounded-lg border-2 transition-all duration-300 hover:shadow-md hover:scale-105 inline-flex items-center gap-2 group">

                                    <?= ucfirst(htmlspecialchars($tiles_sub_name)) ?>
                                </a>
                        <?php
                            endwhile;
                            $tiles_sub_query->close();
                        } else {
                            $tiles_cat_query->close();
                        }
                        ?>
                    </div>


                    <!-- Product Swiper -->
                    <div class="swiper mySwiper-bedfurniture w-full">
                        <div class="swiper-wrapper">
                            <?php
                            if (isset($resultss) && $resultss) {
                                mysqli_data_seek($resultss, 0);
                                while ($row = mysqli_fetch_assoc($resultss)) :
                                    $priceData = calculateSmartPriceDisplay($row);
                                    $discount = (float)($row['discount'] ?? 0);
                                    $product_id = (int)$row['id'];

                                    $subcategory_name = $row['subcategory_name'] ?? 'uncategorized';
                                    $sub_slug = strtolower(str_replace(' ', '-', $subcategory_name));

                                    $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                                    $rating_q->bind_param("i", $product_id);
                                    $rating_q->execute();
                                    $rating_result = $rating_q->get_result()->fetch_assoc();
                                    $avg_rating = $rating_result['avg_rating'] ?? 0;
                                    $total_raters = $rating_result['total_raters'] ?? 0;
                                    $rating_q->close();

                                    $sold_q = $conn->prepare("SELECT SUM(quantity) as total_sold FROM sold_items WHERE product_id = ?");
                                    $sold_q->bind_param("i", $product_id);
                                    $sold_q->execute();
                                    $sold_result = $sold_q->get_result()->fetch_assoc();
                                    $total_sold = (int)($sold_result['total_sold'] ?? 0);
                                    $sold_q->close();

                                    $full = floor($avg_rating);
                                    $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                    $empty = 5 - $full - $half;
                                    $view_count = (int)($row['view_count'] ?? 0);
                            ?>
                                    <div class="swiper-slide p-1 bed-item" data-subcategory="<?= $sub_slug ?>">
                                        <div class="relative rounded overflow-hidden group hover:shadow-2xl transition-all duration-500 ease-out h-[240px] sm:h-[280px] lg:h-[340px] bg-white">
                                            <div class="absolute top-2 right-2 z-20">
                                                <label class="flex items-center justify-center w-7 h-7 bg-white hover:bg-gray-100 rounded cursor-pointer transition-all duration-300 hover:scale-110 shadow">
                                                    <input type="checkbox" class="compare-checkbox hidden" data-product-id="<?= $product_id ?>" onchange="toggleCompare(this, <?= $product_id ?>)">
                                                    <i class="fas fa-plus text-black text-xs compare-icon"></i>
                                                    <i class="fas fa-check text-black text-xs compare-icon-checked hidden"></i>
                                                </label>
                                            </div>

                                            <a href="index-product_view-page-4-AA?id=<?= $product_id ?>" class="block h-full">
                                                <div class="relative h-[110px] sm:h-[130px] lg:h-[180px] overflow-hidden">
                                                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>
                                                    <?php if (!empty($row['main_image'])): ?>
                                                        <img src="../../<?= $row['main_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['product_name']) ?>" class="w-full h-full object-cover sm:object-contain transition-all duration-700 group-hover:brightness-105" />
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                                            <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="p-1.5 sm:p-2 lg:p-2.5 flex flex-col justify-between h-[130px] sm:h-[150px] lg:h-[160px]">
                                                    <div class="space-y-0.5 sm:space-y-1">
                                                        <div class="relative w-full max-w-xs">
                                                            <h3 class="text-[13px] sm:text-[13px] line-clamp-1 font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 line-clamp-2 sm:truncate pr-4">
                                                                <?= htmlspecialchars($row['product_name']) ?>
                                                            </h3>
                                                            <div class="hidden sm:block absolute top-0 right-0 h-full w-4 bg-gradient-to-l from-white to-transparent"></div>
                                                        </div>

                                                        <div class="flex items-center justify-between">
                                                            <?php if ($total_raters > 0): ?>
                                                                <div class="flex items-center space-x-0.5">
                                                                    <div class="flex text-yellow-400 text-[8px] sm:text-[9px]">
                                                                        <?php
                                                                        for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                                                        if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                                                        for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                                                                        ?>
                                                                    </div>
                                                                    <span class="text-[8px] sm:text-[9px] text-gray-500 font-medium"><?= $avg_rating ?></span>
                                                                </div>
                                                                <span class="text-[8px] sm:text-[9px] text-gray-400">(<?= $total_raters ?>)</span>
                                                            <?php else: ?>
                                                                <div class="flex items-center space-x-0.5">
                                                                    <div class="flex text-gray-300 text-[8px] sm:text-[9px]">
                                                                        <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                                    </div>
                                                                    <span class="text-[8px] sm:text-[9px] text-gray-400">No rating</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if (!empty($row['description'])): ?>
                                                            <p class="text-[13px] sm:text-[13px] text-gray-600 leading-relaxed line-clamp-1 sm:line-clamp-2">
                                                                <?= htmlspecialchars($row['description']) ?>
                                                            </p>
                                                        <?php else: ?>
                                                            <p class="text-[8px] sm:text-[9px] text-gray-400 italic">No description</p>
                                                        <?php endif; ?>

                                                        <div class="flex items-baseline gap-1 flex-wrap">
                                                            <?php if ($discount > 0): ?>
                                                                <p class="text-[11px] font-bold text-gray-900"><?= $priceData['display_price'] ?></p>
                                                                <span class="text-[8px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">-<?= number_format($discount, 0) ?>%</span>
                                                            <?php else: ?>
                                                                <p class="text-[11px] font-bold text-gray-900"><?= $priceData['display_price'] ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="mt-2 flex items-center justify-between gap-2 border-t border-gray-100 pt-2">
                                                        <form action="index-product_view-page-4-AA" method="GET" class="pointer-events-auto hidden sm:block" onclick="event.stopPropagation()">
                                                            <input type="hidden" name="id" value="<?= $product_id ?>">
                                                            <button type="submit" class="flex items-center gap-1 text-black hover:text-orange-500 transition font-medium text-[11px] px-2 py-1 border border-gray-300 rounded hover:border-orange-500">
                                                                <i class="fa-solid fa-bag-shopping text-[11px]"></i>
                                                                <span>View</span>
                                                            </button>
                                                        </form>

                                                        <div class="flex items-center gap-2 text-[9px] text-gray-500">
                                                            <?php if ($view_count > 0): ?>
                                                                <div class="flex items-center gap-1 bg-blue-50 px-2 py-1 rounded">
                                                                    viewing
                                                                    <span class="font-medium"><?= formatViewCount($view_count) ?></span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if ($total_sold > 0): ?>
                                                                <div class="flex items-center gap-1 bg-green-50 px-2 py-1 rounded">
                                                                    sold
                                                                    <span class="font-medium"><?= number_format($total_sold) ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                            <?php
                                endwhile;
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <style>
                    /* Improved Button Styles */
                    .bed-filter-btn {
                        border-color: #d1d5db;
                        background: white;
                        color: #4b5563;
                        text-decoration: none;
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                    }


                    .compare-checkbox:checked~.compare-icon {
                        display: none !important;
                    }

                    .compare-checkbox:checked~.compare-icon-checked {
                        display: inline-block !important;
                    }

                    .compare-icon-checked {
                        display: none;
                    }

                    .compare-checkbox:checked~.compare-icon,
                    .compare-checkbox:checked~.compare-icon-checked {
                        animation: scaleIn 0.3s ease;
                    }

                    @keyframes scaleIn {
                        0% {
                            transform: scale(0);
                        }

                        50% {
                            transform: scale(1.2);
                        }

                        100% {
                            transform: scale(1);
                        }
                    }

                    @keyframes slide-up {
                        from {
                            transform: translateY(100%);
                            opacity: 0;
                        }

                        to {
                            transform: translateY(0);
                            opacity: 1;
                        }
                    }

                    @keyframes slide-down {
                        from {
                            transform: translateY(0);
                            opacity: 1;
                        }

                        to {
                            transform: translateY(100%);
                            opacity: 0;
                        }
                    }

                    .animate-slide-up {
                        animation: slide-up 0.3s ease;
                    }

                    /* Filter Button Styles */
                    .furniture-filter-btn,
                    .bed-filter-btn {
                        border-color: #d1d5db;
                        background: white;
                        color: #6b7280;
                    }

                    .furniture-filter-btn:hover,
                    .bed-filter-btn:hover {
                        border-color: #f97316;
                        color: #f97316;
                    }

                    .furniture-filter-btn.active {
                        border-color: #f97316;
                        background: #f97316;
                        color: white;
                    }

                    .bed-filter-btn.active {
                        border-color: #3b82f6;
                        background: #3b82f6;
                        color: white;
                    }

                    .furniture-item,
                    .bed-item {
                        transition: opacity 0.3s ease, transform 0.3s ease;
                    }

                    .furniture-item.hidden,
                    .bed-item.hidden {
                        display: none !important;
                    }
                </style>

                <script>
                    // Initialize Swiper for Furniture
                    let furnitureSwiper = new Swiper('.mySwiper-furniture', {
                        slidesPerView: 2,
                        spaceBetween: 10,
                        breakpoints: {
                            640: {
                                slidesPerView: 2,
                                spaceBetween: 15,
                            },
                            1024: {
                                slidesPerView: 4,
                                spaceBetween: 20,
                            },
                        }
                    });

                    // Initialize Swiper for Bed Furniture
                    let bedFurnitureSwiper = new Swiper('.mySwiper-bedfurniture', {
                        slidesPerView: 2,
                        spaceBetween: 10,
                        breakpoints: {
                            640: {
                                slidesPerView: 2,
                                spaceBetween: 15,
                            },
                            1024: {
                                slidesPerView: 4,
                                spaceBetween: 20,
                            },
                        }
                    });

                    // Filter Furniture by Subcategory
                    function filterFurniture(subcategory) {
                        const items = document.querySelectorAll('.furniture-item');
                        const buttons = document.querySelectorAll('.furniture-filter-btn');

                        // Update active button
                        buttons.forEach(btn => {
                            if (btn.getAttribute('data-filter') === subcategory) {
                                btn.classList.add('active');
                            } else {
                                btn.classList.remove('active');
                            }
                        });

                        // Filter items
                        items.forEach(item => {
                            if (subcategory === 'all') {
                                item.classList.remove('hidden');
                            } else {
                                if (item.getAttribute('data-subcategory') === subcategory) {
                                    item.classList.remove('hidden');
                                } else {
                                    item.classList.add('hidden');
                                }
                            }
                        });

                        // Update Swiper after filtering
                        setTimeout(() => {
                            furnitureSwiper.update();
                        }, 100);
                    }

                    // Filter Bed Furniture by Subcategory
                    function filterBedFurniture(subcategory) {
                        const items = document.querySelectorAll('.bed-item');
                        const buttons = document.querySelectorAll('.bed-filter-btn');

                        // Update active button
                        buttons.forEach(btn => {
                            if (btn.getAttribute('data-filter') === subcategory) {
                                btn.classList.add('active');
                            } else {
                                btn.classList.remove('active');
                            }
                        });

                        // Filter items
                        items.forEach(item => {
                            if (subcategory === 'all') {
                                item.classList.remove('hidden');
                            } else {
                                if (item.getAttribute('data-subcategory') === subcategory) {
                                    item.classList.remove('hidden');
                                } else {
                                    item.classList.add('hidden');
                                }
                            }
                        });

                        // Update Swiper after filtering
                        setTimeout(() => {
                            bedFurnitureSwiper.update();
                        }, 100);
                    }

                    let compareProducts = JSON.parse(localStorage.getItem('compareProducts') || '[]');

                    function updateCompareButton() {
                        const compareBtn = document.getElementById('compareBtn');
                        const compareCount = document.getElementById('compareCount');

                        if (compareProducts.length > 0) {
                            compareBtn.classList.remove('hidden');
                            compareBtn.classList.add('flex');
                            compareCount.textContent = compareProducts.length;
                        } else {
                            compareBtn.classList.add('hidden');
                            compareBtn.classList.remove('flex');
                        }
                    }

                    function toggleCompare(checkbox, productId) {
                        event.stopPropagation();

                        if (checkbox.checked) {
                            if (!compareProducts.includes(productId)) {
                                compareProducts.push(productId);
                                showToast('Product added to comparison');
                            }
                        } else {
                            compareProducts = compareProducts.filter(id => id !== productId);
                            showToast('Product removed from comparison');
                        }

                        localStorage.setItem('compareProducts', JSON.stringify(compareProducts));
                        updateCompareButton();
                    }

                    function goToComparison() {
                        if (compareProducts.length < 2) {
                            alert('Please select at least 2 products to compare.');
                            return;
                        }
                        window.location.href = 'index-shopcompare-C.php?products=' + compareProducts.join(',');
                    }

                    function showToast(message) {
                        const toast = document.createElement('div');
                        toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-slide-up';
                        toast.textContent = message;
                        document.body.appendChild(toast);

                        setTimeout(() => {
                            toast.style.animation = 'slide-down 0.3s ease';
                            setTimeout(() => toast.remove(), 300);
                        }, 2000);
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        document.querySelectorAll('.compare-checkbox').forEach(checkbox => {
                            const productId = parseInt(checkbox.dataset.productId);
                            if (compareProducts.includes(productId)) {
                                checkbox.checked = true;
                            }
                        });
                        updateCompareButton();
                    });
                </script>
            </div>
        </div>
    </section>



    <section>
        <?php
        // MAIN query for bestseller items
        $bestsellerItems = $conn->query("SELECT * FROM bestseller ORDER BY id DESC");
        $bestsellerData = $bestsellerItems->fetch_all(MYSQLI_ASSOC);
        ?>

        <section class="py-6 md:py-8 lg:py-12" id="bestseller-section">
            <div class="max-w-[1700px] mx-auto w-full px-3 sm:px-4 md:px-6 lg:px-8">

                <!-- Section Header -->
                <div class="text-center mb-4 md:mb-6 lg:mb-8" data-aos="fade-up">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl text-black tracking-wide">
                            Best Seller
                        </h2>
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 lg:w-8 lg:h-8 text-black" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <p class="text-gray-600 text-xs sm:text-sm md:text-base lg:text-lg px-2">
                        Save big on quality home improvement products
                    </p>
                </div>

                <!-- Bestseller Tabs Navigation -->
                <div class="mb-4 md:mb-6 lg:mb-8">
                    <div class="border-b border-gray-200 relative overflow-hidden">
                        <!-- Swiper Container for Tabs -->
                        <div class="swiper bestsellerTabsSwiper">
                            <div class="swiper-wrapper pb-px">
                                <?php foreach ($bestsellerData as $index => $item): ?>
                                    <div class="swiper-slide !w-auto">
                                        <button
                                            onclick="switchBestseller(<?= $index ?>)"
                                            data-index="<?= $index ?>"
                                            class="bestseller-tab tracking-wide whitespace-nowrap py-2.5 md:py-3 px-3 sm:px-4 md:px-5 lg:px-6 border-b-2 uppercase text-xs md:text-sm lg:text-base transition-all duration-300 w-full <?= $index === 0 ? 'border-orange-500 text-orange-600 bg-orange-50' : 'border-transparent text-black hover:text-gray-800 hover:border-gray-300' ?>">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Navigation Arrows (Desktop Only) -->
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-button-next"></div>
                        </div>
                    </div>
                </div>

                <!-- Content Display Area -->
                <div class="relative overflow-hidden" id="bestseller-content">
                    <?php foreach ($bestsellerData as $index => $item): ?>
                        <div
                            id="content-<?= $index ?>"
                            class="bestseller-content grid md:grid-cols-2 gap-3 sm:gap-4 md:gap-6 lg:gap-8 items-center <?= $index === 0 ? '' : 'hidden' ?>">

                            <!-- Image Side -->
                            <div class="order-2 md:order-1" data-aos="fade-right">
                                <div class="relative overflow-hidden shadow-md md:shadow-lg lg:shadow-2xl group">
                                    <img
                                        src="<?= htmlspecialchars($item['image'] ?: '../img/promo/default.png') ?>"
                                        alt="<?= htmlspecialchars($item['title']) ?>"
                                        class="w-full h-[240px] sm:h-[300px] md:h-[400px] lg:h-[500px] object-cover transition-transform duration-700">

                                    <!-- Gradient Overlay -->
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>

                                    <!-- Badge -->
                                    <div class="absolute top-3 right-3 md:top-4 md:right-4 bg-black text-white px-3 py-1.5 md:px-4 md:py-2 tracking-wide text-xs md:text-sm shadow-lg">
                                        Best Seller
                                    </div>
                                </div>
                            </div>

                            <!-- Content Side -->
                            <div class="order-1 md:order-2 space-y-3 sm:space-y-4 md:space-y-6 px-2 sm:px-4 md:px-6 lg:px-12" data-aos="fade-left">
                                <div>
                                    <h3 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl text-gray-800 mb-2 md:mb-3 lg:mb-4 uppercase leading-tight">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </h3>
                                    <div class="w-16 sm:w-16 md:w-20 h-1 bg-black rounded-full mb-3 md:mb-4 lg:mb-6"></div>
                                </div>

                                <p class="text-gray-600 text-xs sm:text-sm md:text-base lg:text-lg leading-relaxed">
                                    <?= nl2br(htmlspecialchars($item['description'])) ?>
                                </p>
                                <!-- CTA Button -->
                                <div class="pt-1 sm:pt-2 md:pt-4">
                                    <a href="index-bestseller-detail-B.php?slug=<?= htmlspecialchars($item['slug']) ?>"
                                        class="animated-learn-more inline-flex items-center gap-2 px-5 py-2.5 sm:px-6 sm:py-3 md:px-8 md:py-4 text-xs sm:text-sm md:text-base transition-all duration-300 group relative">
                                        <span class="relative overflow-hidden">
                                            <span class="block transition-transform duration-300 group-hover:-translate-y-full">Learn More</span>
                                            <span class="absolute inset-0 block translate-y-full transition-transform duration-300 group-hover:translate-y-0 text-red-600">Learn More</span>
                                        </span>
                                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 md:w-5 md:h-5 transition-all duration-300 group-hover:translate-x-1 group-hover:text-red-600"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="4"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                        </svg>
                                    </a>
                                </div>

                                <style>
                                    .animated-learn-more::after {
                                        content: "";
                                        position: absolute;
                                        left: 0;
                                        bottom: -4px;
                                        width: 0;
                                        height: 2px;
                                        background-color: #c84747;
                                        transition: width 0.3s ease-out;
                                    }

                                    .animated-learn-more:hover::after {
                                        width: 100%;
                                    }
                                </style>

                                <!-- Additional Info -->
                                <div class="flex flex-wrap items-center gap-2 sm:gap-3 md:gap-4 pt-1 sm:pt-2 md:pt-4 text-xs md:text-sm text-gray-500">
                                    <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2">
                                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 md:w-5 md:h-5 text-black" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                        <span>Top Rated</span>
                                    </div>
                                    <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2">
                                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 md:w-5 md:h-5 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span>Quality Guaranteed</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>

        <style>
            /* Swiper Navigation Styling */
            .bestsellerTabsSwiper .swiper-button-prev,
            .bestsellerTabsSwiper .swiper-button-next {
                width: 40px;
                height: 40px;
                background: white;
                border-radius: 50%;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .bestsellerTabsSwiper .swiper-button-prev:after,
            .bestsellerTabsSwiper .swiper-button-next:after {
                font-size: 18px;
                font-weight: bold;
                color: #000;
            }

            /* Hide arrows on mobile */
            @media (max-width: 1023px) {

                .bestsellerTabsSwiper .swiper-button-prev,
                .bestsellerTabsSwiper .swiper-button-next {
                    display: none;
                }
            }

            /* Smooth content transitions */
            .bestseller-content {
                animation: fadeInContent 0.4s ease-in-out;
            }

            @keyframes fadeInContent {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Mobile optimizations */
            @media (max-width: 768px) {
                .bestseller-tab {
                    touch-action: manipulation;
                }
            }
        </style>


        <script>
            // Initialize Tabs Swiper
            let tabsSwiper;

            document.addEventListener('DOMContentLoaded', function() {
                tabsSwiper = new Swiper('.bestsellerTabsSwiper', {
                    slidesPerView: 'auto',
                    spaceBetween: 8,
                    freeMode: true,
                    grabCursor: true,
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                    breakpoints: {
                        640: {
                            spaceBetween: 8
                        },
                        768: {
                            spaceBetween: 8
                        },
                        1024: {
                            spaceBetween: 8
                        }
                    },
                    on: {
                        init: function() {
                            updateNavigationButtons(this);
                        },
                        resize: function() {
                            updateNavigationButtons(this);
                        },
                        slideChange: function() {
                            updateNavigationButtons(this);
                        },
                        reachBeginning: function() {
                            updateNavigationButtons(this);
                        },
                        reachEnd: function() {
                            updateNavigationButtons(this);
                        }
                    }
                });
            });

            // Function to show/hide navigation buttons based on content
            function updateNavigationButtons(swiper) {
                const prevButton = document.querySelector('.bestsellerTabsSwiper .swiper-button-prev');
                const nextButton = document.querySelector('.bestsellerTabsSwiper .swiper-button-next');

                if (!prevButton || !nextButton) return;

                // Check if content is scrollable (overflow)
                const isScrollable = swiper.isEnd !== swiper.isBeginning;

                // Hide both buttons if content fits (not scrollable)
                if (!isScrollable) {
                    prevButton.style.display = 'none';
                    nextButton.style.display = 'none';
                    return;
                }

                // Show buttons on desktop only when scrollable
                if (window.innerWidth >= 1024) {
                    // Show/hide prev button
                    if (swiper.isBeginning) {
                        prevButton.style.display = 'none';
                    } else {
                        prevButton.style.display = 'flex';
                    }

                    // Show/hide next button
                    if (swiper.isEnd) {
                        nextButton.style.display = 'none';
                    } else {
                        nextButton.style.display = 'flex';
                    }
                } else {
                    // Always hide on mobile
                    prevButton.style.display = 'none';
                    nextButton.style.display = 'none';
                }
            }

            // Function to switch bestseller content
            function switchBestseller(index) {
                // Hide all content
                const allContent = document.querySelectorAll('.bestseller-content');
                allContent.forEach(content => {
                    content.classList.add('hidden');
                });

                // Remove active class from all tabs
                const allTabs = document.querySelectorAll('.bestseller-tab');
                allTabs.forEach(tab => {
                    tab.classList.remove('border-orange-500', 'text-orange-600', 'bg-orange-50');
                    tab.classList.add('border-transparent', 'text-gray-600');
                });

                // Show selected content
                const selectedContent = document.getElementById('content-' + index);
                if (selectedContent) {
                    selectedContent.classList.remove('hidden');
                }

                // Add active class to selected tab
                const selectedTab = document.querySelector('[data-index="' + index + '"]');
                if (selectedTab) {
                    selectedTab.classList.remove('border-transparent', 'text-gray-600');
                    selectedTab.classList.add('border-orange-500', 'text-orange-600', 'bg-orange-50');
                }
            }

            // Update buttons on window resize
            window.addEventListener('resize', function() {
                if (tabsSwiper) {
                    updateNavigationButtons(tabsSwiper);
                }
            });
        </script>

    </section>


    <section class="px-4 ">
        <!-- Combined Container for Both Sections -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">

            <!-- LEFT SECTION: Top Sales (10% Discount) -->
            <?php if (mysqli_num_rows($material_results) > 0): ?>
                <div class="p-4 rounded-lg">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <h2 class="text-2xl lg:text-3xl text-black mb-1 tracking-tight" data-aos="fade-up">Top Sales</h2>
                        <h3 class="text-base lg:text-lg text-black mb-2" data-aos="fade-up">
                            Up to <span class="text-red-500">10% OFF</span>
                        </h3>
                        <div class="mx-auto w-24 h-0.5 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </div>

                    <!-- Swiper Container -->
                    <div class="swiper mySwiper-topsales w-full">
                        <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                            <?php
                            mysqli_data_seek($material_results, 0);
                            while ($row = mysqli_fetch_assoc($material_results)) :
                                $base = (float)$row['price'];
                                $percent = (float)($row['percent'] ?? 0);
                                $discount = (float)($row['discount'] ?? 0);
                                $priceWithMarkup = $base + ($base * $percent / 100);
                                $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

                                $viewCount = (int)($row['view_count'] ?? 0);
                                $soldCount = (int)($row['total_sold'] ?? 0);
                                $avgRating = (float)($row['avg_rating'] ?? 0);
                                $ratingCount = (int)($row['rating_count'] ?? 0);
                            ?>
                                <div class="swiper-slide p-2">
                                    <div class="bg-white p-2 lg:p-3 group hover:shadow-lg transition duration-300 flex flex-col justify-between h-[320px] text-center relative rounded-md">
                                        <!-- Discount Badge -->
                                        <div class="absolute top-0 left-0 z-10">
                                            <div class="w-8 h-8 relative">
                                                <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-6 h-6 object-contain" />
                                            </div>
                                        </div>

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
                                                <p class="text-xs text-black leading-relaxed text-left">
                                                    <span class="font-medium group-hover:text-orange-600 transition-colors duration-300"><?= htmlspecialchars($row['product_name']) ?></span>
                                                    <span class="text-black"><?= !empty($row['size']) ? ' ' . htmlspecialchars($row['size']) : '' ?></span>
                                                    <span class="">
                                                        <?php if (!empty($row['color'])): ?>
                                                            <?php if (!empty($row['color_code'])): ?>
                                                                <span class="inline-block w-2.5 h-2.5 rounded-full border border-gray-300 ml-1 mr-0.5 align-middle" style="background-color: <?= htmlspecialchars($row['color_code']) ?>"></span>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($row['color']) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </p>
                                            </div>

                                            <!-- Stats -->
                                            <div class="flex items-center justify-start gap-1 mt-1 text-xs text-gray-600 px-1">
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
                                            <div class="my-1 text-left px-1">
                                                <?php if ($discount > 0): ?>
                                                    <p class="text-xs text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                    <p class="text-sm text-black font-bold">
                                                        ₱<?= number_format($finalPrice, 2) ?>
                                                        <span class="text-xs text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-sm text-green-600 font-bold">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="flex gap-2 mt-2 px-1">
                                                <form action="index-product_view-page-4-AA" method="GET" class="flex-1">
                                                    <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                    <button type="submit" class="w-full text-black hover:text-orange-500 transition font-medium text-xs py-2 border border-gray-300 rounded hover:border-orange-500">
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
                                                    <input type="hidden" name="total_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                    <input type="hidden" name="return_url" value="index">
                                                    <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white text-xs py-2 rounded transition-all">
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

            <!-- RIGHT SECTION: Discounted Minimal (5% Discount) -->
            <?php if (mysqli_num_rows($material_resultsone) > 0): ?>
                <div class="p-4 rounded-lg">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <h2 class="text-2xl lg:text-3xl text-black mb-1 tracking-tight" data-aos="fade-up">Discounted Minimal</h2>
                        <h3 class="text-base lg:text-lg text-black mb-2" data-aos="fade-up">
                            Up to <span class="text-red-500">5% OFF</span>
                        </h3>
                        <div class="mx-auto w-24 h-0.5 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </div>

                    <!-- Swiper Container -->
                    <div class="swiper mySwiper-minimal w-full">
                        <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                            <?php
                            mysqli_data_seek($material_resultsone, 0);
                            while ($row = mysqli_fetch_assoc($material_resultsone)) :
                                $base = (float)$row['price'];
                                $percent = (float)($row['percent'] ?? 0);
                                $discount = (float)($row['discount'] ?? 0);
                                $priceWithMarkup = $base + ($base * $percent / 100);
                                $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

                                $viewCount = (int)($row['view_count'] ?? 0);
                                $soldCount = (int)($row['total_sold'] ?? 0);
                                $avgRating = (float)($row['avg_rating'] ?? 0);
                                $ratingCount = (int)($row['rating_count'] ?? 0);
                            ?>
                                <div class="swiper-slide p-2">
                                    <div class="bg-white p-2 lg:p-3 group hover:shadow-lg transition duration-300 flex flex-col justify-between h-[320px] text-center relative rounded-md">
                                        <!-- Discount Badge -->
                                        <div class="absolute top-0 left-0 z-10">
                                            <div class="w-8 h-8 relative">
                                                <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-6 h-6 object-contain" />
                                            </div>
                                        </div>

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
                                                <p class="text-xs text-black leading-relaxed text-left">
                                                    <span class="font-medium group-hover:text-orange-600 transition-colors duration-300"><?= htmlspecialchars($row['product_name']) ?></span>
                                                    <span class="text-black"><?= !empty($row['size']) ? ' ' . htmlspecialchars($row['size']) : '' ?></span>
                                                    <span class="">
                                                        <?php if (!empty($row['color'])): ?>
                                                            <?php if (!empty($row['color_code'])): ?>
                                                                <span class="inline-block w-2.5 h-2.5 rounded-full border border-gray-300 ml-1 mr-0.5 align-middle" style="background-color: <?= htmlspecialchars($row['color_code']) ?>"></span>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($row['color']) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </p>
                                            </div>

                                            <!-- Stats -->
                                            <div class="flex items-center justify-start gap-1 mt-1 text-xs text-gray-600 px-1">
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
                                            <div class="my-1 text-left px-1">
                                                <?php if ($discount > 0): ?>
                                                    <p class="text-xs text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                    <p class="text-sm text-black font-bold">
                                                        ₱<?= number_format($finalPrice, 2) ?>
                                                        <span class="text-xs text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-sm text-green-600 font-bold">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="flex gap-1 mt-1 px-1">
                                                <form action="index-product_view-page-4-AA" method="GET" class="flex-1">
                                                    <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                    <button type="submit" class="w-full text-black hover:text-orange-500 transition font-medium text-xs py-1.5 border border-gray-300 rounded hover:border-orange-500">
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
                                                    <input type="hidden" name="total_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                    <input type="hidden" name="return_url" value="index">
                                                    <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white text-xs py-1.5 rounded transition-all">
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
        // Initialize separate Swiper instances for each section
        document.addEventListener('DOMContentLoaded', function() {
            new Swiper('.mySwiper-topsales', {
                slidesPerView: 1,
                spaceBetween: 10,
                loop: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    640: {
                        slidesPerView: 2,
                    },
                    1024: {
                        slidesPerView: 4,
                    }
                }
            });

            new Swiper('.mySwiper-minimal', {
                slidesPerView: 1,
                spaceBetween: 10,
                loop: true,
                autoplay: {
                    delay: 3500,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    640: {
                        slidesPerView: 2,
                    },
                    1024: {
                        slidesPerView: 4,
                    }
                }
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
            <section class="px-4 py-10">
                <!-- Header -->
                <div class="text-center mb-10 relative">
                    <h2 class="text-4xl text-black mb-2 tracking-tight" data-aos="fade-up">New Arrival</h2>

                    <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                </div>

                <?php
                // Check if there are any products
                $row_count = mysqli_num_rows($material_resultstwo);
                ?>

                <?php if ($row_count > 0): ?>
                    <!-- Swiper Container -->
                    <div class="swiper mySwiper-products w-full">
                        <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                            <?php while ($row = mysqli_fetch_assoc($material_resultstwo)) : ?>
                                <?php
                                $base = (float)$row['price'];
                                $percent = (float)($row['percent'] ?? 0);
                                $discount = (float)($row['discount'] ?? 0);
                                $priceWithMarkup = $base + ($base * $percent / 100);
                                $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

                                // Get stats
                                $viewCount = (int)($row['view_count'] ?? 0);
                                $soldCount = (int)($row['total_sold'] ?? 0);
                                $avgRating = (float)($row['avg_rating'] ?? 0);
                                $ratingCount = (int)($row['rating_count'] ?? 0);
                                ?>
                                <div class="swiper-slide p-2">
                                    <div class="bg-white p-2 lg:p-3 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[380px] sm:h-[420px] lg:h-[480px] text-center relative">
                                        <!-- Triangle Badge -->
                                        <div class="absolute top-0 left-0 z-10">
                                            <div class="w-8 h-8 lg:w-12 lg:h-12 relative">
                                                <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-6 h-6 lg:w-9 lg:h-9 object-contain" />
                                            </div>
                                        </div>

                                        <!-- Product Image -->
                                        <div class="aspect-square w-full rounded-lg overflow-hidden mb-2 lg:mb-4">
                                            <?php if (!empty($row['type_image'])): ?>
                                                <img src="../../<?= $row['type_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['namevariant']) ?>"
                                                    class="w-full h-full object-cover lg:object-contain transition-transform duration-300 " />
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Product Info -->
                                        <div class="">
                                            <div class="relative w-full">
                                                <h3 class="text-xs lg:text-sm font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 line-clamp-2 text-center lg:text-left px-2 lg:pr-2">
                                                    <?= htmlspecialchars($row['product_name']) ?>
                                                </h3>
                                            </div>

                                            <!-- Description - Desktop Only -->
                                            <div class="hidden lg:block relative w-full mt-1">
                                                <p class="text-xs text-gray-500 leading-tight line-clamp-2 px-2 text-center lg:text-left">
                                                    <?= htmlspecialchars($row['description'] ?? 'No description available') ?>
                                                </p>
                                            </div>

                                            <!-- Stats Row (Views, Sold, Rating) -->
                                            <div class="flex items-center justify-center lg:justify-start gap-1 mt-2 px-2 text-xs text-gray-600">
                                                <!-- Views -->
                                                <span><?= number_format($viewCount) ?> viewing</span>
                                                <span>|</span>

                                                <!-- Sold -->
                                                <span><?= number_format($soldCount) ?> sold</span>

                                                <!-- Rating -->
                                                <?php if ($ratingCount > 0): ?>
                                                    <span>|</span>
                                                    <div class="flex items-center gap-1">
                                                        <i class="fa-solid fa-star text-yellow-500"></i>
                                                        <span><?= number_format($avgRating, 1) ?></span>
                                                        <span class="text-gray-400">(<?= $ratingCount ?>)</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Price + View Button Row - Desktop Only -->
                                            <div class="hidden lg:flex items-center justify-between mt-2 px-2">
                                                <!-- Pricing -->
                                                <div>
                                                    <?php if ($discount > 0): ?>
                                                        <p class="text-xs text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                        <p class="text-sm text-black font-bold">
                                                            ₱<?= number_format($finalPrice, 2) ?>
                                                            <span class="text-xs text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                                        </p>
                                                    <?php else: ?>
                                                        <p class="text-sm text-green-600 font-bold">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- View Button -->
                                                <form action="index-product_view-page-4-AA" method="GET">
                                                    <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                    <button type="submit" class="flex items-center gap-1 text-black hover:text-orange-500 transition font-medium text-xs">
                                                        <i class="fa-solid fa-bag-shopping"></i>
                                                        <span>View</span>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Mobile Layout (Original) -->
                                            <div class="lg:hidden">
                                                <!-- Pricing -->
                                                <div class="my-1">
                                                    <?php if ($discount > 0): ?>
                                                        <p class="text-xs text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                        <p class="text-sm text-black font-bold">
                                                            ₱<?= number_format($finalPrice, 2) ?>
                                                            <span class="text-xs text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                                        </p>
                                                    <?php else: ?>
                                                        <p class="text-sm text-green-600 font-bold">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Buttons -->
                                                <div class="flex flex-col gap-1.5 mt-1.5">
                                                    <form action="index-product_view-page-4-AA" method="GET" class="w-full flex justify-center">
                                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                        <button type="submit" class="flex items-center gap-2 text-black hover:text-orange-500 transition font-medium text-sm">
                                                            <i class="fa-solid fa-bag-shopping"></i>
                                                            <span>View</span>
                                                        </button>
                                                    </form>

                                                    <!-- Add to Cart Button -->
                                                    <form class="productForm" data-product-id="<?= (int)$row['product_id'] ?>">
                                                        <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                                                        <input type="hidden" name="selected_type" value="<?= htmlspecialchars($row['type_name'] ?? '') ?>">
                                                        <input type="hidden" name="selected_variant" value="<?= htmlspecialchars($row['namevariant'] ?? '') ?>">
                                                        <input type="hidden" name="variant_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                                        <input type="hidden" name="selected_color_id" value="<?= (int)($row['color_id'] ?? 0) ?>">
                                                        <input type="hidden" name="selected_color_name" value="<?= htmlspecialchars($row['color_name'] ?? '') ?>">
                                                        <input type="hidden" name="color_price" value="<?= floatval($row['color_price'] ?? 0) ?>">
                                                        <input type="hidden" name="variant_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                        <input type="hidden" name="total_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                        <input type="hidden" name="return_url" value="index">
                                                        <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white text-xs px-4 py-2 flex items-center justify-center gap-2 font-semibold transition-all duration-300 transform hover:scale-105" aria-label="Add to cart">
                                                            <img src="../img/icon/cart.png" alt="" class="w-4 h-4" aria-hidden="true" />
                                                            Add to Cart
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <!-- Add to Cart Button - Desktop -->
                                            <div class="hidden lg:block mt-2">
                                                <form class="productForm" data-product-id="<?= (int)$row['product_id'] ?>">
                                                    <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                                                    <input type="hidden" name="selected_type" value="<?= htmlspecialchars($row['type_name'] ?? '') ?>">
                                                    <input type="hidden" name="selected_variant" value="<?= htmlspecialchars($row['namevariant'] ?? '') ?>">
                                                    <input type="hidden" name="variant_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="selected_color_id" value="<?= (int)($row['color_id'] ?? 0) ?>">
                                                    <input type="hidden" name="selected_color_name" value="<?= htmlspecialchars($row['color_name'] ?? '') ?>">
                                                    <input type="hidden" name="color_price" value="<?= floatval($row['color_price'] ?? 0) ?>">
                                                    <input type="hidden" name="variant_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                    <input type="hidden" name="total_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                                    <input type="hidden" name="return_url" value="index">
                                                    <button type="submit" class="w-full bg-black hover:bg-gray-800 text-white text-sm px-6 py-3 flex items-center justify-center gap-2 font-semibold transition-all duration-300 transform hover:scale-105" aria-label="Add to cart">
                                                        <img src="../img/icon/cart.png" alt="" class="w-6 h-6" aria-hidden="true" />
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
        <!-- Toggle Button -->
        <button
            id="chatbot-toggle"
            class="w-16 h-16 bg-orange-400 hover:from-blue-700 hover:to-purple-700 text-white rounded-full shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center justify-center">
            <svg id="chat-icon" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            <svg id="close-icon" class="w-8 h-8 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
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
        /* Pagination dots */
        .swiper-pagination-bullet {
            background: linear-gradient(135deg, #000000ff, #000000ff) !important;
            opacity: 0.4 !important;
            transition: all 0.3s ease-in-out;
        }

        .swiper-pagination-bullet-active {
            opacity: 1 !important;
            transform: scale(1.2);
        }

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

    <section class="w-full bg-white py-20 px-6 border-t border-gray-200">
        <div class="max-w-full mx-auto">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <div class="inline-block mb-6">
                    <span class="text-sm  text-gray-500 tracking-wider uppercase mb-2 block">Our NobleHome</span>
                    <h2 class="text-4xl md:text-5xl  text-gray-900 mb-4 tracking-tight">
                        We Design, We build, and We deliver
                    </h2>
                    <div class="w-24 h-1 bg-gradient-to-r from-slate-600 to-slate-800 mx-auto mb-6"></div>
                </div>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto leading-relaxed">
                    Discover our premium collection through detailed product demonstrations and professional showcases
                </p>
            </div>

            <!-- Video Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

                <!-- Video Item 1 -->
                <div class="bg-white overflow-hidden  hover:shadow-2xl transition-all duration-500 border border-gray-100 group h-full flex flex-col">
                    <div class="relative overflow-hidden" style="aspect-ratio: 16/9; height: 220px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                            <source src="../../video/a.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent"></div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class=" text-xl text-gray-900 mb-2 tracking-tight">WPC Wall Panel</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Premium waterproof panels designed for contemporary interior applications</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs  tracking-wide uppercase">
                                Premium
                            </span>
                            <button class="text-slate-700 hover:text-slate-900  flex items-center group">
                                View Details
                                <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Video Item 2 -->
                <div class="bg-white  overflow-hidden  hover:shadow-2xl transition-all duration-500 border border-gray-100 group h-full flex flex-col">
                    <div class="relative overflow-hidden" style="aspect-ratio: 16/9; height: 220px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                            <source src="../../video/b.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent"></div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class=" text-xl text-gray-900 mb-2 tracking-tight">Interior Design</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Professional styling concepts and innovative design solutions</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs  tracking-wide uppercase">
                                Inspiration
                            </span>
                            <a href="../explore/explore_first.php" class="text-slate-700 hover:text-slate-900 flex items-center group">
                                Explore Ideas
                                <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>

                        </div>
                    </div>
                </div>

                <!-- Video Item 3 -->
                <div class="bg-white  overflow-hidden  hover:shadow-2xl transition-all duration-500 border border-gray-100 group h-full flex flex-col">
                    <div class="relative overflow-hidden" style="aspect-ratio: 16/9; height: 220px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                            <source src="../../video/c.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent"></div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class=" text-xl text-gray-900 mb-2 tracking-tight">Product Highlights</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Featured products showcased in real-world applications</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs  tracking-wide uppercase">
                                Featured
                            </span>
                            <button class="text-slate-700 hover:text-slate-900 flex items-center group">
                                Shop Collection
                                <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Video Item 4 -->
                <div class="bg-white overflow-hidden  hover:shadow-2xl transition-all duration-500 border border-gray-100 group h-full flex flex-col">
                    <div class="relative overflow-hidden" style="aspect-ratio: 16/9; height: 220px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                            <source src="../../video/d.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent"></div>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class="text-xl text-gray-900 mb-2 tracking-tight">World Bex</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Thank You for Visiting Us at WORLDBEX 2025! 🎉🏡
                            We truly appreciate your time, support, and interest in Noblehome Depot at WORLDBEX 2025! Your presence made this event even more special, and we’re excited to help bring your home and construction projects to life.</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs  tracking-wide uppercase">
                                Event
                            </span>
                            <button class="text-slate-700 hover:text-slate-900  flex items-center group">
                                Learn More
                                <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

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
                    button.textContent = '✓ Added!';
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

            notification.className = `fixed top-4 left-1/2 -translate-x-1/2 p-4 rounded-lg z-50 ${bgColor} text-white shadow-lg transform transition-all duration-300`;
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
                            slidesPerView: 5,
                            spaceBetween: 15,
                            loop: productSlideCount >= 6
                        },
                        1024: {
                            slidesPerView: 5,
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