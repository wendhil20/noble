<?php
ob_start();
session_start();
include '../connection/connect.php'; // Adjust path if needed

// Check if there's a login notification
if (isset($_SESSION['login_needed'])) {
    $notification_message = $_SESSION['login_needed'];
    unset($_SESSION['login_needed']); // Clear the message after showing it
}

$query_variants1 = "SELECT id, type_id, color, size, price, percent, image FROM product_variants ORDER BY id DESC";
$result_variants = mysqli_query($conn, $query_variants1);
// Indoor Variants: Fetch unique products by codename
$SYCJ_query = "
    SELECT * FROM products
    WHERE codename = 'furniture'
    ORDER BY id DESC
";
$SYCJ_result = mysqli_query($conn, $SYCJ_query);

$material_querys = "
       SELECT 
        pv.*,
        pt.type_name,
        pt.type_image,  -- Add this
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.description,
        pc.id as color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price as color_price
    FROM 
        product_variants pv 
    JOIN 
        product_types pt ON pv.type_id = pt.id 
    JOIN 
        products p ON pt.product_id = p.id
    LEFT JOIN 
        product_colors pc ON p.id = pc.product_id
    WHERE 
        pv.discount = 40
    ORDER BY 
        pv.percent ASC, p.id, pc.id
";

$material_results = mysqli_query($conn, $material_querys);

$material_querysone = "
    SELECT 
        pv.*,
        pt.type_name,
        pt.type_image,  -- Add this
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.description,
        pc.id as color_id,
        pc.color_name AS color,
        pc.color_code,
        pc.price as color_price
    FROM 
        product_variants pv 
    JOIN 
        product_types pt ON pv.type_id = pt.id 
    JOIN 
        products p ON pt.product_id = p.id
    LEFT JOIN 
        product_colors pc ON p.id = pc.product_id
    WHERE 
        pv.discount BETWEEN 1 AND 30 
    ORDER BY 
        pv.percent ASC, p.id, pc.id
";

$material_resultsone = mysqli_query($conn, $material_querysone);

$material_querystwo = "
    SELECT 
        pv.*,
        pt.type_name,
        pt.type_image,        -- ✅ Added this line
        pt.product_id,
        p.product_name,
        p.codename,
        p.main_image,
        p.description,
        pc.id as color_id,
        pc.color_name,
        pc.color_code,
        pc.price as color_price
    FROM 
        product_variants pv 
    JOIN 
        product_types pt ON pv.type_id = pt.id 
    JOIN 
        products p ON pt.product_id = p.id
    LEFT JOIN 
        product_colors pc ON p.id = pc.product_id
    WHERE 
        pv.status = 'new' 
    ORDER BY 
        pv.percent ASC, p.id, pc.id
";

$material_resultstwo = mysqli_query($conn, $material_querystwo);


$discount_result = mysqli_query(
    $conn,
    "SELECT 
        pv.*, 
        pt.type_image,
        pt.type_name,
        pt.product_id,
        p.product_name,
        p.main_image,
        p.codename,
        p.description,
        pc.color_name AS color,
        pc.color_code,
        pc.price AS color_price
     FROM product_variants pv
     JOIN product_types pt ON pv.type_id = pt.id
     JOIN products p ON pt.product_id = p.id
     LEFT JOIN product_colors pc ON p.id = pc.product_id
     WHERE pv.discount IS NULL OR pv.discount = 0
     ORDER BY pv.percent ASC"
);


$filter = 'furniture'; // forced filter
$query = "
SELECT * FROM products 
WHERE codename = '$filter' 
ORDER BY id DESC
";
$result = mysqli_query($conn, $query);


// handle filtering
$filters = 'material'; // force it
$query = "SELECT * FROM products WHERE codename = '$filters' ORDER BY id DESC";
$results = mysqli_query($conn, $query);

// handle filtering
$filters = 'furnituretwo'; // force it
$query = "SELECT * FROM products WHERE codename = '$filters' ORDER BY id DESC";
$resultss = mysqli_query($conn, $query);


$products = [];
while ($row = mysqli_fetch_assoc($discount_result)) {
    $products[] = $row;
}

// Only create columns if we have products
if (!empty($products)) {
    $columns = array_chunk($products, ceil(count($products) / 3));
} else {
    $columns = [[], [], []]; // Empty columns as fallback
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noble Home - Modern Furnishing Supplies</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com?plugins=aspect-ratio"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link href="https://unpkg.com/aos@next/dist/aos.css" rel="stylesheet" />
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Function to hide the notification after 5 seconds
        setTimeout(function() {
            const notification = document.getElementById('loginNotification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 5000); // 5000ms = 5 seconds
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .hero-bg {
            background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.4) 100%),
                url('img/bodyimg/a.png');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .floating-elements::before,
        .floating-elements::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, rgba(251, 146, 60, 0.1), rgba(251, 146, 60, 0.05));
            animation: float 6s ease-in-out infinite;
        }

        .floating-elements::before {
            width: 300px;
            height: 300px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-elements::after {
            width: 200px;
            height: 200px;
            bottom: 10%;
            right: 10%;
            animation-delay: 3s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        .gradient-text {
            background: linear-gradient(135deg, #ffffff 0%, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-glow {
            box-shadow: 0 0 30px rgba(251, 146, 60, 0.3);
            transition: all 0.3s ease;
        }

        .btn-glow:hover {
            box-shadow: 0 0 40px rgba(251, 146, 60, 0.5);
            transform: translateY(-2px);
        }

        .text-shadow {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .backdrop-blur-sm {
            backdrop-filter: blur(4px);
        }

        [x-cloak] {
            display: none !important;
        }

        /* No @apply: use classes directly in HTML */
        .filter-tab {
            /* Placeholder for custom styles if needed */
        }

        .active-tab {
            /* Placeholder for custom styles if needed */
        }

        .swiper-slide {
            opacity: 1 !important;
            transition: opacity 0.5s ease-in-out;
        }

        .swiper-slide-active {
            opacity: 1 !important;
        }

        .swiper-slide:not(.swiper-slide-active) {
            opacity: 0.3;
        }

        /* Ensure proper height for vertical swiper */
        .swiper {
            height: 100%;
        }

        .swiper-wrapper {
            height: 100%;
        }
    </style>
</head>

<body class="">

    <?php include 'navbar/top.php'; ?>


    <?php if (isset($_SESSION['toast'])): ?>
        <div id="toast" class="fixed top-5 right-5 bg-<?= $_SESSION['toast']['type'] === 'error' ? 'red' : 'green' ?>-500 text-white text-sm px-4 py-2 rounded shadow-lg z-50">
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
        <div id="loginNotification" class="p-4 mb-4 bg-yellow-200 text-yellow-800 border-l-4 border-yellow-500">
            <p><?= htmlspecialchars($notification_message) ?></p>
        </div>
    <?php endif; ?>


    <section class="w-full overflow-hidden bg-gray-100">
        <div
            x-data="{
            images: [
                'img/promo/3.png',
                'img/promo/4.webp',
                'img/promo/5.png'             
            ],
            current: 0,
            next() {
                this.current = (this.current + 1) % this.images.length;
            },
            prev() {
                this.current = (this.current - 1 + this.images.length) % this.images.length;
            }
        }"
            x-init="setInterval(() => next(), 3000)"
            class="relative w-full">

            <!-- Image Container with Slide -->
            <div class="w-full h-[200px] md:h-[500px] overflow-hidden relative">
                <div
                    class="flex transition-transform duration-700 ease-in-out"
                    :style="'transform: translateX(-' + (current * 100) + '%)'">
                    <template x-for="(img, index) in images" :key="index">
                        <img :src="img" class="w-full flex-shrink-0 object-contain h-[300px] md:h-[500px]">
                    </template>
                </div>
            </div>

            <!-- Prev Button -->
            <button @click="prev"
                class="absolute top-1/2 left-0 transform -translate-y-1/2 bg-white/80 p-2 rounded-r-md hover:bg-white z-10">
                <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Next Button -->
            <button @click="next"
                class="absolute top-1/2 right-0 transform -translate-y-1/2 bg-white/80 p-2 rounded-l-md hover:bg-white z-10">
                <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </section>

    <section class="bg-gradient-to-r from-orange-500 to-red-500 text-white py-1 px-2 shadow-md">
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
            <a href="shop.php#discounts" class="bg-white text-orange-600 hover:bg-gray-100 font-semibold px-5 py-1 rounded-lg shadow transition">
                Shop Now
            </a>
        </div>
    </section>


   <section class="bg-white shadow-md py-4 px-6" x-data="{ activeModal: null }">
  <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-7">

    <!-- Box 1 -->
    <button @click="activeModal = 1" class="flex-1 hover:bg-orange-200 transition rounded-lg p-4 text-center">
      <h3 class="text-lg font-semibold text-orange-700">Inquire</h3>
      <p class="text-sm text-gray-700">Send us a question or message.</p>
    </button>
                <div class="hidden md:block w-px bg-gray-300 h-10 mx-4"></div>

    <!-- Box 2 -->
    <button @click="activeModal = 2" class="flex-1 hover:bg-orange-200 transition rounded-lg p-4 text-center">
      <h3 class="text-lg font-semibold text-orange-700">Appointment</h3>
      <p class="text-sm text-gray-700">Book a consultation now.</p>
    </button>
            <div class="hidden md:block w-px bg-gray-300 h-10 mx-4"></div>
    <!-- Box 3 -->
    <button @click="activeModal = 3" class="flex-1 hover:bg-orange-200 transition rounded-lg p-4 text-center">
      <h3 class="text-lg font-semibold text-orange-700">Track Order</h3>
      <p class="text-sm text-gray-700">Check your order status.</p>
    </button>
            <div class="hidden md:block w-px bg-gray-300 h-10 mx-4"></div>
    <!-- Box 4 -->
    <button @click="activeModal = 4" class="flex-1 hover:bg-orange-200 transition rounded-lg p-4 text-center">
      <h3 class="text-lg font-semibold text-orange-700">Request Quote</h3>
      <p class="text-sm text-gray-700">Get pricing for your project.</p>
    </button>
            <div class="hidden md:block w-px bg-gray-300 h-10 mx-4"></div>
    <!-- Box 5 -->
    <button @click="activeModal = 5" class="flex-1 hover:bg-orange-200 transition rounded-lg p-4 text-center">
      <h3 class="text-lg font-semibold text-orange-700">Support</h3>
      <p class="text-sm text-gray-700">We're here to help you.</p>
    </button>
  </div>

  <!-- Modals -->
  <template x-if="activeModal">
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white w-full max-w-md p-6 rounded-lg shadow-lg relative">
        <!-- Modal content -->
        <h2 class="text-xl font-bold text-orange-600 mb-2" x-text="{
          1: 'Inquire',
          2: 'Appointment',
          3: 'Track Order',
          4: 'Request Quote',
          5: 'Support'
        }[activeModal]"></h2>

        <p class="text-sm text-gray-700 mb-4" x-text="{
          1: 'Send us your questions, concerns, or feedback. Our team is ready to assist you anytime.',
          2: 'Schedule a face-to-face or virtual consultation with our experts.',
          3: 'Enter your order ID to track the delivery progress and timeline.',
          4: 'Get a detailed quote based on your construction needs and preferences.',
          5: 'Need help? Our support team is always ready to guide you through any issues.'
        }[activeModal]"></p>

        <button @click="activeModal = null"
          class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        <button @click="activeModal = null"
          class="mt-4 w-full bg-orange-500 hover:bg-orange-600 text-white py-2 rounded-md">
          Close
        </button>
      </div>
    </div>
  </template>
</section>



    <!-- Products Section -->
    <section id="products" class="py-20">
        <div class=" px-4">
            <div class="text-center mb-12 text-black">
                <h2 class="text-4xl font-bold mb-4">Products</h2>
                <p class=" max-w-2xl mx-auto">Discover our wide range of quality materials and furniture for your home and construction needs.</p>
            </div>

            <section class="py-8 bg-white">
                <div class="max-w-7xl mx-auto px-4">
                    <h2 class="text-2xl font-bold text-orange-600 mb-6 text-center" data-aos="fade-up" data-aos-delay="200">All Products</h2>

                    <?php if (empty($products)): ?>
                        <div class="text-center text-gray-500">
                            <p>No products available at the moment.</p>
                        </div>
                    <?php else: ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <?php foreach ($columns as $i => $column): ?>
                                <?php if (!empty($column)): ?>
                                    <div class="h-[300px] overflow-hidden">
                                        <div class="swiper swiper-auto-<?= $i ?>">
                                            <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="700">
                                                <?php foreach ($column as $v): ?>
                                                    <div class="swiper-slide">
                                                        <div class="bg-white rounded-lg shadow text-center flex flex-col h-full p-3 justify-between">
                                                            <div class="w-full aspect-square rounded mb-2 overflow-hidden flex items-center justify-center">
                                                                <?php if (!empty($v['type_image'])): ?>
                                                                    <img src="data:image/jpeg;base64,<?= base64_encode($v['type_image']) ?>"
                                                                        alt="<?= htmlspecialchars($v['namevariant'] ?? 'Product') ?>"
                                                                        class="w-full h-full object-contain" />
                                                                <?php else: ?>
                                                                    <span class="text-xs text-gray-400">No Image</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <h3 class="text-xs font-bold mb-1 text-black underline">
                                                                <?= htmlspecialchars($v['namevariant'] ?? 'No Name') ?>
                                                            </h3>
                                                            <ul class="text-xs text-gray-600 space-y-1">
                                                                <li><strong>Color:</strong> <?= htmlspecialchars($v['color'] ?? 'N/A') ?></li>
                                                                <li><strong>Size:</strong> <?= htmlspecialchars($v['size'] ?? 'N/A') ?></li>
                                                            </ul>
                                                            <p class="text-[12px] font-semibold text-green-600 mt-1">
                                                                ₱<?= number_format(floatval($v['price'] ?? 0), 2) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>


            <section class="px-4 py-10">
                <!-- Header -->
                <div class="text-center mb-10" data-aos="fade-up" data-aos-delay="200">
                    <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight">Bed Room</h2>
                    <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                </div>

                <!-- Swiper Slider -->
                <div class="swiper mySwiper-indoor" data-aos="fade-up" data-aos-delay="300">
                    <div class="swiper-wrapper px-1 sm:px-2">
                        <?php while ($row = mysqli_fetch_assoc($resultss)) : ?>
                            <div class="swiper-slide p-2">
                                <a href="product_view.php?id=<?= (int)$row['id'] ?>"
                                    class="flex flex-col justify-between h-[400px] bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-4 group text-center w-full relative">

                                    <!-- Triangle Badge -->
                                    <div class="absolute top-0 left-0 w-12 h-12 z-10">
                                        <div class="w-12 h-12 bg-blue-400 clip-triangle relative">
                                            <img src="img/icon/b.png" alt="Icon" class="absolute top-1.5 left-1.5 w-5 h-5 object-contain" />
                                        </div>
                                    </div>

                                    <style>
                                        .clip-triangle {
                                            clip-path: polygon(0 0, 100% 0, 0 100%);
                                        }
                                    </style>

                                    <!-- Product Image -->
                                    <div class="aspect-square bg-gray-50 rounded-lg overflow-hidden mb-3">
                                        <?php if (!empty($row['main_image'])): ?>
                                            <img src="data:image/jpeg;base64,<?= base64_encode($row['main_image']) ?>"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Info -->
                                    <div class="mt-auto space-y-1">
                                        <h2 class="text-sm font-semibold text-gray-800 leading-snug break-words underline mb-1">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h2>

                                        <?php if (!empty($row['description'])): ?>
                                            <p class="text-xs text-gray-600 leading-tight line-clamp-2 h-10 overflow-hidden">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-400 italic h-10">No description available.</p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>



            <section class="p-3">
                <div class="text-center mb-10">
                    <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="fade-up">Furniture</h2>

                    <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full" data-aos="fade-up"></div>
                </div>

                <!-- Swiper Slider -->
                <div class="swiper mySwiper-indoor">
                    <div class="swiper-wrapper p-2">
                        <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                            <div class="swiper-slide" data-aos="fade-up">
                                <a href="product_view.php?id=<?= (int)$row['id'] ?>"
                                    class="flex flex-col justify-between h-[380px] bg-white rounded-lg shadow-lg p-4 group text-center w-full relative">

                                    <!-- Green Icon Bubble -->
                                    <div class="absolute top-0 left-0 w-14 h-14 z-10 flex items-start justify-start overflow-visible">
                                        <div class="w-14 h-14 bg-blue-400 clip-triangle relative">
                                            <img src="img/icon/b.png" alt="Check Icon" class="absolute top-1.5 left-1.5 w-6 h-6 object-contain" />
                                        </div>
                                    </div>

                                    <style>
                                        .clip-triangle {
                                            clip-path: polygon(0 0, 100% 0, 0 100%);
                                        }
                                    </style>

                                    <!-- Image -->
                                    <div class="w-full aspect-square mb-3">
                                        <?php if (!empty($row['main_image'])): ?>
<img src="../<?= htmlspecialchars($row['main_image']) ?>"
    class="w-full h-full object-contain bg-gray-100 rounded group-hover:scale-105 transition-transform duration-300 mx-auto"
    alt="<?= htmlspecialchars($row['product_name']) ?>" />

                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gray-200 rounded text-gray-500 text-sm">
                                                No Image
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                 <!-- Name & Description (footer area) -->
<div class="mt-auto">
    <h2 class="text-md font-bold underline text-black text-center break-words mb-1">
        <?= htmlspecialchars($row['product_name']) ?>
    </h2>

 <?php if (!empty($row['unit']) || !empty($row['specification'])): ?>
  <p class="text-xs text-gray-700 leading-snug">
    <?php if (!empty($row['unit'])): ?>
      <span class="font-medium text-gray-500"></span> <?= htmlspecialchars($row['unit']) ?><br>
    <?php endif; ?>
    <?php if (!empty($row['specification'])): ?>
      <span class="font-medium text-gray-500"></span> <?= htmlspecialchars($row['specification']) ?>
    <?php endif; ?>
  </p>
<?php else: ?>
  <p class="text-xs text-gray-400 italic h-10">No description.</p>
<?php endif; ?>

</div>

                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

            </section>

            <section class="p-3">
                <!-- Header -->
                <div class="text-center mb-10">
                    <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="fade-up">Materials</h2>

                    <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full" data-aos="fade-up"></div>
                </div>

                <!-- Swiper Slider -->
                <div class="swiper mySwiper-indoor">
                    <div class="swiper-wrapper p-2">
                        <?php while ($row = mysqli_fetch_assoc($results)) : ?>
                            <div class="swiper-slide h-full" data-aos="fade-up" data-aos-delay="400">
                                <a href="product_view.php?id=<?= (int)$row['id'] ?>"
                                    class="flex flex-col justify-between h-[430px] bg-white rounded-lg shadow-lg p-4 group text-center w-full relative">

                                    <!-- Green Icon Bubble -->
                                    <div class="absolute top-0 left-0 w-14 h-14 z-10 flex items-start justify-start overflow-visible">
                                        <div class="w-14 h-14 bg-blue-400 clip-triangle relative">
                                            <img src="img/icon/b.png" alt="Check Icon" class="absolute top-1.5 left-1.5 w-6 h-6 object-contain" />
                                        </div>
                                    </div>

                                    <style>
                                        .clip-triangle {
                                            clip-path: polygon(0 0, 100% 0, 0 100%);
                                        }
                                    </style>

                                    <!-- Image -->
                                    <div class="w-full aspect-square mb-3">
                                        <?php if (!empty($row['main_image'])): ?>
                                            <img src="data:image/jpeg;base64,<?= base64_encode($row['main_image']) ?>"
                                                class="w-full h-full object-contain bg-gray-100 rounded group-hover:scale-105 transition-transform duration-300 mx-auto"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gray-200 rounded text-gray-500 text-sm">
                                                No Image
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Name & Description (footer area) -->
                                    <div class="mt-auto">
                                        <h2 class="text-sm font-semibold text-gray-800 text-center break-words mb-1">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h2>

                                        <?php if (!empty($row['description'])): ?>
                                            <p class="text-xs text-gray-600 leading-snug line-clamp-2 h-[40px] overflow-hidden">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-400 italic h-[40px]">No description.</p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>


            <section class="px-4 py-10">
                <!-- Header -->
                <div class="text-center mb-10">
                    <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="fade-up">Top Sales</h2>
                    <h2 class="text-2xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="fade-up">
                        Get Up to <span class="text-red-500">40% Discount</span> on Select Items!
                    </h2>

                    <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                </div>

                <!-- Swiper Container -->
                <div class="swiper mySwiper-material">
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
                                <div class="bg-white rounded-xl shadow-lg p-4 group hover:shadow-xl transition duration-300 flex flex-col justify-between h-[480px] text-center relative">

                                    <!-- Triangle Badge -->
                                    <div class="absolute top-0 left-0 z-10">
                                        <div class="w-12 h-12 bg-red-400 clip-triangle relative">
                                            <img src="img/icon/b.png" alt="Icon" class="absolute top-1 left-1 w-5 h-5 object-contain" />
                                        </div>
                                    </div>

                                    <!-- Image -->
                                    <div class="aspect-square w-full bg-gray-50 border border-gray-200 rounded-lg overflow-hidden mb-4">
                                        <?php if (!empty($row['type_image'])): ?>
                                            <img
                                                src="data:image/jpeg;base64,<?= base64_encode($row['type_image']) ?>"
                                                class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105"
                                                alt="<?= htmlspecialchars($row['namevariant']) ?>" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div class="mt-auto">
                                        <h3 class="text-base font-semibold underline text-gray-800 leading-snug break-words">
                                            <?= htmlspecialchars($row['namevariant']) ?>
                                        </h3>
                                        <ul class="text-sm text-gray-700 text-center space-y-1 mb-2">
                                            <li><span class="font-semibold">Color:</span> <?= htmlspecialchars($row['color']) ?></li>
                                            <li><span class="font-semibold">Size:</span> <?= htmlspecialchars($row['size']) ?></li>
                                        </ul>
                                        <!-- Price Display -->
                                        <?php if ($discount > 0): ?>
                                            <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                            <p class="text-base text-green-600 font-bold">
                                                ₱<?= number_format($finalPrice, 2) ?>
                                                <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-base text-green-600 font-bold mb-2">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                        <?php endif; ?>


                                        <div class="flex justify-center gap-2 mt-2">
                                            <!-- Shop Button -->
                                            <form action="product_view.php" method="GET">
                                                <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                <button
                                                    type="submit"
                                                    class="relative bg-red-500 text-white text-sm px-4 py-1.5 rounded-full hover:bg-red-900 transition flex items-center gap-2 shadow-sm hover:shadow-md group
                                                         border-2 border-white ring-2 ring-red-200">
                                                    <!-- 🛍️ Shopping Bag Icon -->
                                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                                    </svg>
                                                    Buy
                                                </button>
                                            </form>


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
                                                <input type="hidden" name="return_url" value="index.php">

                                                <button
                                                    type="submit"
                                                    class="bg-orange-500 text-white text-sm px-2 py-1.5 rounded-full hover:bg-orange-600 transition flex items-center gap-2 shadow-sm hover:shadow-md">
                                                    <img src="img/icon/ecommerce.png" alt="Cart" class="w-4 h-4" />
                                                    Pre-Order
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

            <section class="px-4 py-10">
                <!-- Header -->
                <div class="text-center mb-10">
                    <h2 class="text-2xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="fade-up">Discount Minimal <span class="text-red-500">up to 30%</span></h2>
                    <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full" data-aos="fade-up"></div>
                </div>

                <!-- Swiper Container -->
                <div class="swiper mySwiper-material">
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
                                        <div class="w-12 h-12 bg-red-400 clip-triangle relative">
                                            <img src="img/icon/b.png" alt="Icon" class="absolute top-1 left-1 w-5 h-5 object-contain" />
                                        </div>
                                    </div>

                                    <!-- Image -->
                                    <div class="aspect-square w-full bg-gray-50 border border-gray-200 rounded-lg overflow-hidden mb-4">
                                        <?php if (!empty($row['type_image'])): ?>
                                            <img
                                                src="data:image/jpeg;base64,<?= base64_encode($row['type_image']) ?>"
                                                class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105"
                                                alt="<?= htmlspecialchars($row['namevariant']) ?>" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div class="mt-auto">
                                        <h3 class="text-base font-semibold underline text-gray-800 leading-snug break-words">
                                            <?= htmlspecialchars($row['namevariant']) ?>
                                        </h3>
                                        <ul class="text-sm text-gray-700 text-center space-y-1 mb-2">
                                            <li><span class="font-semibold">Color:</span> <?= htmlspecialchars($row['color']) ?></li>
                                            <li><span class="font-semibold">Size:</span> <?= htmlspecialchars($row['size']) ?></li>
                                        </ul>

                                        <!-- Price Display -->
                                        <?php if ($discount > 0): ?>
                                            <p class="text-sm text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                            <p class="text-base text-green-600 font-bold">
                                                ₱<?= number_format($finalPrice, 2) ?>
                                                <span class="text-sm text-red-500">-<?= number_format($discount, 0) ?>%</span>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-base text-green-600 font-bold mb-2">₱<?= number_format($priceWithMarkup, 2) ?></p>
                                        <?php endif; ?>


                                        <div class="flex justify-center gap-2 mt-2">
                                            <!-- Shop Button -->
                                            <form action="product_view.php" method="GET">
                                                <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                <button
                                                    type="submit"
                                                    class="relative bg-red-500 text-white text-sm px-4 py-1.5 rounded-full hover:bg-red-900 transition flex items-center gap-2 shadow-sm hover:shadow-md group
                                                       border-2 border-white ring-2 ring-red-200">
                                                    <!-- 🛍️ Shopping Bag Icon -->
                                                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                                    </svg>
                                                    Shop
                                                </button>
                                            </form>
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
                                                <input type="hidden" name="return_url" value="index.php">

                                                <button
                                                    type="submit"
                                                    class="bg-orange-500 text-white text-sm px-2 py-1.5 rounded-full hover:bg-orange-600 transition flex items-center gap-2 shadow-sm hover:shadow-md">
                                                    <img src="img/icon/ecommerce.png" alt="Cart" class="w-4 h-4" />
                                                    Pre-Order
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
                <!-- Header -->
                <div class="mb-10 mt-10 text-center">
                    <h2 class="text-4xl font-extrabold text-orange-500 mb-2 tracking-tight" data-aos="slide-up">New Arrival</h2>
                    <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full" data-aos="fade-up"></div>
                </div>

                <!-- Swiper Container -->
                <div class="swiper mySwiper-material">
                    <div class="swiper-wrapper" data-aos="fade-up" data-aos-delay="200">
                        <?php
                        $has_new = false;
                        while ($row = mysqli_fetch_assoc($material_resultstwo)) :
                            if ($row['status'] === 'new') :
                                $has_new = true;
                        ?>
                                <div class="swiper-slide h-full p-2">
                                    <div class="bg-white rounded-xl shadow-lg p-4 group hover:shadow-xl transition-all duration-300 relative flex flex-col justify-between h-[470px] w-full text-center">

                                        <!-- NEW Badge -->
                                        <div class="absolute top-2 right-2 z-10">
                                            <span class="bg-green-500 text-white text-[10px] font-bold px-2 py-1 shadow">
                                                NEW
                                            </span>
                                        </div>


                                        <!-- Corner Icon Bubble -->
                                        <div class="absolute top-0 left-0 w-12 h-12 z-10 flex items-start justify-start overflow-visible">
                                            <div class="w-12 h-12 bg-red-400 clip-triangle relative">
                                                <img src="img/icon/b.png" alt="Check Icon" class="absolute top-1 left-1 w-5 h-5 object-contain" />
                                            </div>
                                        </div>

                                        <!-- Image -->
                                        <div class="w-full aspect-square overflow-hidden rounded-lg bg-gray-50 border border-gray-200 mb-4">
                                            <?php if (!empty($row['type_image'])): ?>
                                                <img
                                                    src="data:image/jpeg;base64,<?= base64_encode($row['type_image']) ?>"
                                                    class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105"
                                                    alt="Material Variant" />
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">No Image</div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($row['discount'] > 0): ?>
                                            <div class="relative flex justify-end w-full mb-2">
                                                <!-- Main Banner -->
                                                <div class="bg-red-500 text-white text-xs font-bold py-1 px-4 pr-6 rounded-l-full relative z-10">
                                                    <?= $row['discount'] ?>% OFF
                                                </div>

                                                <!-- Right Triangle -->
                                                <div class="absolute right-0 top-0 w-0 h-0 border-t-[30px] border-t-red-500 border-l-[14px] border-l-transparent"></div>
                                            </div>
                                        <?php endif; ?>


                                        <!-- Info -->
                                        <div class="mt-auto">
                                            <h3 class="text-base font-semibold underline text-gray-800 leading-snug break-words">
                                                <?= htmlspecialchars($row['namevariant']) ?>
                                            </h3>
                                            <ul class="text-sm text-gray-700 text-center space-y-1 mb-2">
                                                <li><span class="font-semibold">Color:</span> <?= htmlspecialchars($row['color']) ?></li>
                                                <li><span class="font-semibold">Size:</span> <?= htmlspecialchars($row['size']) ?></li>
                                            </ul>
                                            <p class="text-sm text-green-600 mb-2">₱<?= number_format($row['price'], 2) ?></p>


                                            <div class="flex justify-center gap-2 mt-2">
                                                <!-- Shop Button -->
                                                <form action="product_view.php" method="GET">
                                                    <input type="hidden" name="id" value="<?= (int)$row['product_id'] ?>">
                                                    <button
                                                        type="submit"
                                                        class="relative bg-red-500 text-white text-sm px-4 py-1.5 rounded-full hover:bg-red-900 transition flex items-center gap-2 shadow-sm hover:shadow-md group
                                                         border-2 border-white ring-2 ring-red-200">
                                                        <!-- 🛍️ Shopping Bag Icon -->
                                                        <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 11h14l-1.5 9h-11L5 11z" />
                                                        </svg>
                                                        Shop
                                                    </button>
                                                </form>

                                                <form class="productForm" data-product-id="<?= $row['product_id'] ?>">
                                                    <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                                                    <input type="hidden" name="selected_type" value="<?= $row['type_name'] ?>">
                                                    <input type="hidden" name="selected_variant" value="<?= $row['color'] ?>">
                                                    <input type="hidden" name="variant_id" value="<?= $row['variant_id'] ?? '' ?>">
                                                    <input type="hidden" name="selected_color_id" value="<?= $row['color_id'] ?? 1 ?>">
                                                    <input type="hidden" name="selected_color_name" value="<?= $row['color_name'] ?? $row['color'] ?? 'Default' ?>">
                                                    <input type="hidden" name="color_price" value="<?= $row['color_price'] ?? 0 ?>">
                                                    <input type="hidden" name="variant_price" value="<?= $row['variant_price'] ?? 0 ?>">
                                                    <input type="hidden" name="total_price" value="<?= $row['variant_price'] ?? 0 ?>">
                                                    <input type="hidden" name="return_url" value="index.php">

                                                    <button
                                                        type="submit"
                                                        class="bg-orange-500 text-white text-sm px-2 py-1.5 rounded-full hover:bg-orange-600 transition flex items-center gap-2 shadow-sm hover:shadow-md">
                                                        <img src="img/icon/ecommerce.png" alt="Cart" class="w-4 h-4" />
                                                        Pre-Order
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php
                            endif;
                        endwhile;
                        ?>

                        <!-- Fallback if no "new" items -->
                        <?php if (!$has_new): ?>
                            <div class="swiper-slide w-full text-center text-gray-500 py-10">
                                <p class="text-sm italic">No new arrivals at the moment. Please check back later!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-gray-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Contact Us</h2>
                <p class="text-gray-600">Get in touch with us for your construction and furniture needs</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white rounded-lg shadow-md p-6 text-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Address</h3>
                    <p class="text-gray-600 text-sm">MC Premier - 1181 EDSA Balintawak, Quezon City 1008 Quezon City,</p>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 text-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Phone</h3>
                    <p class="text-gray-600 text-sm">0968 591 6536</p>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 text-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Email</h3>
                    <p class="text-gray-600 text-sm">noblehomeconst.ph@gmail.com</p>
                </div>
            </div>
        </div>
    </section>

    <div id="chat-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;">

        <!-- ✅ CHAT TOGGLE BUTTON -->
        <button id="chat-toggle" onclick="openChat()"
            style="background-color:rgb(0, 0, 0); color: white; border: 1px solid white; padding: 12px 18px; border-radius: 50px; box-shadow: 0 0 10px rgba(0,0,0,0.3); font-size: 14px; cursor: pointer;">
            Chat with us
        </button>

        <!-- ✅ CHATBOX IFRAME (Initially hidden) -->
        <div id="chat-box" style="display: none; position: relative; margin-top: 10px;">
            <!-- ❌ Close Button -->
            <button onclick="closeChat()"
                style="position: absolute; top: -10px; right: -10px; background: red; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 16px; cursor: pointer; z-index: 10000;">
                ×
            </button>

            <!-- 🧩 IFRAME CHATBOX -->
            <iframe
                src="chatbot/messenger"
                style="width: 360px; height: 540px; border: none; border-radius: 14px; box-shadow: 0 0 15px rgba(0,0,0,0.2);"
                allow="clipboard-write">
            </iframe>
        </div>
    </div>


    <!-- Include Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>


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
                                <img src="img/logo/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
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
    <script>
        const swiper = new Swiper(".mySwiper-indoor", {
            slidesPerView: 1,
            spaceBetween: 20,
            loop: true,
            autoplay: {
                delay: 3000, // milliseconds per slide
                disableOnInteraction: false // continue even after swipe
            },
            breakpoints: {
                640: {
                    slidesPerView: 2
                },
                768: {
                    slidesPerView: 3
                },
                1024: {
                    slidesPerView: 5
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            new Swiper('.mySwiper-material', {
                slidesPerView: 2,
                spaceBetween: 15,
                autoplay: {
                    delay: 2500,
                    disableOnInteraction: false,
                },
                loop: true,
                breakpoints: {
                    768: {
                        slidesPerView: 3
                    },
                    1024: {
                        slidesPerView: 5
                    },
                },
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
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

                        const response = await fetch('cart/add_to_cart.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            showNotification(data.message || 'Product added to cart!', 'success');
                            updateCartCount(data.cart_count);

                            // Success feedback
                            button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Added!';
                            button.className = button.className.replace('bg-orange-500 hover:bg-orange-600', 'bg-green-500');

                            // Reset after 2 seconds
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

                        // Reset button
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                });
            });
        });

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            } [type] || 'bg-blue-500';

            notification.className = `fixed top-4 right-4 p-4 rounded-lg z-50 ${bgColor} text-white shadow-lg transform transition-all duration-300 translate-x-full`;
            notification.textContent = message;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Animate out and remove
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        function updateCartCount(count) {
            const cartCountElements = document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]');
            cartCountElements.forEach(element => {
                element.textContent = count;
                element.style.display = count > 0 ? 'inline' : 'none';
            });

            const cartBubble = document.getElementById('cart-count-bubble');
            if (cartBubble) {
                if (count > 0) {
                    cartBubble.classList.remove('hidden');
                    cartBubble.style.display = 'inline';
                } else {
                    cartBubble.classList.add('hidden');
                    cartBubble.style.display = 'none';
                }
            }
        }


        function openChat() {
            document.getElementById('chat-box').style.display = 'block';
            document.getElementById('chat-toggle').style.display = 'none';
        }

        function closeChat() {
            document.getElementById('chat-box').style.display = 'none';
            document.getElementById('chat-toggle').style.display = 'inline-block';
        }



        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Check if Swiper is available
            if (typeof Swiper === 'undefined') {
                console.error('Swiper library is not loaded. Please include Swiper CSS and JS files.');
                return;
            }

            // Get all swiper containers
            const swiperContainers = document.querySelectorAll('[class*="swiper-auto-"]');

            swiperContainers.forEach((container, index) => {
                const slides = container.querySelectorAll('.swiper-slide');
                const slideCount = slides.length;

                if (slideCount > 0) {
                    const swiper = new Swiper(container, {
                        direction: 'vertical',
                        loop: slideCount > 1, // Only loop if more than 1 slide
                        slidesPerView: 1,
                        spaceBetween: 0,
                        autoplay: slideCount > 1 ? {
                            delay: 3000 + (index * 500), // Longer delay for smoother experience
                            disableOnInteraction: false,
                            pauseOnMouseEnter: false,
                            waitForTransition: true, // Wait for transition to complete
                        } : false,
                        speed: 1000, // Slower transition for smoothness
                        // Remove fade effect for smoother vertical sliding
                        effect: 'slide',
                        on: {
                            init: function() {
                                console.log(`Swiper ${index} initialized with ${slideCount} slides`);
                            },
                            slideChange: function() {
                                console.log(`Swiper ${index} slide changed`);
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>