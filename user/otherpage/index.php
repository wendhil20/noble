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

// 1. Fetch all variants (basic list)
$query_variants1 = "SELECT id, type_id, color, size, price, percent, image, origin FROM product_variants ORDER BY id DESC";
$result_variants = mysqli_query($conn, $query_variants1);

// 2. Furniture product list
$SYCJ_query = "SELECT * FROM products WHERE codename = 'furniture' ORDER BY id DESC";
$SYCJ_result = mysqli_query($conn, $SYCJ_query);

// 3. Discount 30% materials
$material_querys = "
    SELECT 
        pv.*, pv.origin,
        pt.type_name,
        pt.type_image,
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.description,
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    JOIN product_types pt ON pv.type_id = pt.id
    JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    WHERE pv.discount = 30
    ORDER BY pv.percent ASC, p.id, pc.id
";
$material_results = mysqli_query($conn, $material_querys);

// 4. Discount between 1-15%
$material_querysone = "
    SELECT 
        pv.*, pv.origin,
        pt.type_name,
        pt.type_image,
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.description,
        pc.id AS color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
    FROM product_variants pv
    JOIN product_types pt ON pv.type_id = pt.id
    JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    WHERE pv.discount BETWEEN 1 AND 15
    ORDER BY pv.percent ASC, p.id, pc.id
";
$material_resultsone = mysqli_query($conn, $material_querysone);

// 5. Fetch "new" status product variants with type, product, and optional color info
$material_querystwo = "
    SELECT 
        pv.*,                       -- Product Variant fields
        pv.origin,                  -- Origin (local/international)
        pt.type_name,               -- Product Type name
        pt.type_image,              -- Product Type image
        pt.product_id,              -- FK to products
        p.product_name,             -- Product name
        p.codename,                 -- Internal product code
        p.main_image,               -- Main product image
        p.description,              -- Product description
        pc.id AS color_id,          -- Color ID (nullable)
        pc.color_name AS color,     -- Color name (nullable)
        pc.color_code,              -- Color hex code (nullable)
        pc.price AS color_price     -- Optional color-specific price
    FROM product_variants pv
    INNER JOIN product_types pt ON pv.type_id = pt.id
    INNER JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    WHERE pv.status = 'new'
    ORDER BY pv.percent ASC, p.id ASC, pc.id ASC
";

$material_resultstwo = mysqli_query($conn, $material_querystwo);



// 6. Products without discount
$discount_result = mysqli_query(
    $conn,
    "SELECT 
        pv.*, pv.origin,
        pt.type_image,
        pt.type_name,
        pt.product_id,
        p.product_name,
        p.main_image,
        p.codename,
        p.description,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price,
        pc.image
     FROM product_variants pv
     JOIN product_types pt ON pv.type_id = pt.id
     JOIN products p ON pt.product_id = p.id
     LEFT JOIN product_colors pc ON p.id = pc.product_id
     WHERE pv.discount IS NULL OR pv.discount = 0
     ORDER BY pv.percent ASC"
);

// 7. Filter by furniture codename (variant join)
$filter = 'furniture';
$query = "
    SELECT 
        p.*, 
        v.descrip6, 
        v.descrip7,
        v.origin 
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

// 8. Filter by material codename (with rating)
$filter = 'material';
$query = "
    SELECT 
        p.*, 
        v.descrip6, 
        v.descrip7,
         v.origin,
        AVG(r.rating) AS avg_rating
    FROM products p
    LEFT JOIN (
        SELECT * FROM product_variants GROUP BY product_id
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

// 9. Filter by bedfurniture codename (variant join)
$filters = 'bedfurniture';
$query = "
    SELECT 
        p.*, 
        v.descrip6, 
        v.descrip7,
        v.origin 
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


// 10. Organize discount products into columns
$products = [];
while ($row = mysqli_fetch_assoc($discount_result)) {
    $products[] = $row;
}
if (!empty($products)) {
    $columns = array_chunk($products, ceil(count($products) / 3));
} else {
    $columns = [[], [], []];
}

// 11. Slider images
$sql = "SELECT filename FROM discount_images ORDER BY uploaded_at DESC";
$slideresult = $conn->query($sql);

?>



<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    Not Available For Now <span class="underline font-bold"> Discount Banner</span>
                </p>
            </div>

            <!-- Action Button -->
            <a href="shop#discounts" class="bg-white text-orange-600 hover:bg-gray-100 font-semibold px-5 py-1 rounded-lg shadow transition">
                Shop Now
            </a>
        </div>
    </section>


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
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Inquire</h3>
                                <p class="text-sm text-gray-700 mt-1">Send us a question or message.</p>
                            </button>
                        </div>

                        <!-- Slide 2 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 2"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Appointment</h3>
                                <p class="text-sm text-gray-700 mt-1">Book a consultation now.</p>
                            </button>
                        </div>

                        <!-- Slide 3 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 3"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Track Order</h3>
                                <p class="text-sm text-gray-700 mt-1">Check your order status.</p>
                            </button>
                        </div>

                        <!-- Slide 4 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 4"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border bg-white h-full min-h-[100px]">
                                <h3 class="text-lg font-semibold text-orange-700">Request Quote</h3>
                                <p class="text-sm text-gray-700 mt-1">Get pricing for your project.</p>
                            </button>
                        </div>

                        <!-- Slide 5 -->
                        <div class="swiper-slide">
                            <button @click="currentModal = 5"
                                class="w-full hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border bg-white h-full min-h-[100px]">
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
                <button @click="currentModal = 1" class="hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border">
                    <h3 class="text-lg font-semibold text-orange-700">Inquire</h3>
                    <p class="text-sm text-gray-700">Send us a question or message.</p>
                </button>

                <!-- Box 2 -->
                <button @click="currentModal = 2" class="hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border">
                    <h3 class="text-lg font-semibold text-orange-700">Appointment</h3>
                    <p class="text-sm text-gray-700">Book a consultation now.</p>
                </button>

                <!-- Box 3 -->
                <button @click="currentModal = 3" class="hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border">
                    <h3 class="text-lg font-semibold text-orange-700">Track Order</h3>
                    <p class="text-sm text-gray-700">Check your order status.</p>
                </button>

                <!-- Box 4 -->
                <button @click="currentModal = 4" class="hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border">
                    <h3 class="text-lg font-semibold text-orange-700">Request Quote</h3>
                    <p class="text-sm text-gray-700">Get pricing for your project.</p>
                </button>

                <!-- Box 5 -->
                <button @click="currentModal = 5" class="hover:bg-orange-100 transition duration-200 rounded-lg p-4 text-center border">
                    <h3 class="text-lg font-semibold text-orange-700">Support</h3>
                    <p class="text-sm text-gray-700">We're here to help you.</p>
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
        <div class="text-center mb-6">
            <h2 class="text-2xl sm:text-3xl font-bold text-orange-500 mb-1">Categories</h2>
            <p class="text-black text-sm sm:text-base">
                Browse products by category to quickly find what you need.
            </p>
        </div>

        <!-- Mobile View (lg and below) - Swiper -->
        <div class="block lg:hidden">
            <div class="swiper category-swiper" x-init="
            setTimeout(() => {
                new Swiper($el, {
                    slidesPerView: 2,
                    spaceBetween: 16,
                    centeredSlides: false,
                    breakpoints: {
                        480: {
                            slidesPerView: 3,
                            spaceBetween: 20,
                        },
                        640: {
                            slidesPerView: 4,
                            spaceBetween: 20,
                        }
                    }
                });
                lucide.createIcons();
            }, 100);
        ">
                <div class="swiper-wrapper">
                    <!-- Furniture -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=furniture"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="sofa" class="w-6 h-6 mb-1"></i>
                            Furniture
                        </a>
                    </div>

                    <!-- Materials -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=materials"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="layers" class="w-6 h-6 mb-1"></i>
                            Materials
                        </a>
                    </div>

                    <!-- Bedroom Furniture -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=bedfurniture"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="bed-double" class="w-6 h-6 mb-1"></i>
                            Bedroom Furniture
                        </a>
                    </div>

                    <!-- Lighting -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=lighting"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="lightbulb" class="w-6 h-6 mb-1"></i>
                            Lighting fixture
                        </a>
                    </div>

                    <!-- Aircon -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=aircon"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="snowflake" class="w-6 h-6 mb-1"></i>
                            Aircon
                        </a>
                    </div>

                    <!-- Doors -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=doors"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="door-closed" class="w-6 h-6 mb-1"></i>
                            Doors
                        </a>
                    </div>

                    <!-- Tiles -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=tiles"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="grid" class="w-6 h-6 mb-1"></i>
                            Tiles
                        </a>
                    </div>

                    <!-- Windows -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=windows"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="square" class="w-6 h-6 mb-1"></i>
                            Windows
                        </a>
                    </div>

                    <!-- Bathroom -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=bathroom"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="shower-head" class="w-6 h-6 mb-1"></i>
                            Bathroom Fixtures
                        </a>
                    </div>

                    <!-- Kitchen -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=kitchen"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="utensils-crossed" class="w-6 h-6 mb-1"></i>
                            Kitchen Fixtures
                        </a>
                    </div>

                    <!-- Pipes -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=pipes"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="pipe" class="w-6 h-6 mb-1"></i>
                            Pipes
                        </a>
                    </div>

                    <!-- AAC Blocks -->
                    <div class="swiper-slide">
                        <a href="shop?category[]=aacblock"
                            class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm mx-auto">
                            <i data-lucide="box" class="w-6 h-6 mb-1"></i>
                            AAC BLOCKS
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop View (lg and above) - Flex Wrap -->
        <div class="hidden lg:flex flex-wrap justify-center gap-4">
            <a href="shop?category[]=furniture"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="sofa" class="w-6 h-6 mb-1"></i>
                Furniture
            </a>

            <a href="shop?category[]=materials"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="layers" class="w-6 h-6 mb-1"></i>
                Materials
            </a>

            <a href="shop?category[]=bedfurniture"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="bed-double" class="w-6 h-6 mb-1"></i>
                Bedroom Furniture
            </a>

            <a href="shop?category[]=lighting"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="lightbulb" class="w-6 h-6 mb-1"></i>
                Lighting fixture
            </a>

            <a href="shop?category[]=aircon"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="snowflake" class="w-6 h-6 mb-1"></i>
                Aircon
            </a>

            <a href="shop?category[]=doors"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="door-closed" class="w-6 h-6 mb-1"></i>
                Doors
            </a>

            <a href="shop?category[]=tiles"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="grid" class="w-6 h-6 mb-1"></i>
                Tiles
            </a>

            <a href="shop?category[]=windows"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="square" class="w-6 h-6 mb-1"></i>
                Windows
            </a>

            <a href="shop?category[]=bathroom"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="shower-head" class="w-6 h-6 mb-1"></i>
                Bathroom Fixtures
            </a>

            <a href="shop?category[]=kitchen"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="utensils-crossed" class="w-6 h-6 mb-1"></i>
                Kitchen Fixtures
            </a>

            <a href="shop?category[]=pipes"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="pipe" class="w-6 h-6 mb-1"></i>
                Pipes
            </a>

            <a href="shop?category[]=aacblock"
                class="p-5 w-24 h-24 rounded-full flex flex-col items-center justify-center bg-orange-400 hover:bg-orange-600 text-white font-semibold shadow-lg transition text-center text-sm">
                <i data-lucide="box" class="w-6 h-6 mb-1"></i>
                AAC BLOCKS
            </a>
        </div>

        <!-- Init Lucide icons -->
        <script>
            lucide.createIcons();
        </script>
    </section>



    <section class="banner-section">
        <div class="banner-grid">

            <!-- Banner 1 -->
            <div class="banner-item contain-style">
                <img src="../img/promo/a.png" alt="Banner 1">
            </div>

            <!-- Banner 2 -->
            <div class="banner-item cover-style">
                <img src="../img/promo/2.png" alt="Banner 2">

            </div>

            <!-- Banner 3 -->
            <div class="banner-item cover-style">
                <img src="../img/promo/3.png" alt="Banner 3">

            </div>

            <!-- Banner 4 -->
            <div class="banner-item cover-style">
                <img src="../img/promo/4.png" alt="Banner 4">

            </div>

            <!-- Banner 5 -->
            <div class="banner-item contain-style special-border">
                <img src="../img/promo/1.png" alt="Banner 5">
            </div>

            <div class="banner-item cover-style">
                <img src="../img/promo/5.png" alt="Banner 2">

            </div>


        </div>
    </section>

    <section class="p-3 w-full">
        <div class="text-center mb-10 relative">
            <!-- Multiple Bouncing Bubbles Background -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                <span class="bubble-bounce" style="left: 20%; top: 30%; width: 90px; height: 90px; background: radial-gradient(circle at 40% 40%, #fbbf24 60%, #f59e42 100%); animation-delay: 0s;"></span>
                <span class="bubble-bounce" style="left: 60%; top: 50%; width: 60px; height: 60px; background: radial-gradient(circle at 60% 60%, #f97316 60%, #fbbf24 100%); animation-delay: 0.7s;"></span>
                <span class="bubble-bounce" style="left: 40%; top: 60%; width: 40px; height: 40px; background: radial-gradient(circle at 50% 50%, #f59e42 60%, #fbbf24 100%); animation-delay: 1.2s;"></span>
                <span class="bubble-bounce" style="left: 70%; top: 20%; width: 70px; height: 70px; background: radial-gradient(circle at 60% 60%, #fbbf24 60%, #f59e42 100%); animation-delay: 1.7s;"></span>
            </div>
            <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight relative z-10" data-aos="fade-up">Bed Furniture</h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full relative z-10" data-aos="fade-up"></div>
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

        <!-- Swiper -->
        <div class="swiper mySwiper-indoor">
            <div class="swiper-wrapper p-2">
                <?php while ($row = mysqli_fetch_assoc($resultss)) : ?>
                    <div class="swiper-slide flex-shrink-0" data-aos="fade-up">
                        <div class="flex flex-col justify-between h-[460px] bg-white rounded-lg shadow-lg p-4 group text-center w-full max-w-[300px] sm:max-w-[280px] md:max-w-[260px] xl:max-w-[250px] relative">
                            <!-- Ribbon Icon -->
                            <div class="absolute top-0 left-0 w-14 h-14 z-10">
                                <div class="w-16 h-16 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1.5 left-1.5 w-9 h-9 object-contain" />
                                </div>
                            </div>
                            <!-- Image -->
                            <div class="w-full aspect-square mb-3 relative">
                                <?php if (!empty($row['main_image'])): ?>
                                    <img src="../../<?= htmlspecialchars($row['main_image']) ?>" loading="lazy"
                                        class="absolute top-0 left-0 w-full h-full object-contain bg-gray-100 rounded group-hover:scale-105 transition-transform duration-300"
                                        alt="<?= htmlspecialchars($row['product_name']) ?>" />
                                <?php else: ?>
                                    <div class="absolute top-0 left-0 w-full h-full flex items-center justify-center bg-gray-200 rounded text-gray-500 text-sm">
                                        No Image
                                    </div>
                                <?php endif; ?>
                            </div>


                            <!-- Info -->
                            <div class="mt-auto text-left space-y-2">
                                <!-- Name + Ratings -->
                                <div class="flex items-center justify-between">
                                    <h2 class="text-sm font-bold text-orange-600 underline underline-offset-4 truncate max-w-[60%]">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h2>
                                    <?php
                                    $product_id = (int)$row['id'];
                                    $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                                    $rating_q->bind_param("i", $product_id);
                                    $rating_q->execute();
                                    $rating_result = $rating_q->get_result()->fetch_assoc();
                                    $avg_rating = $rating_result['avg_rating'] ?? 0;
                                    $total_raters = $rating_result['total_raters'] ?? 0;
                                    $rating_q->close();
                                    ?>
                                    <?php if ($total_raters > 0): ?>
                                        <div class="flex items-center gap-1 text-orange-400 text-xs">
                                            <?php
                                            $full = floor($avg_rating);
                                            $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                            $empty = 5 - $full - $half;
                                            for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                            if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                            for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star"></i>';
                                            ?>
                                            <span class="text-gray-600">(<?= $avg_rating ?>/5)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-gray-400 text-xs italic">No ratings</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($row['descrip6']) || !empty($row['descrip7'])): ?>
                                    <p class="text-xs text-gray-700 leading-snug h-10 overflow-hidden">
                                        <?= htmlspecialchars($row['descrip6'] ?? '') ?>
                                        <?= (!empty($row['descrip6']) && !empty($row['descrip7'])) ? '<br>' : '' ?>
                                        <?= htmlspecialchars($row['descrip7'] ?? '') ?>

                                    </p>
                                    <!-- Display Origin (Local / International) -->
                                    <p class="text-sm text-gray-600">
                                        Origin:
                                        <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                            <?= ucfirst($row['origin']) ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-xs text-gray-400 italic h-10">No description.</p>
                                <?php endif; ?>

                                <!-- View Button -->
                                <div class="mt-2">
                                    <a href="product_view?id=<?= (int)$row['id'] ?>"
                                        class="p-2 inline-block text-center w-full bg-black hover:bg-orange-600 text-white text-sm font-semibold py-1.5 rounded transition duration-200">
                                        View Product
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>


    <section class="p-3 w-full">
        <div class="text-center mb-10 relative">
            <!-- Multiple Bouncing Bubbles Background -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                <span class="bubble-bounce" style="left: 20%; top: 30%; width: 90px; height: 90px; background: radial-gradient(circle at 40% 40%, #fbbf24 60%, #f59e42 100%); animation-delay: 0s;"></span>
                <span class="bubble-bounce" style="left: 60%; top: 50%; width: 60px; height: 60px; background: radial-gradient(circle at 60% 60%, #f97316 60%, #fbbf24 100%); animation-delay: 0.7s;"></span>
                <span class="bubble-bounce" style="left: 40%; top: 60%; width: 40px; height: 40px; background: radial-gradient(circle at 50% 50%, #f59e42 60%, #fbbf24 100%); animation-delay: 1.2s;"></span>
                <span class="bubble-bounce" style="left: 70%; top: 20%; width: 70px; height: 70px; background: radial-gradient(circle at 60% 60%, #fbbf24 60%, #f59e42 100%); animation-delay: 1.7s;"></span>
            </div>
            <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight relative z-10" data-aos="fade-up">Furniture</h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full relative z-10" data-aos="fade-up"></div>
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

        <!-- Swiper -->
        <div class="swiper mySwiper-indoor">
            <div class="swiper-wrapper p-2">
                <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                    <div class="swiper-slide flex-shrink-0" data-aos="fade-up">
                        <div class="flex flex-col justify-between h-[460px] bg-white rounded-lg shadow-lg p-4 group text-center w-full max-w-[300px] sm:max-w-[280px] md:max-w-[260px] xl:max-w-[250px] relative">
                            <!-- Ribbon Icon -->
                            <div class="absolute top-0 left-0 w-14 h-14 z-10">
                                <div class="w-16 h-16 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1.5 left-1.5 w-9 h-9 object-contain" />
                                </div>
                            </div>
                            <!-- Image -->
                            <div class="w-full aspect-square mb-3">
                                <?php if (!empty($row['main_image'])): ?>
                                    <img src="../../<?= htmlspecialchars($row['main_image']) ?>" loading="lazy"
                                        class="w-full h-full object-contain bg-gray-100 rounded group-hover:scale-105 transition-transform duration-300 mx-auto"
                                        alt="<?= htmlspecialchars($row['product_name']) ?>" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 rounded text-gray-500 text-sm">
                                        No Image
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Info -->
                            <div class="mt-auto text-left space-y-2">
                                <!-- Name + Ratings -->
                                <div class="flex items-center justify-between">
                                    <h2 class="text-sm font-bold text-orange-600 underline underline-offset-4 truncate max-w-[60%]">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h2>
                                    <?php
                                    $product_id = (int)$row['id'];
                                    $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                                    $rating_q->bind_param("i", $product_id);
                                    $rating_q->execute();
                                    $rating_result = $rating_q->get_result()->fetch_assoc();
                                    $avg_rating = $rating_result['avg_rating'] ?? 0;
                                    $total_raters = $rating_result['total_raters'] ?? 0;
                                    $rating_q->close();
                                    ?>
                                    <?php if ($total_raters > 0): ?>
                                        <div class="flex items-center gap-1 text-orange-400 text-xs">
                                            <?php
                                            $full = floor($avg_rating);
                                            $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                            $empty = 5 - $full - $half;
                                            for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                            if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                            for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star"></i>';
                                            ?>
                                            <span class="text-gray-600">(<?= $avg_rating ?>/5)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-gray-400 text-xs italic">No ratings</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($row['descrip6']) || !empty($row['descrip7'])): ?>
                                    <p class="text-xs text-gray-700 leading-snug h-10 overflow-hidden">
                                        <?= htmlspecialchars($row['descrip6'] ?? '') ?>
                                        <?= (!empty($row['descrip6']) && !empty($row['descrip7'])) ? '<br>' : '' ?>
                                        <?= htmlspecialchars($row['descrip7'] ?? '') ?>

                                    </p>
                                    <!-- Display Origin (Local / International) -->
                                    <p class="text-sm text-gray-600">
                                        Origin:
                                        <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                            <?= ucfirst($row['origin']) ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-xs text-gray-400 italic h-10">No description.</p>
                                <?php endif; ?>

                                <!-- View Button -->
                                <div class="mt-2">
                                    <a href="product_view?id=<?= (int)$row['id'] ?>"
                                        class="p-2 inline-block text-center w-full bg-black hover:bg-orange-600 text-white text-sm font-semibold py-1.5 rounded transition duration-200">
                                        View Product
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>



    <section class="w-full mx-auto p-4 space-y-6">
        <!-- Section Title -->
        <div class="text-center mb-6">
            <h2 class="text-3xl font-bold text-black mb-2 relative">
                Noble Home Depot Background
                <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-16 h-0.5 bg-orange-400 rounded-full"></div>
            </h2>
            <p class="text-orange-400 text-base max-w-xl mx-auto mt-3">
                Discover our premium collection through immersive video experiences
            </p>
        </div>

        <!-- Video Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-6xl mx-auto">

            <!-- Video Item 1 -->
            <div class="bg-white rounded-xl p-4 shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="relative group mb-3">
                    <div class="overflow-hidden rounded-lg mx-auto" style="aspect-ratio: 9/16; max-height: 350px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover">
                            <source src="../../video/a.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="text-lg font-bold text-black mb-1">WPC Wall Panel</h3>
                    <p class="text-gray-600 text-sm mb-2">
                        Durable, waterproof panels perfect for modern interior designs.
                    </p>
                    <span class="bg-orange-400 text-white px-3 py-1 rounded-full text-xs font-medium">
                        Premium Quality
                    </span>
                </div>
            </div>

            <!-- Video Item 2 -->
            <div class="bg-white rounded-xl p-4 shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="relative group mb-3">
                    <div class="overflow-hidden rounded-lg mx-auto" style="aspect-ratio: 9/16; max-height: 350px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover">
                            <source src="../../video/b.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="text-lg font-bold text-black mb-1">Interior Inspirations</h3>
                    <p class="text-gray-600 text-sm mb-2">
                        Curated interior styling ideas and design concepts for your space.
                    </p>
                    <span class="bg-orange-400 text-white px-3 py-1 rounded-full text-xs font-medium">
                        Design Ideas
                    </span>
                </div>
            </div>

            <!-- Video Item 3 -->
            <div class="bg-white rounded-xl p-4 shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="relative group mb-3">
                    <div class="overflow-hidden rounded-lg" style="aspect-ratio: 16/9; max-height: 350px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover">
                            <source src="../../video/c.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="text-lg font-bold text-black mb-1">Product Highlights</h3>
                    <p class="text-gray-600 text-sm mb-2">
                        Featured items and their real-world applications in stunning detail.
                    </p>
                    <span class="bg-orange-400 text-white px-3 py-1 rounded-full text-xs font-medium">
                        Featured Products
                    </span>
                </div>
            </div>

            <!-- Video Item 4 -->
            <div class="bg-white rounded-xl p-4 shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="relative group mb-3">
                    <div class="overflow-hidden rounded-lg" style="aspect-ratio: 16/9; max-height: 350px;">
                        <video autoplay muted loop playsinline class="w-full h-full object-cover">
                            <source src="../../video/d.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="text-lg font-bold text-black mb-1">Behind the Scenes</h3>
                    <p class="text-gray-600 text-sm mb-2">
                        Our team and process behind exceptional work and quality standards.
                    </p>
                    <span class="bg-orange-400 text-white px-3 py-1 rounded-full text-xs font-medium">
                        Our Process
                    </span>
                </div>
            </div>

        </div>
    </section>


    <style>
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
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .aspect-9\/16 {
            aspect-ratio: 9/16;
        }
    </style>


    <section class="p-3 w-full">
        <div class="text-center mb-10 relative">
            <!-- Multiple Bouncing Bubbles Background -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                <span class="bubble-bounce" style="left: 20%; top: 30%; width: 90px; height: 90px; background: radial-gradient(circle at 40% 40%, #fbbf24 60%, #f59e42 100%); animation-delay: 0s;"></span>
                <span class="bubble-bounce" style="left: 60%; top: 50%; width: 60px; height: 60px; background: radial-gradient(circle at 60% 60%, #f97316 60%, #fbbf24 100%); animation-delay: 0.7s;"></span>
                <span class="bubble-bounce" style="left: 40%; top: 60%; width: 40px; height: 40px; background: radial-gradient(circle at 50% 50%, #f59e42 60%, #fbbf24 100%); animation-delay: 1.2s;"></span>
                <span class="bubble-bounce" style="left: 70%; top: 20%; width: 70px; height: 70px; background: radial-gradient(circle at 60% 60%, #fbbf24 60%, #f59e42 100%); animation-delay: 1.7s;"></span>
            </div>
            <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight relative z-10" data-aos="fade-up">Materials</h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full relative z-10" data-aos="fade-up"></div>
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

        <!-- Swiper -->
        <div class="swiper mySwiper-material">
            <div class="swiper-wrapper p-2">
                <?php while ($row = mysqli_fetch_assoc($results)) : ?>
                    <div class="swiper-slide flex-shrink-0" data-aos="fade-up">
                        <div class="flex flex-col justify-between h-[460px] bg-white rounded-lg shadow-lg p-4 group text-center w-full max-w-[300px] sm:max-w-[280px] md:max-w-[260px] xl:max-w-[250px] relative">
                            <!-- Ribbon Icon -->
                            <div class="absolute top-0 left-0 w-14 h-14 z-10">
                                <div class="w-16 h-16 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1.5 left-1.5 w-9 h-9 object-contain" />
                                </div>
                            </div>
                            <!-- Image -->
                            <div class="w-full aspect-square mb-3">
                                <?php if (!empty($row['main_image'])): ?>
                                    <img src="../../<?= htmlspecialchars($row['main_image']) ?>" loading="lazy"
                                        class="w-full h-full object-contain bg-gray-100 rounded group-hover:scale-105 transition-transform duration-300 mx-auto"
                                        alt="<?= htmlspecialchars($row['product_name']) ?>" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 rounded text-gray-500 text-sm">
                                        No Image
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Info -->
                            <div class="mt-auto text-left space-y-2">
                                <!-- Name + Ratings -->
                                <div class="flex items-center justify-between">
                                    <h2 class="text-sm font-bold text-orange-600 underline underline-offset-4 truncate max-w-[60%]">
                                        <?= htmlspecialchars($row['product_name']) ?>
                                    </h2>
                                    <?php
                                    $product_id = (int)$row['id'];
                                    $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                                    $rating_q->bind_param("i", $product_id);
                                    $rating_q->execute();
                                    $rating_result = $rating_q->get_result()->fetch_assoc();
                                    $avg_rating = $rating_result['avg_rating'] ?? 0;
                                    $total_raters = $rating_result['total_raters'] ?? 0;
                                    $rating_q->close();
                                    ?>
                                    <?php if ($total_raters > 0): ?>
                                        <div class="flex items-center gap-1 text-orange-400 text-xs">
                                            <?php
                                            $full = floor($avg_rating);
                                            $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                            $empty = 5 - $full - $half;
                                            for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                            if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                            for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star"></i>';
                                            ?>
                                            <span class="text-gray-600">(<?= $avg_rating ?>/5)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-gray-400 text-xs italic">No ratings</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($row['descrip6']) || !empty($row['descrip7'])): ?>
                                    <p class="text-xs text-gray-700 leading-snug h-10 overflow-hidden">
                                        <?= htmlspecialchars($row['descrip6'] ?? '') ?>
                                        <?= (!empty($row['descrip6']) && !empty($row['descrip7'])) ? '<br>' : '' ?>
                                        <?= htmlspecialchars($row['descrip7'] ?? '') ?>

                                    </p>
                                    <!-- Display Origin (Local / International) -->
                                    <p class="text-sm text-gray-600">
                                        Origin:
                                        <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                            <?= ucfirst($row['origin']) ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-xs text-gray-400 italic h-10">No description.</p>
                                <?php endif; ?>

                                <!-- View Button -->
                                <div class="mt-2">
                                    <a href="product_view?id=<?= (int)$row['id'] ?>"
                                        class="p-2 inline-block text-center w-full bg-black hover:bg-orange-600 text-white text-sm font-semibold py-1.5 rounded transition duration-200">
                                        View Product
                                    </a>
                                </div>
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
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                <span class="bubble-bounce" style="left: 20%; top: 30%; width: 90px; height: 90px; background: radial-gradient(circle at 40% 40%, #fbbf24 60%, #f59e42 100%); animation-delay: 0s;"></span>
                <span class="bubble-bounce" style="left: 60%; top: 50%; width: 60px; height: 60px; background: radial-gradient(circle at 60% 60%, #f97316 60%, #fbbf24 100%); animation-delay: 0.7s;"></span>
                <span class="bubble-bounce" style="left: 40%; top: 60%; width: 40px; height: 40px; background: radial-gradient(circle at 50% 50%, #f59e42 60%, #fbbf24 100%); animation-delay: 1.2s;"></span>
                <span class="bubble-bounce" style="left: 70%; top: 20%; width: 70px; height: 70px; background: radial-gradient(circle at 60% 60%, #fbbf24 60%, #f59e42 100%); animation-delay: 1.7s;"></span>
            </div>
            <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="fade-up">Top Sales</h2>
            <h2 class="text-2xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="fade-up">
                Get Up to <span class="text-red-500">30% Discount</span> on Select Items!
            </h2>
            <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
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
        <div class="swiper mySwiper-material">
            <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="300">
                <?php while ($row = mysqli_fetch_assoc($material_results)) :
                    $base = (float)$row['price'];
                    $percent = (float)($row['percent'] ?? 0);
                    $discount = (float)($row['discount'] ?? 0);
                    $priceWithMarkup = $base + ($base * $percent / 100);
                    $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
                ?>
                    <div class="swiper-slide p-2">
                        <div class="bg-white rounded-xl shadow-lg p-4 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[480px] text-center relative">
                            <!-- Triangle Badge -->
                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>

                            <!-- Product Image -->
                            <div class="aspect-square w-full bg-gray-50 border border-gray-200 rounded-lg overflow-hidden mb-4">
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
                                <ul class="text-sm text-gray-700 text-center space-y-1 mb-2 mt-2">
                                    <li><span class="font-semibold">Color:</span> <?= htmlspecialchars($row['color']) ?></li>
                                    <li><span class="font-semibold">Size:</span> <?= htmlspecialchars($row['size']) ?></li>
                                </ul>

                                <!-- Pricing -->
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                    <p class="text-base text-green-600 font-bold">
                                        ₱<?= number_format($finalPrice, 2) ?>
                                        <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                    </p>
                                    <!-- Display Origin (Local / International) -->
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
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                            </svg>
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

    <section class="px-2 sm:px-4 lg:px-6 py-8 sm:py-10 bg-gradient-to-br from-gray-50 via-white to-orange-50">
        <!-- Header -->
        <div class="text-center mb-8 sm:mb-12 relative">
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                <span class="bubble-bounce" style="left: 20%; top: 30%; width: 90px; height: 90px; background: radial-gradient(circle at 40% 40%, #fbbf24 60%, #f59e42 100%); animation-delay: 0s;"></span>
                <span class="bubble-bounce" style="left: 60%; top: 50%; width: 60px; height: 60px; background: radial-gradient(circle at 60% 60%, #f97316 60%, #fbbf24 100%); animation-delay: 0.7s;"></span>
                <span class="bubble-bounce" style="left: 40%; top: 60%; width: 40px; height: 40px; background: radial-gradient(circle at 50% 50%, #f59e42 60%, #fbbf24 100%); animation-delay: 1.2s;"></span>
                <span class="bubble-bounce" style="left: 70%; top: 20%; width: 70px; height: 70px; background: radial-gradient(circle at 60% 60%, #fbbf24 60%, #f59e42 100%); animation-delay: 1.7s;"></span>
            </div>
            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-orange-600 via-red-500 to-pink-500 mb-4 tracking-tight" data-aos="fade-up">
                Discount Minimal <span class="text-red-600 drop-shadow-sm">up to 15%</span>
            </h2>
            <div class="mx-auto w-32 sm:w-40 h-1.5 bg-gradient-to-r from-orange-500 via-red-500 to-transparent rounded-full shadow-lg" data-aos="fade-up"></div>
            <p class="text-gray-600 mt-4 text-sm sm:text-base max-w-md mx-auto" data-aos="fade-up" data-aos-delay="200">
                Discover amazing deals on premium products with exclusive discounts
            </p>
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
                <?php while ($row = mysqli_fetch_assoc($material_resultsone)) : ?>
                    <?php
                    $base = (float)$row['price'];
                    $percent = (float)($row['percent'] ?? 0);
                    $discount = (float)($row['discount'] ?? 0);
                    $priceWithMarkup = $base + ($base * $percent / 100);
                    $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
                    ?>
                    <div class="swiper-slide p-2">
                        <div class="bg-white rounded-xl shadow-lg p-4 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[480px] text-center relative">
                            <!-- Triangle Badge -->
                            <div class="absolute top-0 left-0 z-10">
                                <div class="w-12 h-12 relative">
                                    <img src="../img/icon/d.png" alt="Icon" class="absolute top-1 left-1 w-9 h-9 object-contain" />
                                </div>
                            </div>

                            <!-- Product Image -->
                            <div class="aspect-square w-full bg-gray-50 border border-gray-200 rounded-lg overflow-hidden mb-4">
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
                                <ul class="text-sm text-gray-700 text-center space-y-1 mb-2 mt-2">
                                    <li><span class="font-semibold">Color:</span> <?= htmlspecialchars($row['color']) ?></li>
                                    <li><span class="font-semibold">Size:</span> <?= htmlspecialchars($row['size']) ?></li>
                                </ul>

                                <!-- Pricing -->
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                    <p class="text-base text-green-600 font-bold">
                                        ₱<?= number_format($finalPrice, 2) ?>
                                        <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                    </p>
                                    <!-- Display Origin (Local / International) -->
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
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                            </svg>
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
    </section>

    <section class="p-5">
        <div class="mb-10 mt-10 text-center">
            <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="slide-up">New Arrival</h2>
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
                        <div class="bg-white rounded-xl shadow-lg p-4 group hover:shadow-xl transition-all duration-300 relative flex flex-col justify-between h-[470px] w-full text-center">

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

                            <!-- Image -->
                            <div class="w-full aspect-square overflow-hidden rounded-lg bg-gray-50 border border-gray-200 mb-4">
                                <?php if (!empty($row['type_image'])): ?>
                                    <img src="../../<?= htmlspecialchars($row['type_image']) ?>" loading="lazy" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105" alt="Material Variant" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                <?php endif; ?>
                            </div>


                            <!-- Info -->
                            <div class="mt-auto">
                                <h3 class="text-base font-semibold underline underline-offset-4 text-orange-500 leading-snug break-words">
                                    <?= htmlspecialchars($row['namevariant']) ?>
                                </h3>
                                <ul class="text-sm text-gray-700 text-center space-y-1 mb-2 mt-2">
                                    <li><span class="font-semibold">Color:</span> <?= htmlspecialchars($row['color']) ?></li>
                                    <li><span class="font-semibold">Size:</span> <?= htmlspecialchars($row['size']) ?></li>
                                </ul>

                                <!-- Pricing -->
                                <?php if ($discount > 0): ?>
                                    <p class="text-sm text-gray-400 line-through">₱<?= number_format($base_price, 2) ?></p>
                                    <p class="text-base text-green-600 font-bold">
                                        ₱<?= number_format($finalPrice, 2) ?>
                                        <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-base text-green-600 font-bold mb-2">₱<?= number_format($base_price, 2) ?></p>
                                <?php endif; ?>

                                <!-- Origin -->
                                <p class="text-sm text-gray-600">
                                    Origin:
                                    <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                        <?= ucfirst($row['origin']) ?>
                                    </span>
                                </p>

                                <!-- Buttons -->
                                <div class="flex justify-center gap-2 mt-2 flex-wrap">
                                    <!-- Buy -->
                                    <form action="product_view" method="GET">
                                        <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                        <button type="submit"
                                            class="bg-black text-white text-sm px-4 py-1.5 rounded-full hover:bg-red-900 transition flex items-center gap-2 shadow-sm hover:shadow-md border-2 border-white ring-2 ring-black">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                            </svg>
                                            view
                                        </button>
                                    </form>

                                    <!-- Pre-Order -->
                                    <form class="productForm" data-product-id="<?= (int)$row['product_id'] ?>">
                                        <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                                        <input type="hidden" name="selected_type" value="<?= htmlspecialchars($row['type_name']) ?>">
                                        <input type="hidden" name="selected_variant" value="<?= htmlspecialchars($row['namevariant']) ?>">
                                        <input type="hidden" name="variant_id" value="<?= (int)$row['id'] ?>">
                                        <input type="hidden" name="selected_color_id" value="<?= (int)($row['color_id'] ?? 0) ?>">
                                        <input type="hidden" name="selected_color_name" value="<?= htmlspecialchars($row['color'] ?? '') ?>">
                                        <input type="hidden" name="variant_price" value="<?= $base_price ?>">
                                        <input type="hidden" name="total_price" value="<?= $finalPrice ?>">
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

                <!-- Fallback -->
                <?php if (!$has_new): ?>
                    <div class="swiper-slide w-full text-center text-gray-500 py-10">
                        <p class="text-sm italic">No new arrivals at the moment. Please check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>


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
    <script src="../src/index-obf.js"></script>

</body>

</html>