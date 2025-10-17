<?php
ob_start();
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

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
// 1. Fetch all variants (basic list) - UNCHANGED
$query_variants1 = "SELECT id, type_id, color, size, price, percent, image, origin FROM product_variants ORDER BY id DESC";
$result_variants = mysqli_query($conn, $query_variants1);

// 2. Furniture product list - UNCHANGED
$SYCJ_query = "SELECT * FROM products WHERE codename = 'furniture' ORDER BY id DESC";
$SYCJ_result = mysqli_query($conn, $SYCJ_query);



// 3. Discount 10% materials - RANDOM 10 ONLY
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
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc 
        ON pc.product_id = p.id
       AND pc.id = (
           SELECT MIN(pc2.id) 
           FROM product_colors pc2 
           WHERE pc2.product_id = p.id
       )
    WHERE pv.discount = 10
    ORDER BY RAND()
    LIMIT 10
";
$material_results = mysqli_query($conn, $material_querys);


// 4. Discount between 1-15% - FIXED: descrip6, descrip7 from products table
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
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc 
        ON pc.id = (
            SELECT MIN(pc2.id) 
            FROM product_colors pc2 
            WHERE pc2.product_id = p.id
        )
    WHERE pv.discount BETWEEN 1 AND 5
    GROUP BY p.id
    ORDER BY pv.percent ASC, p.id ASC, pc.id ASC
";
$material_resultsone = mysqli_query($conn, $material_querysone);


// 5. Fetch "new" status product variants - RANDOM 10 ONLY
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
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    WHERE pv.status = 'new'
    GROUP BY pv.id
    ORDER BY RAND()
    LIMIT 10
";
$material_resultstwo = mysqli_query($conn, $material_querystwo);



// 6. Products without discount - RANDOM 10 ONLY
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
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price,
        pc.image
     FROM product_variants pv
     INNER JOIN product_types pt ON pv.type_id = pt.id
     INNER JOIN products p ON pt.product_id = p.id
     LEFT JOIN product_colors pc ON p.id = pc.product_id
     WHERE pv.discount IS NULL OR pv.discount = 0
     GROUP BY pv.id
     ORDER BY RAND()
     LIMIT 10"
);

// 7. Filter by furniture codename - RANDOM 10 ONLY
$filter = 'furniture';
$query = "
    SELECT 
        p.*, 
        p.descrip6, 
        p.descrip7,
        v.origin,
        v.discount,
        v.percent,
        v.status
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    WHERE p.codename = ?
    GROUP BY p.id
    ORDER BY RAND()
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$result = $stmt->get_result();


// 8. Filter by material codename with rating - RANDOM 10 ONLY
$filter = 'buildingmaterials';
$query = "
    SELECT 
        p.*, 
        p.descrip6, 
        p.descrip7,
        v.origin,
        v.discount,
        v.percent,
        v.status,
        AVG(r.rating) AS avg_rating,
        COUNT(r.rating) AS rating_count
    FROM products p
    LEFT JOIN (
        SELECT * FROM product_variants 
        GROUP BY product_id
    ) v ON v.product_id = p.id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    WHERE p.codename = ?
    GROUP BY p.id
    ORDER BY RAND()
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$results = $stmt->get_result();

// 9. Filter by bedfurniture codename - RANDOM 10 ONLY
$filters = 'bedfurniture';
$query = "
    SELECT 
        p.*, 
        p.descrip6, 
        p.descrip7,
        v.origin,
        v.discount,
        v.percent,
        v.status
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    WHERE p.codename = ?
    GROUP BY p.id
    ORDER BY RAND()
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filters);
$stmt->execute();
$resultss = $stmt->get_result();


// 10. Organize discount products into columns - ENHANCED error handling
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

// 11. Slider images - ENHANCED with error handling
$sql = "SELECT filename FROM discount_images ORDER BY uploaded_at DESC";
$slideresult = $conn->query($sql);

// Optional: Error handling function for debugging
function handleQueryError($conn, $query_name)
{
    if (mysqli_error($conn)) {
        error_log("Database Error in $query_name: " . mysqli_error($conn));
        return false;
    }
    return true;
}

// Check for errors in critical queries
handleQueryError($conn, "Discount 30% Query");
handleQueryError($conn, "Discount 1-15% Query");
handleQueryError($conn, "New Status Query");

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

    <?php include 'flash_notification.php'; ?>

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

    <!-- Parent Wrapper -->
    <div class="w-full flex flex-col lg:flex-row gap-1 px-2 sm:px-4  ">
        <!-- LEFT: Main Swiper Container -->
        <section class="w-full lg:w-[65%] xl:w-[70%] overflow-hidden relative flex-shrink-0">
            <div class="mySwiper relative w-full">
                <div class="swiper-wrapper">
                    <?php while ($row = $slideresult->fetch_assoc()): ?>
                        <div class="swiper-slide h-[180px] xs:h-[220px] sm:h-[280px] md:h-[320px] lg:h-[280px] xl:h-[320px] relative bg-gradient-to-b from-gray-50 to-gray-100  overflow-hidden">
                            <img src="../../uploads/<?= htmlspecialchars($row['filename']) ?>"
                                alt="Discount"
                                class="w-full h-full object-cover object-center" />

                            <!-- Blurred Gray Overlay for Pagination Area -->
                            <div class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-gray-100 via-gray-100/70 to-transparent backdrop-blur-xs z-[5]"></div>

                            <div class="absolute inset-0 bg-black/5"></div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="swiper-pagination !bottom-2 relative z-10"></div>
            </div>

            <style>
                .mySwiper .swiper-slide img {
                    width: 100% !important;
                    max-width: 1450px !important;
                    height: 100% !important;
                    object-fit: cover !important;
                    object-position: center !important;
                    margin: auto !important;

                }

                /* Mobile - Extra Small */
                @media (max-width: 480px) {
                    .mySwiper .swiper-slide img {
                        width: 98% !important;
                        height: 96% !important;
                    }
                }

                /* Mobile - Small */
                @media (min-width: 481px) and (max-width: 640px) {
                    .mySwiper .swiper-slide img {
                        width: 96% !important;
                        height: 94% !important;
                    }
                }

                /* Tablet */
                @media (min-width: 641px) and (max-width: 1024px) {
                    .mySwiper .swiper-slide img {
                        width: 92% !important;
                        height: 90% !important;
                    }
                }

                /* Desktop */
                @media (min-width: 1025px) {
                    .mySwiper .swiper-slide img {
                        width: 100% !important;
                        height: 100% !important;
                    }
                }
            </style>
        </section>

        <!-- RIGHT: 2 Images Container -->
        <section class="w-full lg:w-[35%] xl:w-[30%] flex-shrink-0">
            <!-- Mobile: Horizontal Layout (2 images side by side) -->
            <div class="flex lg:hidden gap-1 sm:gap-1 w-full h-[180px] xs:h-[230px] sm:h-[280px]">
                <!-- Image 1 - Desktop -->
                <div class="w-full flex-1 bg-gradient-to-b from-gray-50 to-gray-100 overflow-hidden border border-gray-200  shadow-sm hover:shadow-md transition-shadow duration-300 relative group">
                    <img src="../img/gif1.gif" alt="Promo 1" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
                    <!-- Overlay with Subtitle -->
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 via-black/50 to-transparent p-3">
                        <h3 class="text-white  text-sm">Recent View ➜</h3>
                    </div>
                </div>
                <!-- Image 2 - Desktop -->
                <div class="w-full flex-1 bg-gradient-to-b from-gray-50 to-gray-100 overflow-hidden border border-gray-200  shadow-sm hover:shadow-md transition-shadow duration-300 relative group">
                    <img src="../img/gif1.gif" alt="Promo 2" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
                    <!-- Overlay with Subtitle -->
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 via-black/50 to-transparent p-3">
                        <h3 class="text-white text-sm">Amazing Deals Holiday! ➜</h3>
                    </div>
                </div>
            </div>

            <!-- Desktop: Vertical Layout (stacked) -->
            <div class="hidden lg:flex flex-col gap-2 w-full h-[280px] xl:h-[438px]">
                <!-- Image 1 - Desktop -->
                <div class="w-full flex-1 bg-gradient-to-b from-gray-50 to-gray-100 overflow-hidden border border-gray-200  shadow-sm hover:shadow-md transition-shadow duration-300 relative group">
                    <img src="../img/gif1.gif" alt="Promo 1" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
                    <!-- Overlay with Subtitle -->
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 via-black/50 to-transparent p-3">
                        <h3 class="text-white  text-sm">Recent View ➜</h3>
                    </div>
                </div>
                <!-- Image 2 - Desktop -->
                <div class="w-full flex-1 bg-gradient-to-b from-gray-50 to-gray-100 overflow-hidden border border-gray-200  shadow-sm hover:shadow-md transition-shadow duration-300 relative group">
                    <img src="../img/gif1.gif" alt="Promo 2" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
                    <!-- Overlay with Subtitle -->
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 via-black/50 to-transparent p-3">
                        <h3 class="text-white text-sm">Amazing Deals Holiday! ➜</h3>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="bg-black hidden md:block border border-black/20">
        <div class="px-4 sm:px-8 lg:px-9">
            <!-- Clickable Banner Image - Left Aligned -->
            <a href="shop.php" class="block hover:opacity-90 transition-opacity duration-300 w-fit">
                <img src="../img/exclusive1.png"
                    alt="Exclusive Discounts - Shop Now"
                    class="h-auto object-contain max-h-[30px] sm:max-h-[40px] md:max-h-[50px] lg:max-h-[60px]">
            </a>
        </div>
    </section>

    <section class="py-5 tracking-wide px-4 hidden md:block">
        <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <!-- Fast Delivery -->
            <div class="flex flex-col items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-black mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-800">Fast Delivery</h3>
                <p class="text-sm text-gray-500">Quick and reliable shipping</p>
            </div>

            <!-- High Quality -->
            <div class="flex flex-col items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-black mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-800">High Quality</h3>
                <p class="text-sm text-gray-500">Products you can trust</p>
            </div>

            <!-- Affordable Prices -->
            <div class="flex flex-col items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-black mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-800">Affordable Prices</h3>
                <p class="text-sm text-gray-500">Great value for your money</p>
            </div>

            <!-- Secure Checkout -->
            <div class="flex flex-col items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-black mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-800">Secure Checkout</h3>
                <p class="text-sm text-gray-500">Safe and easy payments</p>
            </div>
        </div>
    </section>


    <!-- POPUP MODAL -->
    <div id="promoPopup" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50">
        <div class="relative max-w-4xl w-full mx-4">
            <!-- Close Button -->
            <button onclick="hidePromoModal()"
                class="absolute -top-4 -right-4 text-white hover:text-red-500 bg-black/70 rounded-full w-10 h-10 flex items-center justify-center text-2xl z-10 transition-colors duration-300">✕</button>

            <!-- Single Image Display -->
            <div class="relative overflow-hidden">
                <a href="allproduct.php?discount=20" class="relative group flex items-center justify-center">
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

    // Category URL mapping
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

    <section class="py-8 md:py-10 lg:py-12 bg-white rounded-t-[60px] md:rounded-t-[80px] lg:rounded-t-[100px]">
        <!-- Minimal Header -->
        <div class="mb-8 md:mb-10 lg:mb-12 px-4 sm:px-6 md:px-8 max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-2 md:mb-3">
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="w-1 h-6 md:h-7 lg:h-8 bg-black"></div>
                    <h2 class="text-2xl md:text-3xl lg:text-4xl font-bold text-black tracking-wide">
                        Shop by Department
                    </h2>
                </div>

                <!-- Navigation Buttons - Compact Top Right -->
                <div class="hidden md:flex items-center gap-2">
                    <button class="department-prev-btn w-9 h-9 bg-white border border-neutral-200 rounded-full shadow-sm flex items-center justify-center hover:bg-neutral-900 hover:border-neutral-900 transition-all duration-300 group">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-neutral-900 group-hover:text-white transition-colors">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <button class="department-next-btn w-9 h-9 bg-white border border-neutral-200 rounded-full shadow-sm flex items-center justify-center hover:bg-neutral-900 hover:border-neutral-900 transition-all duration-300 group">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-neutral-900 group-hover:text-white transition-colors">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
            <p class="text-black text-sm md:text-base lg:text-lg ml-5 md:ml-6 lg:ml-7 font-light">
                Explore our complete range of products
            </p>
            <hr class="border-t border-black mt-4 md:mt-5 lg:mt-6">


        </div>

        <!-- Categories Container -->
        <div class="relative px-3 sm:px-4 md:px-5 lg:px-6 max-w-[1800px] mx-auto">

            <div class="swiper department-swiper-container overflow-hidden">
                <div class="swiper-wrapper pb-4">
                    <?php foreach ($categories as $category): ?>
                        <?php
                        $categoryName = $category['name'];
                        $categoryUrl = $categoryUrlMap[$categoryName] ?? strtolower($categoryName);

                        // Get image path from database
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
                        <div class="swiper-slide">
                            <a href="shop?category[]=<?php echo htmlspecialchars($categoryUrl); ?>" class="group block h-full">
                                <!-- Card with Unique Rounded Corners -->
                                <div class="h-full bg-white group-hover:border-neutral-900 transition-all duration-500 overflow-hidden department-card group-hover:shadow-lg">
                                    <!-- Image Container -->
                                    <div class="relative h-48 sm:h-52 md:h-56 lg:h-64 bg-neutral-50 overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                            alt="<?php echo htmlspecialchars($displayName); ?>"
                                            class="copy w-full h-full object-contain p-2 sm:p-3 md:p-4 transition-transform duration-700 group-hover:scale-105"
                                            loading="lazy">
                                    </div>

                                    <!-- Text Content -->
                                    <div class="py-2.5 md:py-3 px-3 md:px-4 text-center border-t border-neutral-100">
                                        <h3 class="text-neutral-900 text-xs sm:text-sm md:text-base uppercase group-hover:text-neutral-600 transition-colors duration-300 tracking-wide font-medium">
                                            <?php echo htmlspecialchars($displayName); ?>
                                        </h3>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <style>
        /* Unique Rounded Corner Style - Responsive */
        .department-card {
            border-radius: 30px 0 30px 0;
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .copy {
            border-radius: 30px 0 30px 0;
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Tablet and Desktop - Larger rounded corners */
        @media (min-width: 768px) {
            .department-card {
                border-radius: 35px 0 35px 0;
            }

            .copy {
                border-radius: 35px 0 35px 0;
            }
        }

        @media (min-width: 1024px) {
            .department-card {
                border-radius: 40px 0 40px 0;
            }

            .copy {
                border-radius: 40px 0 40px 0;
            }
        }

        /* Image optimization */
        .department-card img {
            image-rendering: -webkit-optimize-contrast;
        }

        /* Swiper customization for responsive */
        .department-swiper-container {
            padding: 4px;
        }

        /* Hide nav buttons when not needed */
        .swiper-button-disabled {
            opacity: 0.3;
            pointer-events: none;
        }
    </style>

    <hr class="border-t border-gray-100 mt-2">

    <section class="px-4 sm:px-6 lg:px-8 py-10 ">
        <div class="max-w-full mx-auto">
            <!-- Header -->
            <div class="mb-8" data-aos="fade-up">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-1 h-8 bg-neutral-900"></div>
                    <h2 class="text-3xl lg:text-4xl font-light text-neutral-900 tracking-wide">
                        Sunpina Deals for You
                    </h2>
                </div>
                <p class="text-neutral-600 text-base lg:text-lg ml-7 font-light">
                    Exclusive offers and promotions
                </p>
            </div>

            <!-- Banner -->
            <a href="https://www.yfk20.com/login" class="group block relative overflow-hidden transition-all duration-300 pointer-events-none cursor-not-allowed" data-aos="fade-up">
                <!-- Rectangle Container -->
                <div class="relative w-full aspect-[16/9] sm:aspect-auto sm:h-auto bg-white border border-neutral-200 rounded-2xl overflow-hidden">
                    <!-- Image -->
                    <img src="../img/sunpina.png"
                        alt="Promotion Banner"
                        class="absolute inset-0 w-full h-full object-cover sm:relative sm:object-contain sm:max-h-[220px] md:max-h-[280px] lg:max-h-[380px] xl:max-h-[500px]">

                    <!-- Temporary Overlay Badge -->
                    <div class="absolute inset-0 flex items-center justify-center z-10">
                        <div class="bg-orange-500 text-white px-5 py-2 sm:px-6 sm:py-3 rounded-lg shadow-2xl transform rotate-[-5deg]">
                            <p class="text-lg sm:text-xl md:text-xl lg:text-2xl font-bold">temporary</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </section>


    <section class="px-2 sm:px-4 lg:px-6 py-1 sm:py-1 mt-4">

        <!-- Header with proper alignment -->
        <div class="flex items-center justify-between mb-6 mt-2" data-aos="fade-up">
            <!-- Left Side: Bar + Title -->
            <div class="flex items-center gap-3">
                <div class="w-1 h-8 bg-neutral-900"></div>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-light text-neutral-900 tracking-tight">
                    Furniture
                </h2>
            </div>

            <!-- Right Side: See All Button -->
            <a href="#" class="text-sm sm:text-base text-neutral-900 hover:text-neutral-600 font-light flex items-center gap-2 transition-colors duration-300 group">
                See All
                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                </svg>
            </a>
        </div>

        <!-- Products Layout: Featured Image + Product Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6" data-aos="fade-up" data-aos-delay="300">

            <!-- Left: Featured Image (Hidden on mobile, shown on desktop) -->
            <div class="hidden lg:block lg:col-span-3 xl:col-span-2">
                <div class="sticky top-4 rounded-lg overflow-hidden h-[400px] group">
                    <img src="../img/category/1.png"
                        alt="Furniture Collection"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                    <div class="absolute bottom-4 left-4 text-white">
                        <h3 class="text-xl font-light mb-1">Furniture</h3>
                        <p class="text-xs opacity-90">Discover our collection</p>
                    </div>
                </div>
            </div>

            <!-- Right: Product Swiper -->
            <div class="col-span-1 lg:col-span-9 xl:col-span-10">
                <div class="swiper mySwiper-products w-full">
                    <div class="swiper-wrapper">
                        <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                            <?php
                            $base = (float)$row['price'];
                            $percent = (float)($row['percent'] ?? 0);
                            $discount = (float)($row['discount'] ?? 0);
                            $priceWithMarkup = $base + ($base * $percent / 100);
                            $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

                            // Variables from Code 2
                            $product_id = (int)$row['id'];
                            $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                            $rating_q->bind_param("i", $product_id);
                            $rating_q->execute();
                            $rating_result = $rating_q->get_result()->fetch_assoc();
                            $avg_rating = $rating_result['avg_rating'] ?? 0;
                            $total_raters = $rating_result['total_raters'] ?? 0;
                            $rating_q->close();
                            $full = floor($avg_rating);
                            $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                            $empty = 5 - $full - $half;
                            ?>
                            <div class="swiper-slide p-1">
                                <!-- Clickable entire card for both mobile and desktop -->
                                <a href="product_view?id=<?= (int)$row['id'] ?>"
                                    class="block relative rounded overflow-hidden group hover:shadow-2xl hover:scale-100 transition-all duration-500 ease-out h-[200px] sm:h-[240px] lg:h-[300px]">

                                    <div class="absolute top-0 left-0 z-10">
                                        <div class="w-5 h-5 sm:w-6 sm:h-6 lg:w-8 lg:h-8 relative">
                                            <img src="../img/icon/d.png" alt="Icon" class="absolute top-0.5 left-0.5 w-4 h-4 sm:w-5 sm:h-5 lg:w-7 lg:h-7 object-cover" />
                                        </div>
                                    </div>

                                    <!-- Image Container with Overlay -->
                                    <div class="relative h-[110px] sm:h-[130px] lg:h-[180px] overflow-hidden">
                                        <!-- Gradient Overlay -->
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>

                                        <?php if (!empty($row['main_image'])): ?>
                                            <img src="../../<?= $row['main_image'] ?>"
                                                loading="lazy"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                class="w-full h-full object-cover sm:object-contain transition-all duration-700 group-hover:brightness-105" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                                <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Content Section -->
                                    <div class="p-1.5 sm:p-2 lg:p-2.5 flex flex-col justify-between h-[90px] sm:h-[110px] lg:h-[120px]">

                                        <!-- Product Info -->
                                        <div class="space-y-0.5 sm:space-y-1">
                                            <!-- Title -->
                                            <div class="relative w-full max-w-xs">
                                                <h3 class="text-[10px] sm:text-[11px] font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 line-clamp-2 sm:truncate pr-4">
                                                    <?= htmlspecialchars($row['product_name']) ?>
                                                </h3>
                                                <!-- Fade overlay - desktop only -->
                                                <div class="hidden sm:block absolute top-0 right-0 h-full w-4 bg-gradient-to-l from-white to-transparent"></div>
                                            </div>

                                            <!-- Rating Section -->
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
                                            <!-- Description -->
                                            <?php if (!empty($row['description'])): ?>
                                                <p class="text-[8px] sm:text-[9px] text-gray-600 leading-relaxed line-clamp-1 sm:line-clamp-2">
                                                    <?= htmlspecialchars($row['description']) ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="text-[8px] sm:text-[9px] text-gray-400 italic">No description</p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Action Button - Desktop Only -->
                                        <div class="mt-1.5 hidden lg:flex justify-start">
                                            <form action="product_view" method="GET" class="pointer-events-auto">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit"
                                                    class="flex items-center gap-1 text-black hover:text-orange-500 transition font-medium text-[10px]">
                                                    <i class="fa-solid fa-bag-shopping text-[10px]"></i>
                                                    <span>View</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* From Uiverse.io by vinodjangid07 */
            .Btn {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                width: 45px;
                height: 45px;
                border: none;
                border-radius: 0px;
                cursor: pointer;
                position: relative;
                overflow: hidden;
                transition-duration: .3s;
                box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.199);
                background-color: black;
            }

            /* icon */
            .Btn .sign {
                width: 100%;
                font-size: 1.5em;
                color: white;
                transition-duration: .3s;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* text */
            .Btn .text {
                position: absolute;
                right: 0%;
                width: 0%;
                opacity: 0;
                color: white;
                transition-duration: .3s;
            }

            /* hover effect */
            .Btn:hover {
                width: 125px;
                border-radius: 0px;
                transition-duration: .3s;
            }

            .Btn:hover .sign {
                width: 30%;
                transition-duration: .3s;
                padding-left: 20px;
            }

            .Btn:hover .text {
                opacity: 1;
                width: 70%;
                transition-duration: .3s;
                padding-right: 20px;
            }

            /* click effect */
            .Btn:active {
                transform: translate(2px, 2px);
            }
        </style>
    </section>

    <section class="py-7 px-4 sm:px-6 lg:px-8">
        <div class="max-w-full mx-auto">
            <!-- Header -->
            <div class="mb-8" data-aos="fade-up">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-1 h-8 bg-neutral-900"></div>
                    <h2 class="text-3xl lg:text-4xl font-light text-neutral-900 tracking-tight">
                        Featured Promotions
                    </h2>
                </div>
                <p class="text-neutral-600 text-base lg:text-lg ml-7 font-light">
                    Discover our latest deals and special offers
                </p>
            </div>

            <!-- 2x2 Grid Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <!-- Box 1 -->
                <a href="link-1.php" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
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
                <a href="link-2.php" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
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
                <a href="link-3.php" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
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

                <!-- Box 4 (New) -->
                <a href="link-4.php" class="group block relative overflow-hidden transition-all duration-500 hover:shadow-2xl rounded-lg">
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
                                <a href="bestseller-detail.php?slug=<?= htmlspecialchars($item['slug']) ?>"
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

    <section class="px-2 sm:px-4 lg:px-6 py-3 sm:py-2">
        <!-- Header first -->
        <div class="flex items-center justify-between gap-4 mb-2 mt-2" data-aos="fade-up">
    <!-- Left Side: Bar + Title -->
            <div class="flex items-center gap-3">
                <div class="w-1 h-8 bg-neutral-900"></div>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-light text-neutral-900 tracking-tight">
                   Bed Furniture
                </h2>
            </div>

            <!-- Right Side: See All Button -->
            <a href="#" class="text-sm sm:text-base text-orange-600 hover:text-orange-700 font-medium flex items-center gap-1 transition-colors duration-200">
                See All
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>

        <!-- Products Layout: Featured Image + Product Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6" data-aos="fade-up" data-aos-delay="300">

            <!-- Left: Featured Image (Hidden on mobile, shown on desktop) -->
            <div class="hidden lg:block lg:col-span-3 xl:col-span-2">
                <div class="sticky top-4 rounded-lg overflow-hidden h-[400px] group">
                    <img src="../img/category/4.png"
                        alt="Bed Furniture Collection"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                    <div class="absolute bottom-4 left-4 text-white">
                        <h3 class="text-xl font-light mb-1">Bed Furniture</h3>
                        <p class="text-xs opacity-90">Discover our collection</p>
                    </div>
                </div>
            </div>

            <!-- Right: Product Swiper -->
            <div class="col-span-1 lg:col-span-8 xl:col-span-9">
                <div class="swiper mySwiper-products w-full">
                    <div class="swiper-wrapper">
                        <?php while ($row = mysqli_fetch_assoc($resultss)) : ?>
                            <?php
                            $base = (float)$row['price'];
                            $percent = (float)($row['percent'] ?? 0);
                            $discount = (float)($row['discount'] ?? 0);
                            $priceWithMarkup = $base + ($base * $percent / 100);
                            $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

                            $product_id = (int)$row['id'];
                            $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                            $rating_q->bind_param("i", $product_id);
                            $rating_q->execute();
                            $rating_result = $rating_q->get_result()->fetch_assoc();
                            $avg_rating = $rating_result['avg_rating'] ?? 0;
                            $total_raters = $rating_result['total_raters'] ?? 0;
                            $rating_q->close();
                            $full = floor($avg_rating);
                            $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                            $empty = 5 - $full - $half;
                            ?>
                            <div class="swiper-slide p-1">
                                <a href="product_view?id=<?= (int)$row['id'] ?>"
                                    class="block relative rounded overflow-hidden group hover:shadow-2xl hover:scale-100 transition-all duration-500 ease-out h-[200px] sm:h-[240px] lg:h-[300px]">

                                    <div class="absolute top-0 left-0 z-10">
                                        <div class="w-5 h-5 sm:w-6 sm:h-6 lg:w-8 lg:h-8 relative">
                                            <img src="../img/icon/d.png" alt="Icon" class="absolute top-0.5 left-0.5 w-4 h-4 sm:w-5 sm:h-5 lg:w-7 lg:h-7 object-contain" />
                                        </div>
                                    </div>

                                    <div class="relative h-[110px] sm:h-[130px] lg:h-[180px] overflow-hidden">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>

                                        <?php if (!empty($row['main_image'])): ?>
                                            <img src="../../<?= $row['main_image'] ?>"
                                                loading="lazy"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                class="w-full h-full object-cover sm:object-contain transition-all duration-700 group-hover:brightness-105" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                                <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="p-1.5 sm:p-2 lg:p-2.5 flex flex-col justify-between h-[90px] sm:h-[110px] lg:h-[120px]">
                                        <div class="space-y-0.5 sm:space-y-1">
                                            <div class="relative w-full max-w-xs">
                                                <h3 class="text-[10px] sm:text-[11px] font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 line-clamp-2 sm:truncate pr-4">
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
                                                <p class="text-[8px] sm:text-[9px] text-gray-600 leading-relaxed line-clamp-1 sm:line-clamp-2">
                                                    <?= htmlspecialchars($row['description']) ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="text-[8px] sm:text-[9px] text-gray-400 italic">No description</p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-1.5 hidden lg:flex justify-start">
                                            <form action="product_view" method="GET" class="pointer-events-auto">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit"
                                                    class="flex items-center gap-1 text-black hover:text-orange-500 transition font-medium text-[10px]">
                                                    <i class="fa-solid fa-bag-shopping text-[10px]"></i>
                                                    <span>View</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="px-2 sm:px-4 lg:px-6 py-1 sm:py-2">
        <!-- Header first -->
        <div class="flex items-center gap-2 mb-2 mt-2" data-aos="fade-up">
        <!-- Left Side: Bar + Title -->
            <div class="flex items-center gap-3">
                <div class="w-1 h-8 bg-neutral-900"></div>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-light text-neutral-900 tracking-tight">
                    Building materials
                </h2>
            </div>
        </div>

        <div class="swiper mySwiper-products w-full">
            <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                <?php while ($row = mysqli_fetch_assoc($results)) : ?>
                    <?php
                    $base = (float)$row['price'];
                    $percent = (float)($row['percent'] ?? 0);
                    $discount = (float)($row['discount'] ?? 0);
                    $priceWithMarkup = $base + ($base * $percent / 100);
                    $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

                    // Variables from Code 2
                    $product_id = (int)$row['id'];
                    $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                    $rating_q->bind_param("i", $product_id);
                    $rating_q->execute();
                    $rating_result = $rating_q->get_result()->fetch_assoc();
                    $avg_rating = $rating_result['avg_rating'] ?? 0;
                    $total_raters = $rating_result['total_raters'] ?? 0;
                    $rating_q->close();
                    $full = floor($avg_rating);
                    $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                    $empty = 5 - $full - $half;
                    ?>
                    <div class="swiper-slide p-1">
                        <!-- Clickable entire card for both mobile and desktop -->
                        <a href="product_view?id=<?= (int)$row['id'] ?>"
                            class="block relative rounded overflow-hidden group hover:shadow-2xl hover:scale-100 transition-all duration-500 ease-out h-[280px] sm:h-[340px] lg:h-[450px]">

                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-6 h-6 sm:w-7 sm:h-7 lg:w-9 lg:h-9 object-contain" />
                                </div>
                            </div>

                            <!-- Image Container with Overlay -->
                            <div class="relative h-[160px] sm:h-[200px] lg:h-[280px] overflow-hidden">
                                <!-- Gradient Overlay -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>

                                <?php if (!empty($row['main_image'])): ?>
                                    <img src="../../<?= $row['main_image'] ?>"
                                        loading="lazy"
                                        alt="<?= htmlspecialchars($row['product_name']) ?>"
                                        class="w-full h-full object-contain sm:object-contain transition-all duration-700 group-hover:brightness-105" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                        <svg class="w-12 h-12 sm:w-16 sm:h-16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content Section -->
                            <div class="p-2 sm:p-3 lg:p-4 flex flex-col justify-between h-[120px] sm:h-[140px] lg:h-[170px]">

                                <!-- Product Info -->
                                <div class="space-y-1 sm:space-y-2">
                                    <!-- Title -->
                                    <div class="relative w-full max-w-xs">
                                        <h3 class="text-xs sm:text-sm font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 line-clamp-2 sm:truncate pr-6">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>
                                        <!-- Fade overlay - desktop only -->
                                        <div class="hidden sm:block absolute top-0 right-0 h-full w-6 bg-gradient-to-l from-white to-transparent"></div>
                                    </div>

                                    <!-- Rating Section -->
                                    <div class="flex items-center justify-between">
                                        <?php if ($total_raters > 0): ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-yellow-400 text-[10px] sm:text-xs">
                                                    <?php
                                                    for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                                    if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                                    for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                                                    ?>
                                                </div>
                                                <span class="text-[10px] sm:text-xs text-gray-500 font-medium"><?= $avg_rating ?></span>
                                            </div>
                                            <span class="text-[10px] sm:text-xs text-gray-400">(<?= $total_raters ?>)</span>
                                        <?php else: ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-gray-300 text-[10px] sm:text-xs">
                                                    <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                </div>
                                                <span class="text-[10px] sm:text-xs text-gray-400">No rating</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Description --><!-- Description -->
                                    <?php if (!empty($row['description'])): ?>
                                        <p class="text-[10px] sm:text-xs text-gray-600 leading-relaxed line-clamp-2">
                                            <?= htmlspecialchars($row['description']) ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-[10px] sm:text-xs text-gray-400 italic">No description</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Button - Desktop Only -->
                                <div class="mt-3 hidden lg:flex justify-start">
                                    <form action="product_view" method="GET" class="pointer-events-auto">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit"
                                            class="flex items-center gap-2 text-black hover:text-orange-500 transition font-medium text-sm">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                            <span>View</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <style>
            /* From Uiverse.io by vinodjangid07 */
            .Btn {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                width: 45px;
                height: 45px;
                border: none;
                border-radius: 0px;
                cursor: pointer;
                position: relative;
                overflow: hidden;
                transition-duration: .3s;
                box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.199);
                background-color: black;
            }

            /* icon */
            .Btn .sign {
                width: 100%;
                font-size: 1.5em;
                color: white;
                transition-duration: .3s;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* text */
            .Btn .text {
                position: absolute;
                right: 0%;
                width: 0%;
                opacity: 0;
                color: white;
                transition-duration: .3s;
            }

            /* hover effect */
            .Btn:hover {
                width: 125px;
                border-radius: 0px;
                transition-duration: .3s;
            }

            .Btn:hover .sign {
                width: 30%;
                transition-duration: .3s;
                padding-left: 20px;
            }

            .Btn:hover .text {
                opacity: 1;
                width: 70%;
                transition-duration: .3s;
                padding-right: 20px;
            }

            /* click effect */
            .Btn:active {
                transform: translate(2px, 2px);
            }
        </style>
    </section>


    <!-- Top Sales Section -->
    <section class="px-4 py-10">
        <!-- Header -->
        <div class="text-center mb-10 relative">
            <h2 class="text-4xl text-black mb-2 tracking-tight" data-aos="fade-up">Top Sales</h2>
            <h2 class="text-2xl text-black mb-2 tracking-tight" data-aos="fade-up">
                Get Up to <span class="text-red-500">10% Discount</span> on Select Items!
            </h2>

        </div>

        <?php
        // Check if there are any products
        $row_count = mysqli_num_rows($material_results);
        ?>

        <?php if ($row_count > 0): ?>
            <!-- Swiper Container -->
            <div class="swiper mySwiper-products w-full">
                <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                    <?php while ($row = mysqli_fetch_assoc($material_results)) : ?>
                        <?php
                        $base = (float)$row['price'];
                        $percent = (float)($row['percent'] ?? 0);
                        $discount = (float)($row['discount'] ?? 0);
                        $priceWithMarkup = $base + ($base * $percent / 100);
                        $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
                        ?>
                        <div class="swiper-slide p-2">
                            <div class="bg-white p-2 lg:p-3 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[320px] sm:h-[360px] lg:h-[420px] text-center relative">
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
                                <div class="mt-auto">
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
                                        <form action="product_view" method="GET">
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
                                            <form action="product_view" method="GET" class="w-full flex justify-center">
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
    </section>

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
<!-- Featured Categories Section -->
<section class="p-6">
    <div class="text-center mb-6">
        <h1 class="text-2xl">Featured Categories</h1>
        <p class="text-gray-600">Discover our top product categories just for you.</p>
    </div>

    <!-- Category Boxes - Clickable with Flex Layout -->
    <div class="flex flex-wrap gap-4 justify-center">
        <!-- Doors Category -->
        <div class="relative rounded-xl overflow-hidden hover:scale-105 transition cursor-pointer category-box w-64 h-48 group"
            onclick="loadCategoryProducts('doors')">
            <img src="../img/categ/cat1.png" alt="Doors" class="w-full h-full object-contain">
            <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                <h2 class="text-2xl text-white">Doors</h2>
            </div>
        </div>

        <!-- Aircon Category -->
        <div class="relative rounded-xl overflow-hidden hover:scale-105 transition cursor-pointer category-box w-64 h-48 group"
            onclick="loadCategoryProducts('aircon')">
            <img src="../img/categ/cat2.webp" alt="Aircon" class="w-full h-full object-contain">
            <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                <h2 class="text-2xl text-white">Aircon</h2>
            </div>
        </div>

        <!-- Bathroom Fixtures Category -->
        <div class="relative rounded-xl overflow-hidden hover:scale-105 transition cursor-pointer category-box w-64 h-48 group"
            onclick="loadCategoryProducts('bathroomfixtures')">
            <img src="../img/categ/cat3.png" alt="Bathroom Fixtures" class="w-full h-full object-contain">
            <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                <h2 class="text-2xl text-white">Bathroom Fixtures</h2>
            </div>
        </div>

        <!-- Tiles Category -->
        <div class="relative rounded-xl overflow-hidden hover:scale-105 transition cursor-pointer category-box w-64 h-48 group"
            onclick="loadCategoryProducts('tiles')">
            <img src="../img/categ/cat4.png" alt="Tiles" class="w-full h-full object-contain">
            <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                <h2 class="text-2xl text-white">Tiles</h2>
            </div>
        </div>
    </div>
</section>

<!-- Sidebar Overlay (Hidden by default) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="closeSidebar()"></div>

<!-- Sidebar for Products -->
<div id="productSidebar" class="fixed top-0 right-0 h-full w-full md:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col">
    <!-- Sidebar Header (Fixed) -->
    <div class="bg-black border-b p-4 flex justify-between items-center flex-shrink-0">
        <div class="text-white">
            <h2 class="text-xl capitalize" id="sidebarTitle">Products</h2>
            <p class="text-sm" id="sidebarSubtitle">Loading...</p>
        </div>
        <button onclick="closeSidebar()" class="text-white hover:text-gray-300 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Scroll Buttons Container -->
    <div class="flex gap-2 p-2 bg-gray-100 flex-shrink-0">
        <button id="scrollUpBtn" onclick="scrollSidebarUp()" class="flex-1 bg-black hover:bg-gray-800 text-white py-2 rounded transition-colors hidden" title="Scroll Up">
            <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
            </svg>
        </button>
        <button id="scrollDownBtn" onclick="scrollSidebarDown()" class="flex-1 bg-black hover:bg-gray-800 text-white py-2 rounded transition-colors" title="Scroll Down">
            <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </button>
    </div>

    <!-- Sidebar Content (Scrollable) -->
    <div id="sidebarContent" class="flex-1 overflow-y-hidden p-4">
        <div class="flex justify-center items-center h-40">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
        </div>
    </div>
</div>

<script>
    const scrollStep = 150;

    function loadCategoryProducts(category) {
        document.getElementById('sidebarOverlay').classList.remove('hidden');
        document.getElementById('productSidebar').classList.remove('translate-x-full');

        const categoryNames = {
            'doors': 'Doors',
            'aircon': 'Aircon',
            'bathroomfixtures': 'Bathroom Fixtures',
            'tiles': 'Tiles'
        };
        
        document.getElementById('sidebarTitle').textContent = categoryNames[category] || category;
        document.getElementById('sidebarSubtitle').textContent = 'Loading products...';
        document.getElementById('sidebarContent').innerHTML = `
            <div class="flex justify-center items-center h-40">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
            </div>
        `;

        document.getElementById('sidebarContent').scrollTop = 0;
        updateScrollButtons();

        fetch('index_fetch_category_products.php?category=' + category)
            .then(response => response.text())
            .then(data => {
                document.getElementById('sidebarContent').innerHTML = data;
                document.getElementById('sidebarSubtitle').textContent = 'Browse our collection';
                updateScrollButtons();
            })
            .catch(error => {
                document.getElementById('sidebarContent').innerHTML = `
                    <div class="text-center text-red-500 p-4">
                        <p>Error loading products. Please try again.</p>
                    </div>
                `;
            });
    }

    function scrollSidebarUp() {
        const content = document.getElementById('sidebarContent');
        content.scrollBy({ top: -scrollStep, behavior: 'smooth' });
        setTimeout(updateScrollButtons, 400);
    }

    function scrollSidebarDown() {
        const content = document.getElementById('sidebarContent');
        content.scrollBy({ top: scrollStep, behavior: 'smooth' });
        setTimeout(updateScrollButtons, 400);
    }

    function updateScrollButtons() {
        const content = document.getElementById('sidebarContent');
        const scrollUpBtn = document.getElementById('scrollUpBtn');
        const scrollDownBtn = document.getElementById('scrollDownBtn');

        if (content.scrollTop > 0) {
            scrollUpBtn.classList.remove('hidden');
        } else {
            scrollUpBtn.classList.add('hidden');
        }

        if (content.scrollTop < content.scrollHeight - content.clientHeight - 10) {
            scrollDownBtn.classList.remove('hidden');
        } else {
            scrollDownBtn.classList.add('hidden');
        }
    }

    function closeSidebar() {
        document.getElementById('sidebarOverlay').classList.add('hidden');
        document.getElementById('productSidebar').classList.add('translate-x-full');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    document.getElementById('sidebarContent').addEventListener('scroll', updateScrollButtons);
</script>

<style>
    .category-box {
        transition: all 0.3s ease;
    }

    .category-box:hover {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .category-box:active {
        transform: scale(0.98);
    }
</style>

    <!-- Top Sales Section -->
    <section class="px-4 py-10">
        <!-- Header -->
        <div class="text-center mb-10 relative">
            <h2 class="text-4xl text-black mb-2 tracking-tight" data-aos="fade-up">Discounted Minimal</h2>
            <h2 class="text-2xl text-black mb-2 tracking-tight" data-aos="fade-up">
                Get Up to <span class="text-red-500">5% Discount</span> on Select Items!
            </h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
        </div>

        <?php
        // Check if there are any products
        $row_count = mysqli_num_rows($material_resultsone);
        ?>

        <?php if ($row_count > 0): ?>
            <!-- Swiper Container -->
            <div class="swiper mySwiper-products w-full">
                <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                    <?php while ($row = mysqli_fetch_assoc($material_resultsone)) : ?>
                        <?php
                        $base = (float)$row['price'];
                        $percent = (float)($row['percent'] ?? 0);
                        $discount = (float)($row['discount'] ?? 0);
                        $priceWithMarkup = $base + ($base * $percent / 100);
                        $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
                        ?>
                        <div class="swiper-slide p-2">
                            <div class="bg-white p-2 lg:p-3 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[320px] sm:h-[360px] lg:h-[420px] text-center relative">
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
                                            class="w-full h-full object-cover lg:object-contain transition-transform duration-300 group-hover:scale-105" />
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Product Info -->
                                <div class="mt-auto">
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
                                        <form action="product_view" method="GET">
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
                                            <form action="product_view" method="GET" class="w-full flex justify-center">
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
    </section>

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
                        ?>
                        <div class="swiper-slide p-2">
                            <div class="bg-white p-2 lg:p-3 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[320px] sm:h-[360px] lg:h-[420px] text-center relative">
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
                                            class="w-full h-full object-cover lg:object-contain transition-transform duration-300 group-hover:scale-105" />
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Product Info -->
                                <div class="mt-auto">
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
                                        <form action="product_view" method="GET">
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
                                            <form action="product_view" method="GET" class="w-full flex justify-center">
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
    </section>

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
    <!-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->


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

    <section class="py-12 md:py-24 px-4">
        <div class="max-w-6xl mx-auto text-center mb-12 md:mb-20">
            <h2 class="text-3xl md:text-5xl mb-4 md:mb-6 bg-clip-text text-black">What Our Customers Say</h2>
            <p class="text-base md:text-lg text-gray-600 max-w-2xl mx-auto">Here's what people are saying about their experience with us.</p>
        </div>

        <!-- Swiper Container -->
        <div class="swiper reviewCarousel max-w-3xl mx-auto relative">
            <div class="swiper-wrapper" id="reviewWrapper">
                <!-- Loading placeholder -->
                <div class="swiper-slide">
                    <div class="testimonial-card p-6 md:p-10 mx-2 md:mx-4">
                        <div class="text-center py-8">
                            <div class="animate-pulse">
                                <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto mb-4"></div>
                                <div class="h-4 bg-gray-200 rounded w-1/2 mx-auto"></div>
                            </div>
                            <p class="text-gray-500 mt-4">Loading reviews...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="swiper-pagination mt-8"></div>
        </div>

    </section>




    <?php include '../navbar/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <script>
        AOS.init();
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const reviewWrapper = document.getElementById("reviewWrapper");
            let reviewSwiper = null;

            // Initialize Swiper with dynamic loop
            function initReviewSwiper(slideCount) {
                // Destroy existing instance if any
                if (reviewSwiper) {
                    reviewSwiper.destroy(true, true);
                }

                reviewSwiper = new Swiper(".reviewCarousel", {
                    loop: slideCount > 1, // Only loop if more than 1 review
                    autoplay: slideCount > 1 ? {
                        delay: 4000,
                        disableOnInteraction: false
                    } : false, // Disable autoplay for single slide
                    pagination: {
                        el: ".swiper-pagination",
                        clickable: true
                    },
                    slidesPerView: 1,
                    spaceBetween: 20,
                    effect: "slide",
                    speed: 700,
                    breakpoints: {
                        640: {
                            spaceBetween: 30
                        }
                    }
                });
            }

            async function loadReviews() {
                try {
                    const res = await fetch("profilefetch_reviews.php");
                    const reviews = await res.json();

                    reviewWrapper.innerHTML = "";

                    if (reviews.length > 0) {
                        reviews.forEach(r => {
                            let stars = "";
                            for (let i = 1; i <= 5; i++) {
                                stars += `<i class="${i <= r.rating ? "fas" : "far"} fa-star text-lg md:text-xl text-yellow-400"></i>`;
                            }

                            reviewWrapper.innerHTML += `
                        <div class="swiper-slide">
                            <div class="testimonial-card p-6 md:p-10 mx-2 md:mx-4">
                                <div class="flex justify-center mb-4 md:mb-6 space-x-1">
                                    ${stars}
                                </div>
                                <p class="text-gray-700 italic leading-relaxed mb-6 md:mb-8 text-base md:text-lg text-center font-medium px-2 md:px-6">
                                    "${r.comment}"
                                </p>
                                <div class="flex items-center justify-center space-x-3 md:space-x-4">
                                    <div class="profile-ring">
                                        <img src="${r.profile_picture}" alt="${r.name}"
                                             class="w-12 h-12 md:w-16 md:h-16 rounded-full object-cover">
                                    </div>
                                    <div class="text-left">
                                        <h4 class="name-highlight font-bold text-base md:text-lg">${r.name}</h4>
                                        <p class="text-xs md:text-sm text-gray-500 font-medium">${new Date(r.created_at).toLocaleDateString()}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                        });

                        // Re-initialize Swiper with the actual review count
                        initReviewSwiper(reviews.length);
                    } else {
                        // Empty state - single slide
                        reviewWrapper.innerHTML = `
                    <div class="swiper-slide">
                        <div class="testimonial-card p-6 md:p-10 mx-2 md:mx-4">
                            <div class="text-center py-8 md:py-12">
                                <p class="text-gray-600 text-base md:text-lg">No reviews yet.</p>
                            </div>
                        </div>
                    </div>
                `;

                        // Re-initialize with loop disabled for single slide
                        initReviewSwiper(1);
                    }
                } catch (err) {
                    console.error("Error fetching reviews:", err);

                    // Error state - single slide
                    reviewWrapper.innerHTML = `
                <div class="swiper-slide">
                    <div class="testimonial-card p-6 md:p-10 mx-2 md:mx-4">
                        <div class="text-center py-8 md:py-12">
                            <p class="text-red-500 text-base md:text-lg">Error loading reviews. Please try again.</p>
                        </div>
                    </div>
                </div>
            `;

                    // Re-initialize with loop disabled for single slide
                    initReviewSwiper(1);
                }
            }

            // Initial load
            loadReviews();

            // Auto refresh every 10 seconds
            setInterval(loadReviews, 10000);
        });

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

        //  Product form submit handler
        async function handleProductFormSubmit(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;

            // Show loading state
            button.disabled = true;
            button.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span> Adding...';

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

                    button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Added!';
                    button.className = button.className.replace('bg-orange-500 hover:bg-orange-600', 'bg-green-500');

                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.className = button.className.replace('bg-green-500', 'bg-orange-500 hover:bg-orange-600');
                        button.disabled = false;
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Add to cart failed.');
                }
            } catch (error) {
                showNotification(' ' + error.message, 'error');
                console.error('Add to cart error:', error);

                button.innerHTML = originalText;
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
                            slidesPerView: 3,
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
                        slidesPerView: 3,
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
        });
    </script>

</body>

</html>