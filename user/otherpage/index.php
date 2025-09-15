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

// 3. Discount 30% materials - FIXED: descrip6, descrip7 from products table
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
    WHERE pv.discount = 30
    ORDER BY pv.percent ASC, p.id ASC
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
    WHERE pv.discount BETWEEN 1 AND 15
    GROUP BY p.id
    ORDER BY pv.percent ASC, p.id ASC, pc.id ASC
";
$material_resultsone = mysqli_query($conn, $material_querysone);


// 5. Fetch "new" status product variants - FIXED: added descrip6, descrip7 from products table
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
        p.descrip6,   -- Extra description field 6 from products table
        p.descrip7,   -- Extra description field 7 from products table
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    WHERE pv.status = 'new'
    GROUP BY pv.id   -- group by variant para isang kulay lang ma-fetch
    ORDER BY pv.percent ASC, p.id ASC
";
$material_resultstwo = mysqli_query($conn, $material_querystwo);



// 6. Products without discount - OPTIMIZED with consistent field selection
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
     ORDER BY pv.percent ASC, p.id ASC, pc.id ASC"
);

// 7. Filter by furniture codename - OPTIMIZED with consistent joins
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
    ORDER BY p.id DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$result = $stmt->get_result();

// 8. Filter by material codename with rating - OPTIMIZED
$filter = 'material';
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
    ORDER BY p.id DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter);
$stmt->execute();
$results = $stmt->get_result();

// 9. Filter by bedfurniture codename - OPTIMIZED with consistent joins
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
    ORDER BY p.id DESC
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
    <!-- Alpine.js CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=aspect-ratio"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js" defer></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
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

        .swiper,
        .swiper-wrapper {
            height: 100%
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

<body class="font-mont">

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
      bg-red-100 border border-red-300 text-red-800 px-6 py-3 rounded shadow-lg transition-opacity duration-1000">
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



    <section class="w-full bg-gray-100 overflow-hidden">
        <div class="mySwiper relative w-full">
            <div class="swiper-wrapper">
                <?php while ($row = $slideresult->fetch_assoc()): ?>
                    <div class="swiper-slide h-[300px] sm:h-[400px] md:h-[500px] lg:h-[600px]">
                        <img src="../../uploads/<?= htmlspecialchars($row['filename']) ?>" alt="Discount"
                            class="w-full h-full object-cover rounded" />
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Swiper Buttons -->
            <div class="swiper-button-next absolute top-1/2 -translate-y-1/2 right-2 sm:right-4 md:right-6 z-10 w-8 h-8 bg-white bg-opacity-70 rounded-full flex items-center justify-center shadow-md mt-3"></div>
            <div class="swiper-button-prev absolute top-1/2 -translate-y-1/2 left-2 sm:left-4 md:left-6 z-10 w-8 h-8 bg-white bg-opacity-70 rounded-full flex items-center justify-center shadow-md mt-3"></div>
        </div>
    </section>



    <section class="bg-orange-400 text-white py-1 px-2 shadow-md">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">

            <!-- Discount Text -->
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 14l6-6M15 14l-6-6M9 10h6v4H9z" />
                </svg>
                <p class="text-lg font-semibold">
                    Exclusive Deals! <span class="underline font-bold">Discounted Items Available</span>
                </p>
            </div>

            <!-- Action Button -->
            <a href="allproduct.php?discount=all"
                class="bg-white text-orange-600 hover:bg-gray-100 font-semibold px-5 py-1 rounded-lg shadow transition">
                Shop Now
            </a>
        </div>
    </section>


    <!-- Scrolling Text -->
    <div class="overflow-hidden bg-orange-500 text-white">
        <div class="flex animate-marquee whitespace-nowrap">
            <!-- Unang set -->
            <span class="mx-10"> Big Sale Coming Soon!</span>
            <span class="mx-10"> Exclusive Discounts Await!</span>
            <span class="mx-10"> Shop Now & Save More!</span>
            <span class="mx-10"> Free Shipping on Selected Items!</span>

            <!-- Duplicate set para tuloy-tuloy -->
            <span class="mx-10"> Big Sale Coming Soon!</span>
            <span class="mx-10"> Exclusive Discounts Await!</span>
            <span class="mx-10"> Shop Now & Save More!</span>
            <span class="mx-10"> Free Shipping on Selected Items!</span>
        </div>
    </div>

    <style>
        @keyframes marquee {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        .animate-marquee {
            display: inline-flex;
            min-width: 200%;
            /* para may continuous na kopya */
            animation: marquee 20s linear infinite;
        }
    </style>

    <!-- POPUP MODAL -->
    <div id="promoPopup" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50">
        <div class="relative max-w-4xl w-full mx-4">
            <!-- Close Button -->
            <button onclick="hidePromoModal()"
                class="absolute -top-4 -right-4 text-white hover:text-red-500 bg-black/70 rounded-full w-10 h-10 flex items-center justify-center text-2xl z-10 transition-colors duration-300">✕</button>

            <!-- Carousel Container -->
            <div class="relative overflow-hidden">
                <div id="slideContainer" class="flex transition-transform duration-700 ease-in-out">
                    <!-- Slide 1 -->
                    <a href="allproduct.php?discount=20" class="flex-shrink-0 w-full relative group flex items-center justify-center">
                        <img src="../img/sale/c.png" alt="20% OFF Sale" class="max-w-full max-h-[80vh] object-contain">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-8">
                            <span class="text-white text-4xl font-extrabold tracking-wide">Shop 20% OFF</span>
                        </div>
                    </a>

                    <!-- Slide 2 -->
                    <a href="allproduct.php?discount=30" class="flex-shrink-0 w-full relative group flex items-center justify-center">
                        <img src="../img/sale/c.png" alt="30% OFF Sale" class="max-w-full max-h-[80vh] object-contain">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-8">
                            <span class="text-white text-4xl font-extrabold tracking-wide">Shop 30% OFF</span>
                        </div>
                    </a>

                    <!-- Slide 3 -->
                    <a href="allproduct.php?discount=50" class="flex-shrink-0 w-full relative group flex items-center justify-center">
                        <img src="../img/sale/c.png" alt="50% OFF Sale" class="max-w-full max-h-[80vh] object-contain">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-8">
                            <span class="text-white text-4xl font-extrabold tracking-wide">Shop 50% OFF</span>
                        </div>
                    </a>
                </div>

                <!-- Left Arrow -->
                <button onclick="moveToPreviousSlide()"
                    class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white/20 backdrop-blur-sm text-white hover:bg-white/30 w-12 h-12 rounded-full text-2xl flex items-center justify-center transition-all duration-300 shadow-lg">
                    ‹
                </button>

                <!-- Right Arrow -->
                <button onclick="moveToNextSlide()"
                    class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white/20 backdrop-blur-sm text-white hover:bg-white/30 w-12 h-12 rounded-full text-2xl flex items-center justify-center transition-all duration-300 shadow-lg">
                    ›
                </button>
            </div>

            <!-- Slide Indicators -->
            <div class="flex justify-center mt-6 gap-2">
                <button onclick="jumpToSpecificSlide(0)" class="slide-indicator w-3 h-3 rounded-full bg-white/50 hover:bg-white/80 transition-colors duration-300"></button>
                <button onclick="jumpToSpecificSlide(1)" class="slide-indicator w-3 h-3 rounded-full bg-white/50 hover:bg-white/80 transition-colors duration-300"></button>
                <button onclick="jumpToSpecificSlide(2)" class="slide-indicator w-3 h-3 rounded-full bg-white/50 hover:bg-white/80 transition-colors duration-300"></button>
            </div>
        </div>
    </div>

    <script>
        let activeSlidePosition = 0;
        const maxSlideCount = document.querySelectorAll("#slideContainer a").length;
        const carouselWrapper = document.getElementById("slideContainer");
        const dotIndicators = document.querySelectorAll(".slide-indicator");

        function moveToSlidePosition(pos) {
            if (pos >= maxSlideCount) activeSlidePosition = 0;
            else if (pos < 0) activeSlidePosition = maxSlideCount - 1;
            else activeSlidePosition = pos;

            carouselWrapper.style.transform = `translateX(-${activeSlidePosition * 100}%)`;
            refreshIndicatorDots();
        }

        function refreshIndicatorDots() {
            dotIndicators.forEach((dot, idx) => {
                if (idx === activeSlidePosition) {
                    dot.classList.remove('bg-white/50');
                    dot.classList.add('bg-white');
                } else {
                    dot.classList.remove('bg-white');
                    dot.classList.add('bg-white/50');
                }
            });
        }

        function moveToNextSlide() {
            moveToSlidePosition(activeSlidePosition + 1);
        }

        function moveToPreviousSlide() {
            moveToSlidePosition(activeSlidePosition - 1);
        }

        function jumpToSpecificSlide(pos) {
            moveToSlidePosition(pos);
        }

        // Auto-advance slides every 5 seconds
        let carouselTimer = setInterval(() => {
            moveToNextSlide();
        }, 5000);

        // Pause auto-advance on hover
        document.getElementById('promoPopup').addEventListener('mouseenter', () => {
            clearInterval(carouselTimer);
        });

        // Resume auto-advance when mouse leaves
        document.getElementById('promoPopup').addEventListener('mouseleave', () => {
            carouselTimer = setInterval(() => {
                moveToNextSlide();
            }, 5000);
        });

        // Popup management functions
        function displayPromoModal() {
            document.getElementById('promoPopup').classList.remove('hidden');
            const timestamp = Date.now();
            // Store timestamp in session that persists across page reloads
            try {
                sessionStorage.setItem('promoModalLastShown', timestamp.toString());
            } catch (e) {
                // Fallback to window object if sessionStorage fails
                window.modalLastShown = timestamp;
            }
        }

        function hidePromoModal() {
            document.getElementById('promoPopup').classList.add('hidden');
            clearInterval(carouselTimer);
        }

        function getLastShownTime() {
            try {
                const stored = sessionStorage.getItem('promoModalLastShown');
                return stored ? parseInt(stored) : null;
            } catch (e) {
                // Fallback to window object
                return window.modalLastShown || null;
            }
        }

        function setupModalSchedule() {
            const currentTimestamp = Date.now();
            const lastShownTime = getLastShownTime();

            console.log('Current time:', new Date(currentTimestamp).toLocaleTimeString());

            if (!lastShownTime) {
                console.log('First visit - popup will show in 5 seconds');
                setTimeout(displayPromoModal, 5000);
            } else {
                const elapsedSeconds = Math.floor((currentTimestamp - lastShownTime) / 1000);
                const elapsedMinutes = Math.floor(elapsedSeconds / 60);

                console.log(`Last shown: ${new Date(lastShownTime).toLocaleTimeString()}`);
                console.log(`Time elapsed: ${elapsedMinutes} minutes and ${elapsedSeconds % 60} seconds`);

                if (elapsedSeconds >= 300) { // 5 minutes = 300 seconds
                    console.log('5+ minutes passed - showing popup now');
                    displayPromoModal();
                } else {
                    const remainingSeconds = 300 - elapsedSeconds;
                    const remainingMinutes = Math.floor(remainingSeconds / 60);
                    console.log(`Popup will show in ${remainingMinutes} minutes and ${remainingSeconds % 60} seconds`);

                    setTimeout(displayPromoModal, remainingSeconds * 1000);
                }
            }
        }

        // Initialize popup logic
        setupModalSchedule();

        // Initialize indicators
        refreshIndicatorDots();
    </script>

    <section class="bg-white shadow-md py-2 px-4 sm:px-6 rounded-lg" x-data="{ currentModal: null }">
        <div class="max-w-7xl mx-auto">
            <!-- Mobile View (lg and below) - Swiper -->
            <div class="block lg:hidden">
                <div class="swiper contact-swiper" x-init="
                    setTimeout(() => {
                        new Swiper($el, {
                            slidesPerView: 1.2,
                            spaceBetween: 16,
                            centeredSlides: false,
                            breakpoints: {
                                480: {
                                    slidesPerView: 1.8,
                                    spaceBetween: 20,
                                },
                                640: {
                                    slidesPerView: 2.5,
                                    spaceBetween: 20,
                                }
                            }
                        });
                    }, 100);
                ">
                    <div class="swiper-wrapper">
                        <!-- Slide 1 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 1"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center  bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Inquire</h3>
                                <p class="text-sm text-gray-700 mt-1">Send us a question or message.</p>
                            </button>
                        </div>

                        <!-- Slide 2 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 2"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center  bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Appointment</h3>
                                <p class="text-sm text-gray-700 mt-1">Book a consultation now.</p>
                            </button>
                        </div>

                        <!-- Slide 3 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 3"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center  bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Track Order</h3>
                                <p class="text-sm text-gray-700 mt-1">Check your order status.</p>
                            </button>
                        </div>

                        <!-- Slide 4 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 4"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center  bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Request Quote</h3>
                                <p class="text-sm text-gray-700 mt-1">Get pricing for your project.</p>
                            </button>
                        </div>

                        <!-- Slide 5 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 5"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center  bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Support</h3>
                                <p class="text-sm text-gray-700 mt-1">We're here to help you.</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop View (lg and above) - Grid -->
            <div class="hidden lg:grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <!-- Box 1 -->
<button @click="currentModal = 1"
  class="group w-full  transition duration-200 rounded-lg p-4 text-center bg-white h-full min-h-[100px]">

  <h3 class="text-lg font-semibold text-orange-700 relative inline-block">
    Inquire
    <span class="absolute left-0 -bottom-1 w-0 h-[2px] bg-orange-700 transition-all duration-300 group-hover:w-full"></span>
  </h3>

  <p class="text-sm text-gray-700 mt-1">Send us a question or message.</p>
</button>

<!-- Box 2 -->
<button @click="currentModal = 2"
  class="group w-full  transition duration-200 rounded-lg p-4 text-center bg-white h-full min-h-[100px]">

  <h3 class="text-lg font-semibold text-orange-700 relative inline-block">
    Appointment
    <span class="absolute left-0 -bottom-1 w-0 h-[2px] bg-orange-700 transition-all duration-300 group-hover:w-full"></span>
  </h3>

  <p class="text-sm text-gray-700 mt-1">Book a consultation now.</p>
</button>

<!-- Box 3 -->
<button @click="currentModal = 3"
  class="group w-full  transition duration-200 rounded-lg p-4 text-center bg-white h-full min-h-[100px]">

  <h3 class="text-lg font-semibold text-orange-700 relative inline-block">
    Track Order
    <span class="absolute left-0 -bottom-1 w-0 h-[2px] bg-orange-700 transition-all duration-300 group-hover:w-full"></span>
  </h3>

  <p class="text-sm text-gray-700 mt-1">Check your order status.</p>
</button>

<!-- Box 4 -->
<button @click="currentModal = 4"
  class="group w-full  transition duration-200 rounded-lg p-4 text-center bg-white h-full min-h-[100px]">

  <h3 class="text-lg font-semibold text-orange-700 relative inline-block">
    Request Quote
    <span class="absolute left-0 -bottom-1 w-0 h-[2px] bg-orange-700 transition-all duration-300 group-hover:w-full"></span>
  </h3>

  <p class="text-sm text-gray-700 mt-1">Get pricing for your project.</p>
</button>

<!-- Box 5 -->
<button @click="currentModal = 5"
  class="group w-full  transition duration-200 rounded-lg p-4 text-center bg-white h-full min-h-[100px]">

  <h3 class="text-lg font-semibold text-orange-700 relative inline-block">
    Support
    <span class="absolute left-0 -bottom-1 w-0 h-[2px] bg-orange-700 transition-all duration-300 group-hover:w-full"></span>
  </h3>

  <p class="text-sm text-gray-700 mt-1">We're here to help you.</p>
</button>

            </div>
        </div>

        <!-- Modals -->
        <template x-if="currentModal">
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto px-4">
                <div class="bg-white w-full max-w-md p-6 mt-10 mb-10 rounded-lg shadow-lg relative modal-enter">
                    <!-- Modal Title -->
                    <h2 class="text-xl font-bold text-orange-600 mb-2" x-text="{
                        1: 'Inquire',
                        2: 'Appointment',
                        3: 'Track Order',
                        4: 'Request Quote',
                        5: 'Support'
                    }[currentModal]"></h2>

                    <!-- Modal Description -->
                    <p class="text-sm text-gray-700 mb-4" x-text="{
                        1: 'Send us your questions, concerns, or feedback. Our team is ready to assist you anytime.',
                        2: 'Schedule a face-to-face or virtual consultation with our experts.',
                        3: 'Enter your order ID to track the delivery progress and timeline.',
                        4: 'Get a detailed quote based on your construction needs and preferences.',
                        5: 'Need help? Our support team is always ready to guide you through any issues.'
                    }[currentModal]"></p>

                    <!-- Close Button -->
                    <button @click="currentModal = null" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    <button @click="currentModal = null" class="mt-4 w-full bg-orange-500 hover:bg-orange-600 text-white py-2 rounded-md transition duration-200">
                        Close
                    </button>
                </div>
            </div>
        </template>
    </section>



    <section class="px-4 py-8 bg-white" x-data="{ selectedCategory: null }">
        <!-- Heading and description -->
        <div class="text-center mb-8">
            <h2 class="text-3xl sm:text-4xl font-bold text-black mb-3">Shop by Categories</h2>
            <p class="text-gray-600 text-base sm:text-lg max-w-2xl mx-auto">
                Discover our wide range of home improvement products organized by category
            </p>
        </div>

        <!-- Mobile View (lg and below) - Swiper -->
        <div class="block lg:hidden">
            <div class="swiper category-swiper" x-init="
        setTimeout(() => {
            new Swiper($el, {
                slidesPerView: 2.2,
                spaceBetween: 12,
                centeredSlides: false,
                breakpoints: {
                    480: {
                        slidesPerView: 3.2,
                        spaceBetween: 16,
                    },
                    640: {
                        slidesPerView: 4.5,
                        spaceBetween: 20,
                    }
                }
            });
            lucide.createIcons();
        }, 100);
    ">
                <div class="swiper-wrapper pb-4">
                    <!-- Furniture -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=furniture" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/1.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Furniture</span>
                            </div>
                        </a>
                    </div>

                    <!-- Materials -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=materials" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/3.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Building Materials</span>
                            </div>
                        </a>
                    </div>

                    <!-- Bedroom Furniture -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=bedfurniture" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/4.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Bedroom</span>
                            </div>
                        </a>
                    </div>

                    <!-- Lighting -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=lighting" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/5.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Lighting</span>
                            </div>
                        </a>
                    </div>

                    <!-- Aircon -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=aircon" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/6.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Aircon</span>
                            </div>
                        </a>
                    </div>

                    <!-- Doors -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=doors" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/7.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Doors</span>
                            </div>
                        </a>
                    </div>

                    <!-- Tiles -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=tiles" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/8.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Tiles</span>
                            </div>
                        </a>
                    </div>

                    <!-- Windows -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=windows" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/9.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Windows</span>
                            </div>
                        </a>
                    </div>

                    <!-- Bathroom -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=bathroom" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/10.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Bathroom</span>
                            </div>
                        </a>
                    </div>

                    <!-- Kitchen -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=kitchen" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/11.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Kitchen</span>
                            </div>
                        </a>
                    </div>

                    <!-- Pipes -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=pipes" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/2.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">Pipes</span>
                            </div>
                        </a>
                    </div>

                    <!-- AAC Blocks -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=aacblock" class="group block">
                            <div class="bg-white border-2 border-orange-200 rounded-xl p-4 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 shadow-sm hover:shadow-md">
                                <div class="w-full h-full bg-orange-100 rounded-lg flex items-center justify-center mb-2 group-hover:bg-orange-200 transition-colors">
                                    <img src="../img/category/12.png" alt="Furniture" class="w-full h-full object-contain">
                                </div>
                                <span class="text-xs font-semibold text-gray-700 text-center">AAC Blocks</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop View (lg and above) - Grid Layout -->
        <div class="hidden lg:block max-w-6xl mx-auto">
            <div class="grid grid-cols-6 gap-6">
                <!-- Row 1 -->
                <a href="shop?category[]=furniture" class="group">
                    <div class="p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/1.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Furniture</span>
                    </div>
                </a>

                <a href="shop?category[]=materials" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/3.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Material Boards</span>
                    </div>
                </a>

                <a href="shop?category[]=bedfurniture" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/4.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Bedroom </span>
                    </div>
                </a>

                <a href="shop?category[]=lighting" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/5.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Lighting fixture</span>
                    </div>
                </a>

                <a href="shop?category[]=aircon" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/6.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Aircon</span>
                    </div>
                </a>

                <a href="shop?category[]=doors" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/7.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Doors</span>
                    </div>
                </a>

                <!-- Row 2 -->
                <a href="shop?category[]=tiles" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/8.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Tiles</span>
                    </div>
                </a>

                <a href="shop?category[]=windows" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/9.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Windows</span>
                    </div>
                </a>

                <a href="shop?category[]=bathroom" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/10.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Bathroom </span>
                    </div>
                </a>

                <a href="shop?category[]=kitchen" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/11.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Kitchen Fixtures</span>
                    </div>
                </a>

                <a href="shop?category[]=pipes" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/2.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">Pipes</span>
                    </div>
                </a>

                <a href="shop?category[]=aacblock" class="group">
                    <div class=" p-6 h-36 flex flex-col items-center justify-center hover:border-orange-400 hover:bg-orange-50 transition-all duration-300  hover:shadow-md">
                        <div class="w-full h-full bg-orange-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-orange-200 transition-colors">
                            <img src="../img/category/12.png" alt="Furniture" class="w-full h-full object-contain">
                        </div>
                        <span class="text-sm font-semibold text-black text-center">AAC BLOCKS</span>
                    </div>
                </a>

            </div>
        </div>

        <!-- Init Lucide icons -->
        <script>
            lucide.createIcons();
        </script>
    </section>


<section class="px-4 py-12">
    <div class="max-w-full mx-auto">
        <!-- Section Header -->
        <div class="text-center mb-10 " data-aos="fade-up">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-3">Smart Savings for Every Project</h2>
            <p class="text-gray-600 text-base md:text-lg">Save big on quality home improvement products</p>
        </div>

        <!-- Cards Container -->
        <div class="flex flex-col sm:flex-row gap-4 md:gap-6">
            
            <!-- Featured Promotion -->
            <div class="flex-1 overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:scale-105" data-aos="fade-up">
                <div class="w-full h-48 md:h-56 relative overflow-hidden">
                    <img src="../img/promo/a.png" alt="Featured Sale" class="w-full h-full object-contain ">
                </div>
                <div class="p-4 md:p-6 text-center">
                    <div class="mb-3">
                        <span class="bg-black text-white px-3 py-1 rounded-full text-xs font-bold shadow-md">
                            Featured Deal
                        </span>
                    </div>
                    <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-2">
                        Home Renovation Sale
                    </h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">
                        Get up to 50% off on selected home improvement products. Perfect time to upgrade your space.
                    </p>
               
                </div>
            </div>

            <!-- Weekly Sale -->
            <div class="flex-1 overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:scale-105" data-aos="fade-up">
                <div class="w-full h-48 md:h-56 relative overflow-hidden">
                    <img src="../img/promo/2.png" alt="Sale Items" class="w-full h-full object-contain">
                </div>
                <div class="p-4 md:p-6 text-center">
                    <div class="mb-3">
                        <span class="bg-black text-white px-3 py-1 rounded-full text-xs font-bold shadow-md">
                            SALE
                        </span>
                    </div>
                    <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-2">
                        Weekly Sale
                    </h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">
                        Discounted items refreshed every week. Check back regularly for new deals and amazing savings.
                    </p>
                
                </div>
            </div>

            <!-- New Arrivals -->
            <div class="flex-1 overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:scale-105" data-aos="fade-up">
                <div class="w-full h-48 md:h-56 relative overflow-hidden">
                    <img src="../img/promo/3.png" alt="New Arrivals" class="w-full h-full object-contain ">
                </div>
                <div class="p-4 md:p-6 text-center">
                    <div class="mb-3">
                        <span class="bg-black text-white px-3 py-1 rounded-full text-xs font-bold shadow-md">
                            NEW
                        </span>
                    </div>
                    <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-2">
                        New Arrivals
                    </h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">
                        Fresh inventory just arrived. Be the first to get the latest products and trending items.
                    </p>
             
                </div>
            </div>

            <!-- Hot Deals -->
            <div class="flex-1 overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:scale-105" data-aos="fade-up">
                <div class="w-full h-48 md:h-56 relative overflow-hidden">
                    <img src="../img/promo/4.png" alt="Hot Deals" class="w-full h-full object-contain ">
                </div>
                <div class="p-4 md:p-6 text-center">
                    <div class="mb-3">
                        <span class="bg-black text-white px-3 py-1 rounded-full text-xs font-bold shadow-md animate-pulse">
                            HOT DEAL
                        </span>
                    </div>
                    <h3 class="text-lg md:text-xl font-bold text-gray-900 mb-2">
                        Hot Deals
                    </h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">
                        Limited quantity deals that won't last long. Grab them while supplies last - act fast!
                    </p>
                  
                </div>
            </div>
        </div>
    </div>
</section>


    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10 ">
        <!-- Header first -->
     
            <div class="flex items-center gap-2 mb-2 mt-4" data-aos="fade-up">
                <!-- Details Button (as Title) -->
                <a href="shop.php"
                    class="group relative inline-flex items-center gap-2 font-bold text-2xl sm:text-3xl lg:text-4xl text-black">
                    <span class="relative">
                        <span class="block group-hover:text-orange-600 transition-colors duration-300">
                            Bed Furniture
                        </span>
                        <!-- Animated overlay text -->
                        <span class="absolute inset-0 w-0 overflow-hidden text-orange-600 transition-all duration-300 group-hover:w-full">
                            Bed Furniture
                        </span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24"
                        stroke-width="2.5" stroke="currentColor"
                        class="w-7 h-7 transform transition-transform duration-300 group-hover:translate-x-1 group-hover:text-orange-600 ">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <!-- underline -->
                    <span class="absolute left-0 -bottom-1 h-0.5 w-0 bg-orange-600 transition-all duration-300 group-hover:w-full"></span>
                </a>
            </div>


  
        <style>
            .bubble-bounce {
                position: absolute;
                display: inline-block;
                opacity: 0.18;
                border-radius: 50%;
                animation: bubble-bounce 2.2s cubic-bezier(.68, -0.55, .27, 1.55) infinite;
                box-shadow: 0 8px 32px 0 rgba(251, 146, 60, 0.25);
            }

            @keyframes bubble-bounce {

                0%,
                100% {
                    transform: translateY(0) scale(1);
                }

                50% {
                    transform: translateY(-30px) scale(1.08);
                }
            }
        </style>

        <div class="swiper mySwiper-products w-full">
            <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                <?php while ($row = mysqli_fetch_assoc($resultss)) : ?>
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
                        <div class="relative rounded overflow-hidden group hover:shadow-2xl hover:scale-100 transition-all duration-500 ease-out h-[450px] ">

                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>


                            <!-- Image Container with Overlay -->
                            <div class="relative h-[280px] overflow-hidden">
                                <!-- Gradient Overlay -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>

                                <?php if (!empty($row['main_image'])): ?>
                                    <img src="../../<?= $row['main_image'] ?>"
                                        loading="lazy"
                                        alt="<?= htmlspecialchars($row['product_name']) ?>"
                                        class="w-full h-full object-cover transition-all duration-700 group-hover:scale-110 group-hover:brightness-105" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                        <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content Section -->
                            <div class="p-4 flex flex-col justify-between h-[170px]">

                                <!-- Product Info -->
                                <div class="space-y-2">
                                    <!-- Title -->
                                    <h3 class="text-sm font-bold text-gray-800 leading-tight line-clamp-2 group-hover:text-orange-600 transition-colors duration-300">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h3>



                                    <!-- Rating Section -->
                                    <div class="flex items-center justify-between">
                                        <?php if ($total_raters > 0): ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-yellow-400 text-xs">
                                                    <?php
                                                    for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                                    if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                                    for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                                                    ?>
                                                </div>
                                                <span class="text-xs text-gray-500 font-medium"><?= $avg_rating ?></span>
                                            </div>
                                            <span class="text-xs text-gray-400">(<?= $total_raters ?> reviews)</span>
                                        <?php else: ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-gray-300 text-xs">
                                                    <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                </div>
                                                <span class="text-xs text-gray-400">No rating</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Description -->
                                    <?php if (!empty($row['descrip6']) || !empty($row['descrip7'])): ?>
                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">
                                            <?= htmlspecialchars($row['descrip6'] ?? '') ?>
                                            <?= (!empty($row['descrip6']) && !empty($row['descrip7'])) ? ' ' : '' ?>
                                            <?= htmlspecialchars($row['descrip7'] ?? '') ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-400 italic">No description available</p>
                                    <?php endif; ?>


                                </div>

                                <!-- Action Button -->
                                <div class="mt-3 flex justify-start">
                                    <form action="product_view" method="GET">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="Btn">
                                            <div class="sign">
                                                <i class="fa-solid fa-bag-shopping"></i>
                                            </div>
                                            <div class="text">View</div>
                                        </button>
                                    </form>
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
                                        font-size: 1.1em;
                                        font-weight: 500;
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

                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>



    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10 ">
        <!-- Header first -->
            <div class="flex items-center gap-2 mb-2" data-aos="fade-up">
                <!-- Details Button (as Title) -->
                <a href="shop.php"
                    class="group relative inline-flex items-center gap-2 font-bold text-2xl sm:text-3xl lg:text-4xl text-black">
                    <span class="relative">
                        <span class="block group-hover:text-orange-600 transition-colors duration-300">
                            Furniture
                        </span>
                        <!-- Animated overlay text -->
                        <span class="absolute inset-0 w-0 overflow-hidden text-orange-600 transition-all duration-300 group-hover:w-full">
                            Furniture
                        </span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24"
                        stroke-width="2.5" stroke="currentColor"
                        class="w-7 h-7 transform transition-transform duration-300 group-hover:translate-x-1 group-hover:text-orange-600">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <!-- underline -->
                    <span class="absolute left-0 -bottom-1 h-0.5 w-0 bg-orange-600 transition-all duration-300 group-hover:w-full"></span>
                </a>
            </div>




        <style>
            .bubble-bounce {
                position: absolute;
                display: inline-block;
                opacity: 0.18;
                border-radius: 50%;
                animation: bubble-bounce 2.2s cubic-bezier(.68, -0.55, .27, 1.55) infinite;
                box-shadow: 0 8px 32px 0 rgba(251, 146, 60, 0.25);
            }

            @keyframes bubble-bounce {

                0%,
                100% {
                    transform: translateY(0) scale(1);
                }

                50% {
                    transform: translateY(-30px) scale(1.08);
                }
            }
        </style>

        <!-- Swiper Container -->
        <div class="swiper mySwiper-products w-full">
            <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
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
                        <div class="relative rounded overflow-hidden group hover:shadow-2xl hover:scale-100 transition-all duration-500 ease-out h-[450px] ">

                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>

                            <!-- Image Container with Overlay -->
                            <div class="relative h-[280px] overflow-hidden">
                                <!-- Gradient Overlay -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>

                                <?php if (!empty($row['main_image'])): ?>
                                    <img src="../../<?= $row['main_image'] ?>"
                                        loading="lazy"
                                        alt="<?= htmlspecialchars($row['product_name']) ?>"
                                        class="w-full h-full object-contain transition-all duration-700 group-hover:scale-110 group-hover:brightness-105" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                        <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content Section -->
                            <div class="p-4 flex flex-col justify-between h-[170px]">

                                <!-- Product Info -->
                                <div class="space-y-2">
                                    <!-- Title -->
                                    <h3 class="text-sm font-bold text-gray-800 leading-tight line-clamp-2 group-hover:text-orange-600 transition-colors duration-300">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h3>



                                    <!-- Rating Section -->
                                    <div class="flex items-center justify-between">
                                        <?php if ($total_raters > 0): ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-yellow-400 text-xs">
                                                    <?php
                                                    for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                                    if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                                    for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                                                    ?>
                                                </div>
                                                <span class="text-xs text-gray-500 font-medium"><?= $avg_rating ?></span>
                                            </div>
                                            <span class="text-xs text-gray-400">(<?= $total_raters ?> reviews)</span>
                                        <?php else: ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-gray-300 text-xs">
                                                    <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                </div>
                                                <span class="text-xs text-gray-400">No rating</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Description -->
                                    <?php if (!empty($row['descrip6']) || !empty($row['descrip7'])): ?>
                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">
                                            <?= htmlspecialchars($row['descrip6'] ?? '') ?>
                                            <?= (!empty($row['descrip6']) && !empty($row['descrip7'])) ? ' ' : '' ?>
                                            <?= htmlspecialchars($row['descrip7'] ?? '') ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-400 italic">No description available</p>
                                    <?php endif; ?>


                                </div>

                                <!-- Action Button -->
                                <div class="mt-3 flex justify-start">
                                    <form action="product_view" method="GET">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="Btn">
                                            <div class="sign">
                                                <i class="fa-solid fa-bag-shopping"></i>
                                            </div>
                                            <div class="text">View</div>
                                        </button>
                                    </form>
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
                                        font-size: 1.1em;
                                        font-weight: 500;
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
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>



    <section class="w-full bg-white py-20 px-6 border-t border-gray-200">
        <div class="max-w-full mx-auto">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <div class="inline-block mb-6">
                    <span class="text-sm font-semibold text-gray-500 tracking-wider uppercase mb-2 block">Our NobleHome</span>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 tracking-tight">
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
                        <h3 class="font-semibold text-xl text-gray-900 mb-2 tracking-tight">WPC Wall Panel</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Premium waterproof panels designed for contemporary interior applications</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase">
                                Premium
                            </span>
                            <button class="text-slate-700 hover:text-slate-900 font-medium flex items-center group">
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
                        <h3 class="font-semibold text-xl text-gray-900 mb-2 tracking-tight">Interior Design</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Professional styling concepts and innovative design solutions</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase">
                                Inspiration
                            </span>
                            <a href="../explore/explore_first.php" class="text-slate-700 hover:text-slate-900 font-medium flex items-center group">
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
                        <h3 class="font-semibold text-xl text-gray-900 mb-2 tracking-tight">Product Highlights</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Featured products showcased in real-world applications</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase">
                                Featured
                            </span>
                            <button class="text-slate-700 hover:text-slate-900 font-medium flex items-center group">
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
                        <h3 class="font-semibold text-xl text-gray-900 mb-2 tracking-tight">World Bex</h3>
                        <p class="text-gray-600 mb-4 leading-relaxed flex-1">Thank You for Visiting Us at WORLDBEX 2025! 🎉🏡
                            We truly appreciate your time, support, and interest in Noblehome Depot at WORLDBEX 2025! Your presence made this event even more special, and we’re excited to help bring your home and construction projects to life.</p>
                        <div class="flex items-center justify-between">
                            <span class="bg-black border border-black text-white px-3 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase">
                                Event
                            </span>
                            <button class="text-slate-700 hover:text-slate-900 font-medium flex items-center group">
                                Learn More
                                <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom CTA -->
            <div class="text-center mt-16">
                <div class="max-w-lg mx-auto mb-8">
                    <p class="text-lg text-gray-600 leading-relaxed mb-6">
                        Ready to explore our complete product portfolio?
                    </p>
                    <button class="bg-black hover:bg-slate-900 text-white px-8 py-4 rounded-lg font-semibold text-lg transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 min-w-[200px]">
                        View All Products
                    </button>
                </div>
            </div>
        </div>
    </section>


    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10 ">
        <!-- Header first -->
  
            <div class="flex items-center gap-2 mb-2" data-aos="fade-up">
                <!-- Details Button (as Title) -->
                <a href="shop.php"
                    class="group relative inline-flex items-center gap-2 font-bold text-2xl sm:text-3xl lg:text-4xl text-black">
                    <span class="relative">
                        <span class="block group-hover:text-orange-600 transition-colors duration-300">
                            Building Materials
                        </span>
                        <!-- Animated overlay text -->
                        <span class="absolute inset-0 w-0 overflow-hidden text-orange-600 transition-all duration-300 group-hover:w-full">
                            Building Materials
                        </span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24"
                        stroke-width="2.5" stroke="currentColor"
                        class="w-7 h-7 transform transition-transform duration-300 group-hover:translate-x-1 group-hover:text-orange-600">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                    <!-- underline -->
                    <span class="absolute left-0 -bottom-1 h-0.5 w-0 bg-orange-600 transition-all duration-300 group-hover:w-full"></span>
                </a>
            </div>


        <!-- Swiper Container -->
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
                        <div class="relative rounded overflow-hidden group hover:shadow-2xl hover:scale-100 transition-all duration-500 ease-out h-[450px] ">

                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>


                            <!-- Image Container with Overlay -->
                            <div class="relative h-[280px] overflow-hidden">
                                <!-- Gradient Overlay -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>

                                <?php if (!empty($row['main_image'])): ?>
                                    <img src="../../<?= $row['main_image'] ?>"
                                        loading="lazy"
                                        alt="<?= htmlspecialchars($row['product_name']) ?>"
                                        class="w-full h-full object-cover transition-all duration-700 group-hover:scale-110 group-hover:brightness-105" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                        <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content Section -->
                            <div class="p-4 flex flex-col justify-between h-[170px]">

                                <!-- Product Info -->
                                <div class="space-y-2">
                                    <!-- Title -->
                                    <h3 class="text-sm font-bold text-gray-800 leading-tight line-clamp-2 group-hover:text-orange-600 transition-colors duration-300">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h3>



                                    <!-- Rating Section -->
                                    <div class="flex items-center justify-between">
                                        <?php if ($total_raters > 0): ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-yellow-400 text-xs">
                                                    <?php
                                                    for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                                    if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                                    for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                                                    ?>
                                                </div>
                                                <span class="text-xs text-gray-500 font-medium"><?= $avg_rating ?></span>
                                            </div>
                                            <span class="text-xs text-gray-400">(<?= $total_raters ?> reviews)</span>
                                        <?php else: ?>
                                            <div class="flex items-center space-x-1">
                                                <div class="flex text-gray-300 text-xs">
                                                    <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                </div>
                                                <span class="text-xs text-gray-400">No rating</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Description -->
                                    <?php if (!empty($row['descrip6']) || !empty($row['descrip7'])): ?>
                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">
                                            <?= htmlspecialchars($row['descrip6'] ?? '') ?>
                                            <?= (!empty($row['descrip6']) && !empty($row['descrip7'])) ? ' ' : '' ?>
                                            <?= htmlspecialchars($row['descrip7'] ?? '') ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-400 italic">No description available</p>
                                    <?php endif; ?>


                                </div>

                                <!-- Action Button -->
                                <div class="mt-3 flex justify-start">
                                    <form action="product_view" method="GET">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="Btn">
                                            <div class="sign">
                                                <i class="fa-solid fa-bag-shopping"></i>
                                            </div>
                                            <div class="text">View</div>
                                        </button>
                                    </form>
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
                                        font-size: 1.1em;
                                        font-weight: 500;
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
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>


    <!-- Top Sales Section -->
    <section class="px-4 py-10">
        <!-- Header -->
        <div class="text-center mb-10 relative">
          
            <h2 class="text-4xl font-extrabold text-black mb-2 tracking-tight" data-aos="fade-up">Top Sales</h2>
            <h2 class="text-2xl font-extrabold text-black mb-2 tracking-tight" data-aos="fade-up">
                Get Up to <span class="text-red-500">30% Discount</span> on Select Items!
            </h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
  
        </div>
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
                        <div class="bg-white rounded-xl p-4 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[480px] text-center relative">
                            <!-- Triangle Badge -->
                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>

                            <!-- Product Image -->
                            <div class="aspect-square w-full rounded-lg overflow-hidden mb-4">
                                <?php if (!empty($row['type_image'])): ?>
                                    <img src="../../<?= $row['type_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['namevariant']) ?>"
                                        class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                <?php endif; ?>
                            </div>

                            <!-- Product Info -->
                            <div class="mt-auto">
                                <h3 class="text-base font-semibold underline underline-offset-4 text-orange-500 leading-snug break-words">
                                    <?= htmlspecialchars($row['namevariant']) ?>
                                </h3>

                                <!-- View Size & Color -->
                                <button type="button"
                                    onclick="openModal('<?= htmlspecialchars($row['color']) ?>', '<?= htmlspecialchars($row['size']) ?>')"
                                    class="text-sm text-blue-600  hover:text-orange-500 transition mb-2 mt-2">
                                    View Size & Color
                                </button>

                                <!-- Pricing -->
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                    <p class="text-base text-green-600 font-bold">
                                        ₱<?= number_format($finalPrice, 2) ?>
                                        <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        Origin:
                                        <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                            <?= ucfirst($row['origin']) ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-base text-green-600 font-bold mb-2">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                <?php endif; ?>

                                <!-- Buttons -->
                                <div class="flex justify-center gap-2 mt-2 flex-wrap">
                                    <!-- Buy Button -->
                                    <form action="product_view" method="GET">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit"
                                            class="bg-black text-white text-sm px-4 py-1.5 rounded-full hover:bg-red-900 transition flex items-center gap-2 shadow-sm hover:shadow-md border-2 border-white ring-2 ring-black">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                            view
                                        </button>
                                    </form>

                                    <!-- Pre-Order Button -->
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

                                        <button type="submit"
                                            class="bg-orange-500 text-white text-sm px-3 py-1.5 rounded-full hover:bg-orange-600 transition flex items-center gap-2 shadow-sm hover:shadow-md">
                                            <img src="../img/ecommerce.png" alt="Cart" class="w-4 h-4" />
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

        <!-- Shared Modal -->
        <div id="infoModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
            <div class="bg-white rounded-lg shadow-lg p-6 w-80 text-center relative">
                <button onclick="closeModal()"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">✕</button>
                <h2 class="text-lg font-semibold mb-4">Product Details</h2>
                <p class="text-gray-700"><span class="font-semibold">Color:</span> <span id="modalColor"></span></p>
                <p class="text-gray-700"><span class="font-semibold">Size:</span> <span id="modalSize"></span></p>
            </div>
        </div>

        <script>
            function openModal(color, size) {
                document.getElementById("modalColor").innerText = color;
                document.getElementById("modalSize").innerText = size;
                document.getElementById("infoModal").classList.remove("hidden");
            }

            function closeModal() {
                document.getElementById("infoModal").classList.add("hidden");
            }
            document.getElementById("infoModal").addEventListener("click", function(e) {
                if (e.target === this) closeModal();
            });
        </script>

    </section>

    <section class="p-6"> <!-- Section Title -->
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold">Featured Categories</h1>
            <p class="text-gray-600">Discover our top product categories just for you.</p>
        </div> <!-- Boxes -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"> <!-- Top Left -->
            <div class="rounded-xl overflow-hidden hover:scale-105 transition"> <img src="../img/categ/cat1.png" alt="Box 1 Image" class="w-full h-32 object-contain bg-white">
                <div class="p-3 text-center">
                    <h2 class="text-lg font-semibold mb-1">Doors</h2>
                    <p class="text-gray-600 text-sm">Stylish and durable doors to match every home design.</p>
                </div>
            </div> <!-- Top Right -->
            <div class="rounded-xl overflow-hidden hover:scale-105 transition"> <img src="../img/categ/cat2.webp" alt="Box 2 Image" class="w-full h-32 object-contain bg-white">
                <div class="p-3 text-center">
                    <h2 class="text-lg font-semibold mb-1">Aircon</h2>
                    <p class="text-gray-600 text-sm">Energy-efficient air conditioners to keep your space cool.</p>
                </div>
            </div> <!-- Bottom Left -->
            <div class="rounded-xl overflow-hidden hover:scale-105 transition"> <img src="../img/categ/cat3.png" alt="Box 3 Image" class="w-full h-32 object-contain bg-white">
                <div class="p-3 text-center">
                    <h2 class="text-lg font-semibold mb-1">Bathroom Fixtures</h2>
                    <p class="text-gray-600 text-sm">Modern fixtures for a stylish and functional bathroom.</p>
                </div>
            </div> <!-- Bottom Right -->
            <div class="rounded-xl overflow-hidden hover:scale-105 transition"> <img src="../img/categ/cat4.png" alt="Box 4 Image" class="w-full h-32 object-contain bg-white">
                <div class="p-3 text-center">
                    <h2 class="text-lg font-semibold mb-1">Tiles</h2>
                    <p class="text-gray-600 text-sm">Premium tiles in various designs and textures for any space.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10">
        <!-- Header -->
        <div class="text-center mb-8 sm:mb-12 relative">
            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-black bg-clip-text mb-4 tracking-tight " data-aos="fade-up">
                Discount Minimal <span class="text-red-700 drop-shadow-sm">up to 15%</span>
            </h2>
            <div class="mx-auto w-32 sm:w-40 h-1.5 bg-gradient-to-r from-orange-500 via-red-500 to-transparent rounded-full shadow-lg" data-aos="fade-up"></div>
            <p class="text-gray-600 mt-4 text-sm sm:text-base max-w-md mx-auto" data-aos="fade-up" data-aos-delay="200">
                Discover amazing deals on premium products with exclusive discounts
            </p>
        </div>

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
                        <div class="bg-white rounded-xl p-4 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[480px] text-center relative">
                            <!-- Triangle Badge -->
                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>

                            <!-- Product Image -->
                            <div class="aspect-square w-full rounded-lg overflow-hidden mb-4">
                                <?php if (!empty($row['type_image'])): ?>
                                    <img src="../../<?= $row['type_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['namevariant']) ?>"
                                        class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                <?php endif; ?>
                            </div>

                            <!-- Product Info -->
                            <div class="mt-auto">
                                <h3 class="text-base font-semibold underline underline-offset-4 text-orange-500 leading-snug break-words">
                                    <?= htmlspecialchars($row['namevariant']) ?>
                                </h3>

                                <!-- View Size & Color -->
                                <button type="button"
                                    onclick="openModal('<?= htmlspecialchars($row['color']) ?>', '<?= htmlspecialchars($row['size']) ?>')"
                                    class="text-sm text-blue-600  hover:text-orange-500 transition mb-2 mt-2">
                                    View Size & Color
                                </button>

                                <!-- Pricing -->
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                    <p class="text-base text-green-600 font-bold">
                                        ₱<?= number_format($finalPrice, 2) ?>
                                        <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        Origin:
                                        <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                            <?= ucfirst($row['origin']) ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-base text-green-600 font-bold mb-2">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                <?php endif; ?>

                                <!-- Buttons -->
                                <div class="flex justify-center gap-2 mt-2 flex-wrap">
                                    <!-- Buy Button -->
                                    <form action="product_view" method="GET">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit"
                                            class="bg-black text-white text-sm px-4 py-1.5 rounded-full hover:bg-red-900 transition flex items-center gap-2 shadow-sm hover:shadow-md border-2 border-white ring-2 ring-black">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                            view
                                        </button>
                                    </form>

                                    <!-- Pre-Order Button -->
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

                                        <button type="submit"
                                            class="bg-orange-500 text-white text-sm px-3 py-1.5 rounded-full hover:bg-orange-600 transition flex items-center gap-2 shadow-sm hover:shadow-md">
                                            <img src="../img/ecommerce.png" alt="Cart" class="w-4 h-4" />
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

        <!-- Shared Modal -->
        <div id="infoModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
            <div class="bg-white rounded-lg shadow-lg p-6 w-80 text-center relative">
                <button onclick="closeModal()"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">✕</button>
                <h2 class="text-lg font-semibold mb-4">Product Details</h2>
                <p class="text-gray-700"><span class="font-semibold">Color:</span> <span id="modalColor"></span></p>
                <p class="text-gray-700"><span class="font-semibold">Size:</span> <span id="modalSize"></span></p>
            </div>
        </div>

        <script>
            function openModal(color, size) {
                document.getElementById("modalColor").innerText = color;
                document.getElementById("modalSize").innerText = size;
                document.getElementById("infoModal").classList.remove("hidden");
            }

            function closeModal() {
                document.getElementById("infoModal").classList.add("hidden");
            }
            document.getElementById("infoModal").addEventListener("click", function(e) {
                if (e.target === this) closeModal();
            });
        </script>
    </section>

    <section class="p-5">
        <div class="mb-10 mt-10 text-center">
            <h2 class="text-4xl font-extrabold text-black mb-2 tracking-tight" data-aos="slide-up">New Arrival</h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full" data-aos="fade-up"></div>
        </div>

        <div class="swiper mySwiper-material">
            <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="200">
                <?php
                $has_new = false;
                while ($row = mysqli_fetch_assoc($material_resultstwo)):
                    $has_new = true;

                    // Only use variant price
                    $base_price = floatval($row['price']);
                    $discount = floatval($row['discount'] ?? 0);
                    $finalPrice = $discount > 0 ? $base_price * (1 - $discount / 100) : $base_price;
                ?>
                    <div class="swiper-slide h-full p-2">
                        <div class="bg-white rounded-xl p-4 group hover:shadow-xl transition-all duration-300 relative flex flex-col justify-between h-[470px] w-full text-center">

                            <!-- NEW Badge -->
                            <div class="absolute top-2 right-2 z-10">
                                <span class="bg-black text-white text-[10px] font-bold px-2 py-1 shadow">NEW</span>
                            </div>

                            <!-- Icon -->
                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>
                            <!-- Product Image -->
                            <div class="aspect-square w-full rounded-lg overflow-hidden mb-4">
                                <?php if (!empty($row['type_image'])): ?>
                                    <img src="../../<?= $row['type_image'] ?>" loading="lazy" alt="<?= htmlspecialchars($row['namevariant']) ?>"
                                        class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                <?php endif; ?>
                            </div>

                            <!-- Product Info -->
                            <div class="mt-auto">
                                <h3 class="text-base font-semibold underline underline-offset-4 text-orange-500 leading-snug break-words">
                                    <?= htmlspecialchars($row['namevariant']) ?>
                                </h3>

                                <!-- View Size & Color -->
                                <button type="button"
                                    onclick="openModal('<?= htmlspecialchars($row['color']) ?>', '<?= htmlspecialchars($row['size']) ?>')"
                                    class="text-sm text-blue-600  hover:text-orange-500 transition mb-2 mt-2">
                                    View Size & Color
                                </button>

                                <!-- Pricing -->
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                    <p class="text-base text-green-600 font-bold">
                                        ₱<?= number_format($finalPrice, 2) ?>
                                        <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        Origin:
                                        <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                            <?= ucfirst($row['origin']) ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-base text-green-600 font-bold mb-2">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                <?php endif; ?>

                                <!-- Buttons -->
                                <div class="flex justify-center gap-2 mt-2 flex-wrap">
                                    <!-- Buy Button -->
                                    <form action="product_view" method="GET">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit"
                                            class="bg-black text-white text-sm px-4 py-1.5 rounded-full hover:bg-red-900 transition flex items-center gap-2 shadow-sm hover:shadow-md border-2 border-white ring-2 ring-black">
                                            <i class="fa-solid fa-bag-shopping"></i>
                                            view
                                        </button>
                                    </form>

                                    <!-- Pre-Order Button -->
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

                                        <button type="submit"
                                            class="bg-orange-500 text-white text-sm px-3 py-1.5 rounded-full hover:bg-orange-600 transition flex items-center gap-2 shadow-sm hover:shadow-md">
                                            <img src="../img/ecommerce.png" alt="Cart" class="w-4 h-4" />
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

        <!-- Shared Modal -->
        <div id="infoModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
            <div class="bg-white rounded-lg shadow-lg p-6 w-80 text-center relative">
                <button onclick="closeModal()"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">✕</button>
                <h2 class="text-lg font-semibold mb-4">Product Details</h2>
                <p class="text-gray-700"><span class="font-semibold">Color:</span> <span id="modalColor"></span></p>
                <p class="text-gray-700"><span class="font-semibold">Size:</span> <span id="modalSize"></span></p>
            </div>
        </div>

        <script>
            function openModal(color, size) {
                document.getElementById("modalColor").innerText = color;
                document.getElementById("modalSize").innerText = size;
                document.getElementById("infoModal").classList.remove("hidden");
            }

            function closeModal() {
                document.getElementById("infoModal").classList.add("hidden");
            }
            document.getElementById("infoModal").addEventListener("click", function(e) {
                if (e.target === this) closeModal();
            });
        </script>
    </section>

    <!----Chat bot------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->

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
            background: linear-gradient(135deg, #6366f1, #3b82f6) !important;
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

    <section class="py-12 md:py-24 px-4">
        <div class="max-w-6xl mx-auto text-center mb-12 md:mb-20">
            <h2 class="text-3xl md:text-5xl font-extrabold mb-4 md:mb-6 bg-clip-text text-black">What Our Customers Say</h2>
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

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const reviewWrapper = document.getElementById("reviewWrapper");

            // Init Swiper with mobile-friendly settings     
            let reviewSwiper = new Swiper(".reviewCarousel", {
                loop: true,
                autoplay: {
                    delay: 4000,
                    disableOnInteraction: false
                },
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true
                },
                slidesPerView: 1,
                spaceBetween: 20,
                effect: "slide",
                speed: 700,
                // Mobile breakpoints
                breakpoints: {
                    640: {
                        spaceBetween: 30
                    }
                }
            });

            async function loadReviews() {
                try {
                    // Replace this with your actual fetch
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
                    } else {
                        reviewWrapper.innerHTML = `                     
                    <div class="swiper-slide">                         
                        <div class="testimonial-card p-6 md:p-10 mx-2 md:mx-4">
                            <div class="text-center py-8 md:py-12">
                                <p class="text-gray-600 text-base md:text-lg">No reviews yet.</p>
                            </div>
                        </div>                      
                    </div>                     
                `;
                    }

                    reviewSwiper.update();
                } catch (err) {
                    console.error("Error fetching reviews:", err);
                    // Show error state
                    reviewWrapper.innerHTML = `                     
                <div class="swiper-slide">                         
                    <div class="testimonial-card p-6 md:p-10 mx-2 md:mx-4">
                        <div class="text-center py-8 md:py-12">
                            <p class="text-red-500 text-base md:text-lg">Error loading reviews. Please try again.</p>
                        </div>
                    </div>                      
                </div>                     
            `;
                    reviewSwiper.update();
                }
            }

            // Load on page load     
            loadReviews();

            // Auto refresh every 10 seconds     
            setInterval(loadReviews, 10000);
        });
    </script>

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



    <script>
        AOS.init();
    </script>

    <!-- Include Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        // 🔁 Universal Swiper initializer
        function initSwiper(selector, options) {
            if (document.querySelector(selector)) {
                return new Swiper(selector, options);
            }
        }

        // 🛒 Product form submit handler
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

        // 🚀 DOM Ready - PROGRESSIVE SCALING (2 → 4 → 5)
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Swiper === 'undefined') {
                console.error('Swiper library is not loaded.');
                return;
            }

            // ✅ MAIN HERO SWIPER - Para sa banner/slides mo
            initSwiper('.mySwiper', {
                slidesPerView: 1,
                spaceBetween: 0,
                loop: true,
                autoplay: {
                    delay: 4000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true
                },
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

            // 🛒 PROGRESSIVE PRODUCTS DISPLAY: 2 → 4 → 5
            initSwiper('.mySwiper-products', {
                slidesPerView: 2, // ✅ Default: 2 products (small screens)
                spaceBetween: 10,
                loop: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true
                },
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev"
                },
                breakpoints: {
                    // Small screens (320px - 767px): 2 products - MALAKI
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 12
                    },
                    640: {
                        slidesPerView: 2,
                        spaceBetween: 15
                    },

                    // Medium screens (768px - 1023px): 4 products - MEDIUM
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 15
                    },

                    // Large screens (1024px+): 5 products - SMALLER BUT MORE
                    1024: {
                        slidesPerView: 5,
                        spaceBetween: 18
                    },
                    1280: {
                        slidesPerView: 5,
                        spaceBetween: 20
                    },
                    1536: {
                        slidesPerView: 7,
                        spaceBetween: 25
                    }
                }
            });

            // 💎 MATERIALS - Baka pwedeng mas marami since smaller items
            initSwiper('.mySwiper-material', {
                slidesPerView: 2,
                spaceBetween: 8,
                loop: true,
                autoplay: {
                    delay: 2500,
                    disableOnInteraction: false
                },
                breakpoints: {
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 10
                    }, // Small: 2 malaki
                    640: {
                        slidesPerView: 2,
                        spaceBetween: 12
                    },
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 12
                    }, // Medium: 4
                    1024: {
                        slidesPerView: 4,
                        spaceBetween: 15
                    }, // Large: 5
                    1280: {
                        slidesPerView: 4,
                        spaceBetween: 18
                    }, // Extra large: 6 (optional)
                    1536: {
                        slidesPerView: 8,
                        spaceBetween: 20
                    }
                }
            });

            // 🎯 ALTERNATIVE: Kung gusto mo ng 3 products sa transition
            initSwiper('.mySwiper-products-alternative', {
                slidesPerView: 2,
                spaceBetween: 10,
                loop: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false
                },
                breakpoints: {
                    // Small: 2 products - MALAKI
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 12
                    },
                    640: {
                        slidesPerView: 2,
                        spaceBetween: 15
                    },

                    // Small-Medium transition: 3 products
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 12
                    },

                    // Medium: 4 products  
                    900: {
                        slidesPerView: 4,
                        spaceBetween: 15
                    },

                    // Large: 5 products
                    1200: {
                        slidesPerView: 5,
                        spaceBetween: 18
                    },
                    1400: {
                        slidesPerView: 5,
                        spaceBetween: 20
                    }
                }
            });

            // Rest of your code...
            initAutoVerticalSwipers();

            document.querySelectorAll('.productForm').forEach(form => {
                form.addEventListener('submit', handleProductFormSubmit);
            });
        });
    </script>

</body>

</html>