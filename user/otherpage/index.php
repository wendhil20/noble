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

        .mySwiper .swiper-slide img {
            width: 100vw !important;
            height: 100% !important;
            object-fit: contain !important;
            object-position: center !important;
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
            background: #ffffff !important;
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

    <section class="w-full overflow-hidden relative">
        <div class="mySwiper relative w-full">
            <div class="swiper-wrapper">
                <?php while ($row = $slideresult->fetch_assoc()): ?>
                    <div class="swiper-slide h-[300px] sm:h-[400px] md:h-[500px] lg:h-[600px] relative bg-white">
                        <img src="../../uploads/<?= htmlspecialchars($row['filename']) ?>"
                            alt="Discount"
                            class="w-full h-full object-cover object-center" />
                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-black/5"></div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Rectangle Pagination Indicators -->
            <div class="swiper-pagination !bottom-4 relative z-10"></div>

            <!-- buttons same as before -->
        </div>
    </section>


    <section class="bg-black text-white p-2">

        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-6">

            <!-- Discount Text with better mobile layout -->
            <div class="flex items-center gap-2 text-center sm:text-left w-full sm:flex-1">
                <svg class="w-4 h-4 sm:w-6 sm:h-6 text-white flex-shrink-0"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>

                <div class="flex-1 min-w-0">
                    <p class="text-xs sm:text-base md:text-lg lg:text-xl leading-tight">
                        <span class="inline">Exclusive Deals!</span>
                        <span class="inline underline  ml-1">
                            Discounted Items Available
                        </span>
                    </p>
                </div>
            </div>

            <!-- Action Button with better responsive sizing -->
            <div class="w-full sm:w-auto flex-shrink-0">
                <a href="allproduct.php?discount=all"
                    class="underline text-white hover:text-red-50 active:bg-gray-100 
                      px-3 py-1.5 sm:px-6 sm:py-3 
                      text-xs sm:text-base hover:shadow-lg
                      transition-all duration-200 ease-in-out
                      w-full sm:w-auto text-center inline-block
                      transform hover:scale-105 active:scale-95
                      focus:outline-none focus:ring-2 focus:ring-orange-300">
                    Shop Now!
                </a>
            </div>
        </div>

    </section>

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


    <section class="py-8 bg-white overflow-hidden">
        <!-- Heading and description -->
        <div class="text-center mb-8 px-4">
            <h2 class="text-3xl sm:text-4xl font-light text-black mb-3">Shop by Categories</h2>
            <p class="text-gray-600 text-base sm:text-lg max-w-2xl mx-auto">
                Discover our wide range of home improvement products organized by category
            </p>
        </div>

        <!-- Categories Container -->
        <div class="relative px-4 sm:px-6 lg:px-8">
            <!-- Navigation Buttons - Desktop Only -->
            <button class="category-prev hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 bg-white rounded-full shadow-lg items-center justify-center hover:bg-orange-50 hover:scale-110 transition-all duration-300 disabled:opacity-30 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>

            <button class="category-next hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 bg-white rounded-full shadow-lg items-center justify-center hover:bg-orange-50 hover:scale-110 transition-all duration-300 disabled:opacity-30 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            <div class="swiper category-swiper !overflow-visible" x-init="
            setTimeout(() => {
                const swiper = new Swiper($el, {
                    slidesPerView: 1.5,
                    spaceBetween: 12,
                    centeredSlides: false,
                    navigation: {
                        nextEl: '.category-next',
                        prevEl: '.category-prev',
                    },
                    breakpoints: {
                        480: {
                            slidesPerView: 2,
                            spaceBetween: 16,
                        },
                        640: {
                            slidesPerView: 2.5,
                            spaceBetween: 16,
                        },
                        768: {
                            slidesPerView: 3,
                            spaceBetween: 20,
                        },
                        1024: {
                            slidesPerView: 4,
                            spaceBetween: 24,
                        },
                        1280: {
                            slidesPerView: 6,
                            spaceBetween: 24,
                        }
                    }
                });
            }, 100);
        ">
                <div class="swiper-wrapper pb-2">
                    <!-- Row 1 -->
                    <!-- Furniture -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=furniture" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/1.png" alt="Furniture" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Furniture</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Building Materials -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=buildingmaterials" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/3.png" alt="Building Materials" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl text-center px-2 leading-tight">Building<br>Materials</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Bedroom -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=bedfurniture" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/4.png" alt="Bedroom" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Bedroom</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Lighting -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=lighting" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/5.png" alt="Lighting" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl text-center leading-tight">Lighting<br>Fixture</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Aircon -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=aircon" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/6.png" alt="Aircon" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Aircon</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Doors -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=doors" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/7.png" alt="Doors" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Doors</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Row 2 -->
                    <!-- Tiles -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=tiles" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/8.png" alt="Tiles" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Tiles</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Windows -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=windows" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/9.png" alt="Windows" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Windows</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Bathroom -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=bathroom" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/10.png" alt="Bathroom" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Bathroom</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Kitchen -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=kitchen" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/11.png" alt="Kitchen" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl text-center leading-tight">Kitchen<br>Fixtures</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Pipes -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=pipes" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/2.png" alt="Pipes" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">Pipes</span>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- AAC Blocks -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=aacblock" class="group block">
                            <div class="relative h-48 sm:h-52 lg:h-56 bg-white border-2 border-gray-200 rounded-xl overflow-hidden group-hover:border-orange-400 group-hover:shadow-xl transition-all duration-300">
                                <img src="../img/category/12.png" alt="AAC Blocks" class="w-full h-full object-contain">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xl sm:text-2xl hover:underline text-white drop-shadow-2xl">AAC BLOCKS</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile Swipe Indicator -->
            <div class="flex lg:hidden justify-center mt-4 gap-1">
                <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                <div class="w-2 h-2 rounded-full bg-gray-300"></div>
            </div>
        </div>
    </section>


    <section class="py-8 bg-white overflow-hidden">
        <!-- Section Header -->
        <div class="text-center mb-8 px-4" data-aos="fade-up">
            <h2 class="text-3xl sm:text-4xl font-light text-black mb-3">Best Seller</h2>
            <p class="text-gray-600 text-base sm:text-lg">Save big on quality home improvement products</p>
        </div>

        <!-- Cards Container -->
        <div class="relative px-4 sm:px-6 lg:px-8">
            <!-- Navigation Buttons - Desktop Only -->
            <button class="promo-prev hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 bg-white rounded-full shadow-lg items-center justify-center hover:bg-orange-50 hover:scale-110 transition-all duration-300 disabled:opacity-30 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>

            <button class="promo-next hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 w-12 h-12 bg-white rounded-full shadow-lg items-center justify-center hover:bg-orange-50 hover:scale-110 transition-all duration-300 disabled:opacity-30 disabled:cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            <div class="swiper promo-swiper !overflow-visible" x-data="{}" x-init="
            setTimeout(() => {
                new Swiper($el, {
                    slidesPerView: 1.2,
                    spaceBetween: 16,
                    centeredSlides: false,
                    navigation: {
                        nextEl: '.promo-next',
                        prevEl: '.promo-prev',
                    },
                    breakpoints: {
                        640: {
                            slidesPerView: 2,
                            spaceBetween: 20,
                        },
                        1024: {
                            slidesPerView: 3,
                            spaceBetween: 24,
                        },
                        1280: {
                            slidesPerView: 4,
                            spaceBetween: 24,
                        }
                    }
                });
            }, 100);
        ">
                <div class="swiper-wrapper pb-2">
                    <?php
                    // Fetch bestsellers from database
                    $bestsellers = $conn->query("SELECT * FROM bestseller ORDER BY id DESC");
                    $count = 0;
                    while ($item = $bestsellers->fetch_assoc()):
                        $count++;
                    ?>
                        <!-- Bestseller Card -->
                        <div class="swiper-slide">
                            <a href="bestseller-detail.php?slug=<?= htmlspecialchars($item['slug']) ?>" class="group block h-full">
                                <div class="bg-white border-2 border-gray-200 rounded-xl overflow-hidden hover:border-orange-400 hover:shadow-xl transition-all duration-300 h-full">
                                    <div class="relative w-full h-80 sm:h-96 overflow-hidden">
                                        <img src="<?= htmlspecialchars($item['image'] ?: '../img/promo/default.png') ?>"
                                            alt="<?= htmlspecialchars($item['title']) ?>"
                                            class="w-full h-full object-contain transition-transform duration-500">

                                        <!-- Gradient Overlay -->
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>

                                        <!-- Content Overlay -->
                                        <div class="absolute inset-0 p-5 sm:p-6 flex flex-col justify-end text-white">
                                            <h3 class="text-xl sm:text-2xl lg:text-3xl uppercase mb-2 group-hover:text-orange-400 transition-colors">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </h3>
                                            <p class="text-white/90 text-xs sm:text-sm leading-relaxed mb-3 sm:mb-4 line-clamp-2">
                                                <?= htmlspecialchars($item['description']) ?>
                                            </p>
                                            <div class="flex items-center justify-between text-xs sm:text-sm">
                                                <span class="text-orange-400 group-hover:underline">Learn More →</span>
                                            </div>
                                        </div>

                                        <!-- Top Badge -->
                                        <div class="absolute top-3 sm:top-4 right-3 sm:right-4">
                                            <span class="bg-white text-black px-2.5 sm:px-3 py-1 rounded-full text-xs  shadow-lg">
                                                <?= $count === 1 ? 'FEATURED' : 'BESTSELLER' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Pagination Dots -->
            <div class="flex lg:hidden justify-center mt-6 gap-2">
                <?php
                $total = $conn->query("SELECT COUNT(*) as total FROM bestseller")->fetch_assoc()['total'];
                for ($i = 0; $i < min($total, 4); $i++): ?>
                    <div class="w-2 h-2 rounded-full <?= $i === 0 ? 'bg-orange-500' : 'bg-gray-300' ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

<section>
    <?php

    // Separate query to get only new products for the sidebar panel
    $newProductsQuery = "
    SELECT 
        p.id,
        p.product_name AS name,
        p.codename,
        p.quantity,
        p.price,
        p.description,
        p.main_image,
        p.category_id,
        COALESCE(c.name, 'Uncategorized') AS category_name,
        p.created_at,
        CASE 
            WHEN p.quantity > 0 THEN 'In Stock'
            WHEN p.quantity = 0 THEN 'Out of Stock'
            ELSE 'Unknown'
        END AS stock_status
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    AND p.quantity >= 0
    ORDER BY p.created_at DESC
    LIMIT 50
";

    $newProductsResult = $conn->query($newProductsQuery);
    $newProducts = [];
    if ($newProductsResult && $newProductsResult->num_rows > 0) {
        while ($row = $newProductsResult->fetch_assoc()) {
            $newProducts[] = $row;
        }
    }

    if ($results->num_rows === 0) {
        echo '<div class="col-span-full text-center py-8 text-gray-500">No products found.</div>';
    } else {
    ?>

        <!-- Only show button and sidebar if there are new products -->
        <?php if (count($newProducts) > 0): ?>
            <!-- Sidebar Toggle Button -->
            <div class="notif-wrapper">
                <button onclick="toggleSidebar()" class="sidebar-toggle-btn">
                    New Products!
                </button>
                <span class="notif-badge"><?php echo count($newProducts); ?></span>
            </div>

            <!-- Sidebar Overlay -->
            <div id="sidebarOverlay"
                class="fixed inset-0 bg-black bg-opacity-50 opacity-0 invisible transition-all duration-300"
                onclick="toggleSidebar()">
            </div>

            <!-- Gate-style Sidebar -->
            <div id="newProductsSidebar"
                class="fixed left-0 w-80 bg-white shadow-2xl transform -translate-x-full transition-transform duration-300 ease-in-out"
                style="top: 80px; height: calc(100vh - 80px); z-index: 40;">

                <!-- Sidebar Header -->
                <div class="flex justify-between items-center p-4 border-b bg-gray-900 text-white" style="background-color: #000 !important;">
                    <h2 class="text-lg ">New Products</h2>
                    <button onclick="toggleSidebar()"
                        class="text-white hover:text-orange-400 text-3xl font-bold w-10 h-10 flex items-center justify-center rounded-full hover:bg-white/20 transition-all leading-none"
                        style="line-height: 1;">
                        ×
                    </button>
                </div>

                <!-- Sidebar Content -->
                <div class="overflow-y-auto h-full pb-20">
                    <div class="p-4 space-y-3">
                        <?php foreach ($newProducts as $product): ?>
                            <div class="p-3 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start space-x-3">
                                    <?php if (!empty($product['main_image'])): ?>
                                        <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                                            class="w-12 h-12 object-contain rounded"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center text-xs text-gray-500" style="display:none;">
                                            No Image
                                        </div>
                                    <?php else: ?>
                                        <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center text-xs text-gray-500">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-medium text-sm text-gray-900 truncate">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </h3>

                                        <?php if (!empty($product['description'])): ?>
                                            <p class="text-xs text-gray-600 truncate mt-1">
                                                <?php echo htmlspecialchars(substr($product['description'], 0, 50)) . (strlen($product['description']) > 50 ? '...' : ''); ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="flex justify-between items-center mt-1">
                                            <span class="text-xs text-gray-400">
                                                <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                                            </span>
                                        </div>

                                        <!-- Action Button -->
                                        <div class="mt-3 flex justify-start">
                                            <form action="product_view" method="GET">
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                <button type="submit" class="Btn">
                                                    <div class="sign">
                                                        <i class="fa-solid fa-bag-shopping"></i>
                                                    </div>
                                                    <div class="text">View</div>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sidebar Footer -->
                <div class="absolute bottom-0 left-0 right-0 p-4 bg-white border-t">
                    <button onclick="window.location.href='allproduct'"
                        class="w-full px-3 py-2 bg-black text-white rounded hover:bg-blue-600 text-sm">
                        View All New Products
                    </button>
                </div>

            </div>

            <script>
                function toggleSidebar() {
                    const sidebar = document.getElementById("newProductsSidebar");
                    const overlay = document.getElementById("sidebarOverlay");
                    const notifWrapper = document.querySelector(".notif-wrapper");
                    const isOpen = !sidebar.classList.contains("-translate-x-full");

                    if (isOpen) {
                        // Close sidebar (slide out to left)
                        sidebar.classList.add("-translate-x-full");
                        overlay.classList.add("opacity-0", "invisible");
                        overlay.classList.remove("opacity-100", "visible");
                        document.body.style.overflow = "auto";

                        // Show the button again
                        if (notifWrapper) {
                            notifWrapper.style.display = "inline-block";
                        }
                    } else {
                        // Open sidebar (slide in from left)
                        sidebar.classList.remove("-translate-x-full");
                        overlay.classList.remove("opacity-0", "invisible");
                        overlay.classList.add("opacity-100", "visible");
                        document.body.style.overflow = "hidden";

                        // Hide the button when sidebar is open
                        if (notifWrapper) {
                            notifWrapper.style.display = "none";
                        }
                    }
                }

                function viewAllNewProducts() {
                    // Close sidebar first
                    toggleSidebar();
                    // Redirect to a page that shows all new products
                    setTimeout(() => {
                        window.location.href = "?filter=new_products";
                    }, 300);
                }

                // Close sidebar when pressing Escape key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        const sidebar = document.getElementById("newProductsSidebar");
                        if (!sidebar.classList.contains("-translate-x-full")) {
                            toggleSidebar();
                        }
                    }
                });

                // Listen for mobile menu opening and close New Products sidebar
                document.addEventListener('DOMContentLoaded', function() {
                    // Check for Alpine.js mobile menu state changes
                    const checkMobileMenu = setInterval(function() {
                        const mobileMenuOverlay = document.querySelector('.lg\\:hidden.fixed.inset-0');
                        
                        if (mobileMenuOverlay) {
                            const observer = new MutationObserver(function(mutations) {
                                mutations.forEach(function(mutation) {
                                    if (mutation.type === 'attributes') {
                                        // Check if mobile menu is visible
                                        const mobileMenuVisible = mobileMenuOverlay.getAttribute('x-show') || 
                                                                 window.getComputedStyle(mobileMenuOverlay).display !== 'none' ||
                                                                 !mobileMenuOverlay.classList.contains('hidden');
                                        
                                        const sidebar = document.getElementById("newProductsSidebar");
                                        const overlay = document.getElementById("sidebarOverlay");
                                        const notifWrapper = document.querySelector(".notif-wrapper");
                                        
                                        // Simple check: if mobile overlay exists and is not hidden
                                        const isVisible = mobileMenuOverlay.style.display !== 'none' && 
                                                         !mobileMenuOverlay.hasAttribute('hidden');
                                        
                                        if (isVisible || mobileMenuOverlay.offsetParent !== null) {
                                            // Mobile menu is opening, close New Products sidebar
                                            if (sidebar && !sidebar.classList.contains("-translate-x-full")) {
                                                sidebar.classList.add("-translate-x-full");
                                                overlay.classList.add("opacity-0", "invisible");
                                                overlay.classList.remove("opacity-100", "visible");
                                                document.body.style.overflow = "auto";
                                                
                                                if (notifWrapper) {
                                                    notifWrapper.style.display = "inline-block";
                                                }
                                            }
                                            
                                            // Hide the New Products button when mobile menu is open
                                            if (notifWrapper) {
                                                notifWrapper.style.opacity = "0";
                                                notifWrapper.style.pointerEvents = "none";
                                                notifWrapper.style.visibility = "hidden";
                                            }
                                        } else {
                                            // Mobile menu is closing, show New Products button again
                                            if (notifWrapper) {
                                                notifWrapper.style.opacity = "1";
                                                notifWrapper.style.pointerEvents = "auto";
                                                notifWrapper.style.visibility = "visible";
                                            }
                                        }
                                    }
                                });
                            });
                            
                            observer.observe(mobileMenuOverlay, { 
                                attributes: true,
                                attributeFilter: ['style', 'class', 'x-show', 'hidden'],
                                childList: false,
                                subtree: false
                            });
                            
                            // Also observe the actual sidebar content div
                            const mobileSidebarContent = document.querySelector('.lg\\:hidden.fixed.inset-0 > div');
                            if (mobileSidebarContent) {
                                observer.observe(mobileSidebarContent, { 
                                    attributes: true,
                                    attributeFilter: ['style', 'class']
                                });
                            }
                            
                            clearInterval(checkMobileMenu);
                        }
                    }, 100);
                    
                    // Stop checking after 5 seconds
                    setTimeout(() => clearInterval(checkMobileMenu), 5000);
                });

                // Auto-refresh new products count every 30 seconds
                setInterval(function() {
                    fetch(window.location.pathname + '?action=get_new_products_count')
                        .then(response => response.json())
                        .then(data => {
                            const notifWrapper = document.querySelector('.notif-wrapper');
                            const badge = document.querySelector('.notif-badge');

                            if (data.count !== undefined) {
                                if (data.count > 0) {
                                    // Show button if there are new products
                                    if (notifWrapper) {
                                        notifWrapper.style.display = 'inline-block';
                                    }
                                    if (badge) {
                                        badge.textContent = data.count;
                                    }
                                } else {
                                    // Hide button if no new products
                                    if (notifWrapper) {
                                        notifWrapper.style.display = 'none';
                                    }
                                }
                            }
                        })
                        .catch(error => console.log('Auto-refresh error:', error));
                }, 30000);
            </script>

            <style>
                /* Button wrapper - fixed positioning */
                .notif-wrapper {
                    position: fixed !important;
                    top: 50% !important;
                    left: 0 !important;
                    transform: translateY(-50%) !important;
                    z-index: 40 !important;
                    display: inline-block;
                    opacity: 1;
                    pointer-events: auto;
                    visibility: visible;
                    transition: opacity 0.3s ease, visibility 0.3s ease;
                }

                /* Main sidebar toggle button */
                .sidebar-toggle-btn {
                    width: 130px;
                    height: 60px;
                    border-radius: 0 30px 30px 0;
                    background: #000000ff;
                    color: white;
                    border: none;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    text-align: center;
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                    cursor: pointer;
                    transition: background-color 0.2s ease;
                }

                .sidebar-toggle-btn:hover {
                    background: #333333;
                }

                /* Notification badge */
                .notif-badge {
                    position: absolute;
                    top: -8px;
                    right: 3px;
                    background: #dc2626;
                    color: white;
                    border-radius: 50%;
                    padding: 4px 8px;
                    font-size: 12px;
                    line-height: 1;
                    min-width: 20px;
                    text-align: center;
                    z-index: 1;
                }

                /* Sidebar styling */
                #newProductsSidebar {
                    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
                    z-index: 40;
                    top: 80px !important;
                    height: calc(100vh - 80px) !important;
                }

                #sidebarOverlay {
                    z-index: 39;
                }

                /* Custom scrollbar for the sidebar */
                #newProductsSidebar .overflow-y-auto::-webkit-scrollbar {
                    width: 4px;
                }

                #newProductsSidebar .overflow-y-auto::-webkit-scrollbar-track {
                    background: #ffffffff;
                    border-radius: 2px;
                }

                #newProductsSidebar .overflow-y-auto::-webkit-scrollbar-thumb {
                    background: #c1c1c1;
                    border-radius: 2px;
                }

                #newProductsSidebar .overflow-y-auto::-webkit-scrollbar-thumb:hover {
                    background: #a1a1a1;
                }

                /* Hover effect for product cards */
                #newProductsSidebar .hover\:bg-gray-50:hover {
                    background-color: #f9fafb;
                    cursor: pointer;
                }

                /* Mobile responsive - Hide New Products when mobile menu is active */
                @media (max-width: 1024px) {
                    .notif-wrapper {
                        z-index: 40 !important;
                    }
                    
                    /* Ensure it's hidden behind mobile menu */
                    body.mobile-menu-open .notif-wrapper,
                    .mobile-menu-active .notif-wrapper {
                        opacity: 0 !important;
                        pointer-events: none !important;
                        visibility: hidden !important;
                    }
                    
                    body.mobile-menu-open #newProductsSidebar,
                    .mobile-menu-active #newProductsSidebar {
                        transform: translateX(-100%) !important;
                    }
                    
                    body.mobile-menu-open #sidebarOverlay,
                    .mobile-menu-active #sidebarOverlay {
                        opacity: 0 !important;
                        visibility: hidden !important;
                    }

                    #newProductsSidebar {
                        width: 100%;
                        max-width: 85vw;
                    }

                    .sidebar-toggle-btn {
                        width: 90px;
                        height: 50px;
                      
                    }

                    .notif-badge {
                        top: -5px;
                        right: 0px;
                        padding: 3px 6px;
                    
                    }
                }

                /* View Button Styling */
                .Btn {
                    width: 120px;
                    height: 35px;
                    display: flex;
                    align-items: center;
                    justify-content: flex-start;
                    gap: 10px;
                    background: linear-gradient(105deg, #000000ff, #000000ff);
                    border-radius: 30px;
                    color: #fff;
                    font-weight: 600;
                    border: none;
                    position: relative;
                    cursor: pointer;
                    transition-duration: .2s;
                    background-size: 200%;
                    background-position: 0%;
                    font-size: 12px;
                }

                .Btn:hover {
                    background-position: 100%;
                    transform: scale(1.05);
                }

                .Btn:active {
                    transform: scale(0.95);
                }

                .sign {
                    width: 25px;
                    height: 25px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #ffffff;
                    font-size: 10px;
                    margin-left: 2px;
                }

                .text {
                    font-size: 11px;
                    font-weight: 500;
                }
            </style>
        <?php endif; ?>

    <?php
    } // End of main if statement
    ?>
</section>

    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10 ">
        <!-- Header first -->

        <div class="flex items-center gap-2 mb-2 mt-4" data-aos="fade-up">
            <!-- Details Button (as Title) -->
            <a href="shop.php"
                class="group relative inline-flex items-center gap-2 font-light text-2xl sm:text-3xl lg:text-4xl text-black">
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
                                    <div class="relative w-full max-w-xs">
                                        <h3 class="text-sm font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 truncate pr-6">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>
                                        <!-- Fade overlay -->
                                        <div class="absolute top-0 right-0 h-full w-6 bg-gradient-to-l from-white to-transparent"></div>
                                    </div>


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
                class="group relative inline-flex items-center gap-2 text-2xl sm:text-3xl lg:text-4xl text-black">
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
                                    <div class="relative w-full max-w-xs">
                                        <h3 class="text-sm font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 truncate pr-6">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>
                                        <!-- Fade overlay -->
                                        <div class="absolute top-0 right-0 h-full w-6 bg-gradient-to-l from-white to-transparent"></div>
                                    </div>
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


    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10 ">
        <!-- Header first -->

        <div class="flex items-center gap-2 mb-2" data-aos="fade-up">
            <!-- Details Button (as Title) -->
            <a href="shop.php"
                class="group relative inline-flex items-center gap-2 text-2xl sm:text-3xl lg:text-4xl text-black">
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
                                    <div class="relative w-full max-w-xs">
                                        <h3 class="text-sm font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 truncate pr-6">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>
                                        <!-- Fade overlay -->
                                        <div class="absolute top-0 right-0 h-full w-6 bg-gradient-to-l from-white to-transparent"></div>
                                    </div>
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

            <h2 class="text-4xl text-black mb-2 tracking-tight" data-aos="fade-up">Top Sales</h2>
            <h2 class="text-2xl text-black mb-2 tracking-tight" data-aos="fade-up">
                Get Up to <span class="text-red-500">10% Discount</span> on Select Items!
            </h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>

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
                        <div class="bg-white p-4 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[480px] text-center relative">
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
                                <div class="relative w-full max-w-xs">
                                    <h3 class="text-sm font-light text-gray-800 leading-tight group-hover:text-orange-600 transition-colors duration-300 truncate pr-6">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h3>
                                    <!-- Fade overlay -->
                                    <div class="absolute top-0 right-0 h-full w-6 bg-gradient-to-l from-white to-transparent"></div>
                                </div>
                                <!-- View Size & Color -->
                                <button type="button"
                                    onclick="openModal('<?= htmlspecialchars($row['color']) ?>', '<?= htmlspecialchars($row['size']) ?>')"
                                    class="text-sm text-black  hover:text-orange-500 transition mb-2 mt-2">
                                    View Size & Color
                                </button>

                                <!-- Pricing -->
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                    <p class="text-base text-black font-bold">
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

                                <!-- Replace your current View Details Button section with this -->
                                <div class="flex flex-col gap-2 mt-auto">
                                    <!-- Animated View Details Button -->
                                    <form action="product_view" method="GET" class="w-full flex justify-start mt-4">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit" class="animated-view-btn">
                                            <div class="btn-sign">
                                                <i class="fa-solid fa-bag-shopping"></i>
                                            </div>
                                            <div class="btn-text">View Details</div>
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
                    <h2 class="text-2xl  text-white">Doors</h2>
                </div>
            </div>

            <!-- Aircon Category -->
            <div class="relative rounded-xl overflow-hidden hover:scale-105 transition cursor-pointer category-box w-64 h-48 group"
                onclick="loadCategoryProducts('aircon')">
                <img src="../img/categ/cat2.webp" alt="Aircon" class="w-full h-full object-contain">
                <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                    <h2 class="text-2xl  text-white">Aircon</h2>
                </div>
            </div>

            <!-- Bathroom Fixtures Category -->
            <div class="relative rounded-xl overflow-hidden hover:scale-105 transition cursor-pointer category-box w-64 h-48 group"
                onclick="loadCategoryProducts('bathroomfixtures')">
                <img src="../img/categ/cat3.png" alt="Bathroom Fixtures" class="w-full h-full object-contain">
                <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                    <h2 class="text-2xl  text-white">Bathroom Fixtures</h2>
                </div>
            </div>

            <!-- Tiles Category -->
            <div class="relative rounded-xl overflow-hidden hover:scale-105 transition cursor-pointer category-box w-64 h-48 group"
                onclick="loadCategoryProducts('tiles')">
                <img src="../img/categ/cat4.png" alt="Tiles" class="w-full h-full object-contain">
                <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                    <h2 class="text-2xl  text-white">Tiles</h2>
                </div>
            </div>
        </div>
    </section>

    <!-- Sidebar Overlay (Hidden by default) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" onclick="closeSidebar()"></div>

    <!-- Sidebar for Products -->
    <div id="productSidebar" class="fixed top-0 right-0 h-full w-full md:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 overflow-y-auto">
        <!-- Sidebar Header -->
        <div class="sticky top-0 bg-black border-b p-4 flex justify-between items-center z-1 0">
            <div class="text-white">
                <h2 class="text-xl  capitalize" id="sidebarTitle">Products</h2>
                <p class="text-sm " id="sidebarSubtitle">Loading...</p>
            </div>
            <button onclick="closeSidebar()" class="text-white hover:text-gray-900">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Sidebar Content -->
        <div id="sidebarContent" class="p-4">
            <div class="flex justify-center items-center h-40">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
            </div>
        </div>
    </div>

    <script>
        function loadCategoryProducts(category) {
            // Open sidebar
            document.getElementById('sidebarOverlay').classList.remove('hidden');
            document.getElementById('productSidebar').classList.remove('translate-x-full');

            // Update title
            document.getElementById('sidebarTitle').textContent = category.charAt(0).toUpperCase() + category.slice(1);
            document.getElementById('sidebarSubtitle').textContent = 'Loading products...';

            // Show loading spinner
            document.getElementById('sidebarContent').innerHTML = `
        <div class="flex justify-center items-center h-40">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
        </div>
    `;

            // Fetch products via AJAX
            fetch('index_fetch_category_products.php?category=' + category)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('sidebarContent').innerHTML = data;
                    document.getElementById('sidebarSubtitle').textContent = 'Browse our collection';
                })
                .catch(error => {
                    document.getElementById('sidebarContent').innerHTML = `
                <div class="text-center text-red-500 p-4">
                    <p>Error loading products. Please try again.</p>
                </div>
            `;
                });
        }

        function closeSidebar() {
            document.getElementById('sidebarOverlay').classList.add('hidden');
            document.getElementById('productSidebar').classList.add('translate-x-full');
        }

        // Close sidebar with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });
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

    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10">
        <!-- Header -->
        <div class="text-center mb-8 sm:mb-12 relative">
            <h2 class="text-2xl sm:text-3xl lg:text-4xl text-black bg-clip-text mb-4 tracking-tight " data-aos="fade-up">
                Discount Minimal <span class="text-red-700 drop-shadow-sm">up to 5%</span>
            </h2>
            <div class="mx-auto w-32 sm:w-40 h-1.5 bg-gradient-to-r from-orange-500 via-red-500 to-transparent rounded-full shadow-lg" data-aos="fade-up"></div>
            <p class="text-gray-600 mt-4 text-sm sm:text-base max-w-md mx-auto" data-aos="fade-up" data-aos-delay="200">
                Discover amazing deals on premium products with exclusive discounts
            </p>
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
                        <div class=" p-4 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[480px] text-center relative">
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
                                <div class="flex flex-col gap-2 mt-auto">
                                    <!-- Animated View Details Button -->
                                    <form action="product_view" method="GET" class="w-full flex justify-start mt-4">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit" class="animated-view-btn">
                                            <div class="btn-sign">
                                                <i class="fa-solid fa-bag-shopping"></i>
                                            </div>
                                            <div class="btn-text">View Details</div>
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
            <h2 class="text-4xl text-black mb-2 tracking-tight" data-aos="slide-up">New Arrival</h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full" data-aos="fade-up"></div>
        </div>
 
    <?php 
    // Check if there are any products
    $row_count = mysqli_num_rows($material_resultstwo);
    ?>

    <?php if ($row_count > 0): ?>
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
                        <div class=" p-4 group hover:shadow-xl transition-all duration-300 relative flex flex-col justify-between h-[470px] w-full text-center">

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

                                <div class="flex flex-col gap-2 mt-auto">
                                    <!-- Animated View Details Button -->
                                    <form action="product_view" method="GET" class="w-full flex justify-start mt-4">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit" class="animated-view-btn">
                                            <div class="btn-sign">
                                                <i class="fa-solid fa-bag-shopping"></i>
                                            </div>
                                            <div class="btn-text">View Details</div>
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
            background: linear-gradient(135deg, #ffffffff, #ffffffff) !important;
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
    </script>


    <?php include '../navbar/footer.php'; ?>


    <script>
        AOS.init();
    </script>

    <!-- Include Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>

        
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

// DOM Ready
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
        loop: heroSlideCount > 1, // Only loop if more than 1 slide
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

    // 🛒 PRODUCTS SWIPER - Dynamic loop based on slide count
    const productSlideCount = document.querySelector('.mySwiper-products')?.querySelectorAll('.swiper-slide').length || 0;
    initSwiper('.mySwiper-products', {
        slidesPerView: 2,
        spaceBetween: 10,
        loop: productSlideCount >= 4, // Need at least 4 slides for loop with 2 per view
        autoplay: productSlideCount > 2 ? {
            delay: 3000,
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
                loop: productSlideCount >= 6 // 3 per view needs 6+ slides
            },
            1024: {
                slidesPerView: 5,
                spaceBetween: 18,
                loop: productSlideCount >= 10 // 5 per view needs 10+ slides
            },
            1280: {
                slidesPerView: 5,
                spaceBetween: 20,
                loop: productSlideCount >= 10
            },
            1536: {
                slidesPerView: 7,
                spaceBetween: 25,
                loop: productSlideCount >= 14 // 7 per view needs 14+ slides
            }
        }
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