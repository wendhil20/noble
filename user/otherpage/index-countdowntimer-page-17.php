<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ FETCH ALL PRODUCTS WITH ACTIVE TIMERS - CORRECT PRICE (SIZE + COLOR SEPARATE)
$query = "
  SELECT 
    p.id,
    p.product_name,
    p.codename,
    p.main_image,
    p.price,
    p.description,
    p.descrip6,
    p.descrip7,
    p.view_count,
    p.unique_view_count,
    MIN(pv.timer_discount_start) as timer_start,
    MAX(pv.timer_discount_end) as timer_end,
    MAX(pv.timer_discount_percent) as timer_discount,
    MAX(pv.discount) as discount,
    MAX(pv.percent) as percent,
    MAX(pv.origin) as origin,
    MAX(pv.status) as status,
    -- 🔥 SIZE PRICES (ALL variant prices with timer)
    MIN(pv.price) as min_size_price,  
    MAX(pv.price) as max_size_price,  
    -- 🔥 COLOR PRICES (color prices only)
    MIN(pc.price) as min_color_price,
    MAX(pc.price) as max_color_price,
    COUNT(DISTINCT pc.id) as color_count,
    COUNT(DISTINCT pv.id) as variant_count,
    COALESCE(SUM(pv.stock), 0) as total_stock,
    AVG(r.rating) AS avg_rating,
    COUNT(r.rating) AS rating_count,
    COALESCE(SUM(si.quantity), 0) AS total_sold
  FROM products p
  LEFT JOIN product_types pt ON p.id = pt.product_id
  LEFT JOIN product_variants pv ON pt.id = pv.type_id
  LEFT JOIN product_colors pc ON p.id = pc.product_id
  LEFT JOIN product_ratings r ON r.product_id = p.id
  LEFT JOIN sold_items si ON si.product_id = p.id
  WHERE pv.timer_discount_active = 1
    AND pv.timer_discount_end IS NOT NULL
    AND pv.timer_discount_start IS NOT NULL
    AND UNIX_TIMESTAMP(pv.timer_discount_end) >= UNIX_TIMESTAMP(NOW())
  GROUP BY p.id
  ORDER BY pv.timer_discount_end ASC
";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$products_with_timers = [];

while ($row = $result->fetch_assoc()) {
    $products_with_timers[] = $row;
}
$stmt->close();

$now = time();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limited Time Offers - Noble Home</title>
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        @keyframes timerPulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.3);
            }

            50% {
                box-shadow: 0 0 0 6px rgba(0, 0, 0, 0);
            }
        }

        .timer-pulse {
            animation: timerPulse 2s infinite;
        }

        .product-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e5e7eb;
        }

        .product-card:hover {
            border-color: #f97316;
            box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .product-image-container {
            background-color: #f5f5f5;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image-container img {
            transition: transform 0.3s ease;
            object-fit: contain;
            max-width: 100%;
            max-height: 100%;
        }

        .product-card:hover .product-image-container img {
            transform: scale(1.05);
        }

        .discount-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background-color: #f97316;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .stock-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .countdown-timer {
            background-color: #f01313ff;
            color: white;
            padding: 8px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            text-align: center;
            border: 2px solid #f97316;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .countdown-timer.expired {
            background-color: #666;
            border-color: #999;
            color: #ddd;
        }

        .star-rating {
            color: #fbbf24;
        }

        .filter-btn {
            transition: all 0.2s ease;
            border: 2px solid transparent;
            background-color: white;
            color: #000;
            font-weight: 500;
        }

        .filter-btn:hover {
            border-color: #f97316;
            color: #f97316;
        }

        .filter-btn.active {
            background-color: #000;
            color: white;
            border-color: #000;
        }

        .cta-button {
            background-color: #f97316;
            color: white;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .cta-button:hover {
            background-color: #000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .breadcrumb {
            font-size: 14px;
            color: #666;
        }

        .breadcrumb a {
            color: #f97316;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
        }

        @media (max-width: 640px) {
            .products-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body class="font-roboto">
    <?php include '../navbar/top.php'; ?>

    <!-- Breadcrumb -->
    <nav class="bg-white border-b border-gray-200 px-4 py-3">
        <div class="max-w-7xl mx-auto">
            <div class="breadcrumb flex items-center space-x-2">
                <a href="index-page-1-A-B-C-D-E">
                    <i class="fas fa-home mr-1"></i>Home
                </a>
                <span class="text-gray-400">/</span>
                <span class="text-gray-600 font-medium">Limited Time Offers</span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="text-base">
                <h1 class="text-4xl font-bold text-black mb-2">Limited Time Offers</h1>
                <p class="text-gray-600 text-lg mb-2">Exclusive deals with countdown timers</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-12">

        <?php if (count($products_with_timers) > 0): ?>

            <!-- Filter + Products Layout -->
            <div class="flex flex-col lg:flex-row gap-6">

                <!-- Sidebar Filter -->
                <div class="lg:w-64 flex-shrink-0">
                    <div class="bg-white border border-gray-200 rounded-lg p-6 sticky top-24">
                        <h3 class="text-lg font-bold text-black mb-4">
                            <i class="fas fa-filter mr-2 text-orange-500"></i>Filter
                        </h3>
                        <div class="space-y-4">

                            <!-- Expiring Soon Filter -->
                            <div class="border-b border-gray-200 pb-4">
                                <button onclick="toggleFilter('expiring')" class="w-full flex items-center justify-between py-2 hover:text-orange-500 transition">
                                    <span class="font-medium text-black">Expiring Soon</span>
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </button>
                                <div id="filter-expiring" class="mt-2">
                                    <label class="flex items-center gap-2 cursor-pointer py-1.5 hover:text-orange-500">
                                        <input type="radio" name="sort" value="expiring" onchange="sortBy('expiring')" class="w-4 h-4" checked>
                                        <span class="text-sm text-gray-700">Expiring Soon</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Highest Discount Filter -->
                            <div class="pb-4">
                                <button onclick="toggleFilter('discount')" class="w-full flex items-center justify-between py-2 hover:text-orange-500 transition">
                                    <span class="font-medium text-black">Highest Discount</span>
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </button>
                                <div id="filter-discount" class="mt-2 hidden">
                                    <label class="flex items-center gap-2 cursor-pointer py-1.5 hover:text-orange-500">
                                        <input type="radio" name="sort" value="discount" onchange="sortBy('discount')" class="w-4 h-4">
                                        <span class="text-sm text-gray-700">Highest Discount</span>
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="flex-1">
                    <div class="products-grid" id="productsGrid">

                        <?php foreach ($products_with_timers as $product):
                            $timer_end = strtotime($product['timer_end']);
                            $remaining = $timer_end - $now;
                            $days = floor($remaining / 86400);
                            $hours = floor(($remaining % 86400) / 3600);
                            $minutes = floor(($remaining % 3600) / 60);
                            $seconds = $remaining % 60;
                            $total_hours = ($days * 24) + $hours;

                            // Get colors
                            $color_q = $conn->prepare("SELECT color_name FROM product_colors WHERE product_id = ? LIMIT 2");
                            $color_q->bind_param("i", $product['id']);
                            $color_q->execute();
                            $color_result = $color_q->get_result();
                            $colors = [];
                            while ($c = $color_result->fetch_assoc()) {
                                $colors[] = $c['color_name'];
                            }
                            $color_q->close();

                            // Get sizes
                            $size_q = $conn->prepare("
                SELECT DISTINCT pv.size 
                FROM product_variants pv
                JOIN product_types pt ON pv.type_id = pt.id
                WHERE pt.product_id = ?
                LIMIT 2
              ");
                            $size_q->bind_param("i", $product['id']);
                            $size_q->execute();
                            $size_result = $size_q->get_result();
                            $sizes = [];
                            while ($s = $size_result->fetch_assoc()) {
                                $sizes[] = $s['size'];
                            }
                            $size_q->close();

                            // Get actual SIZE + COLOR prices directly (ALL variants, not just timer ones)
                            $size_price_q = $conn->prepare("
                SELECT MIN(pv.price) as min_size, MAX(pv.price) as max_size
                FROM product_variants pv
                JOIN product_types pt ON pv.type_id = pt.id
                WHERE pt.product_id = ?
              ");
                            $size_price_q->bind_param("i", $product['id']);
                            $size_price_q->execute();
                            $size_prices = $size_price_q->get_result()->fetch_assoc();
                            $size_price_q->close();

                            $color_price_q = $conn->prepare("
                SELECT MIN(price) as min_color, MAX(price) as max_color
                FROM product_colors
                WHERE product_id = ?
              ");
                            $color_price_q->bind_param("i", $product['id']);
                            $color_price_q->execute();
                            $color_prices = $color_price_q->get_result()->fetch_assoc();
                            $color_price_q->close();

                            $minSizePrice = (float)($size_prices['min_size'] ?? 0);
                            $maxSizePrice = (float)($size_prices['max_size'] ?? 0);
                            $minColorPrice = (float)($color_prices['min_color'] ?? 0);
                            $maxColorPrice = (float)($color_prices['max_color'] ?? 0);
                            $variantDiscount = (float)($product['discount'] ?? 0);
                            $percent = (float)($product['percent'] ?? 0);

                            // 🔥 SIMPLE FORMULA: final_size_price + color_price
                            $minFinalPrice = $minSizePrice + $minColorPrice;
                            $maxFinalPrice = $maxSizePrice + $maxColorPrice;

                            // Format display price
                            if ($minFinalPrice != $maxFinalPrice) {
                                $displayPrice = '₱' . number_format($minFinalPrice, 2) . ' - ₱' . number_format($maxFinalPrice, 2);
                            } else {
                                $displayPrice = '₱' . number_format($minFinalPrice, 2);
                            }
                        ?>

                            <a href="index-product_view-page-4-AA.php?id=<?= $product['id'] ?>"
                                class="product-card bg-white rounded-lg overflow-hidden"
                                data-expiry="<?= $timer_end ?>"
                                data-discount="<?= (int)$product['timer_discount'] ?>"
                                data-rating="<?= number_format($product['avg_rating'] ?? 0, 1) ?>"
                                data-sold="<?= $product['total_sold'] ?>">

                                <!-- Image Container -->
                                <div class="relative product-image-container">
                                    <?php if (!empty($product['main_image'])): ?>
                                        <img src="../../<?= htmlspecialchars($product['main_image']) ?>"
                                            alt="<?= htmlspecialchars($product['product_name']) ?>"
                                            loading="lazy">
                                    <?php else: ?>
                                        <div class="text-gray-300">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    <?php endif; ?>

                                </div>

                                <!-- Content -->
                                <div class="p-4">
                                    <!-- Product Name -->
                                    <h3 class="text-black font-semibold text-sm mb-1 line-clamp-2">
                                        <?= htmlspecialchars($product['product_name']) ?>
                                    </h3>

                                    <!-- Description -->
                                    <p class="text-gray-600 text-xs mb-2 line-clamp-2">
                                        <?= htmlspecialchars($product['description']) ?>
                                    </p>

                                    <!-- Rating -->
                                    <div class="flex items-center gap-2 mb-2">
                                        <?php if ($product['rating_count'] > 0): ?>
                                            <div class="flex star-rating text-xs">
                                                <?php
                                                $rating = (int)$product['avg_rating'];
                                                for ($i = 0; $i < $rating; $i++) echo '<i class="fas fa-star"></i>';
                                                for ($i = $rating; $i < 5; $i++) echo '<i class="far fa-star"></i>';
                                                ?>
                                            </div>
                                            <span class="text-gray-500 text-xs">(<?= $product['rating_count'] ?>)</span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No reviews</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Smart Price Display with Discount -->
                                    <div class="flex items-center gap-2 mb-3 border-t border-gray-200 pt-2">

                                        <!-- Display Price -->
                                        <span class="text-base font-bold text-black"><?= $displayPrice ?></span>

                                        <!-- Variant Discount -->
                                        <?php if ($variantDiscount > 0): ?>
                                            <span class="text-[7px] font-semibold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">
                                                -<?= number_format($variantDiscount, 0) ?>%
                                            </span>
                                        <?php endif; ?>

                                        <!-- Timer Discount Badge -->
                                        <div class="text-[11px] font-semibold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">
                                            -<?= (int)$product['timer_discount'] ?>%
                                        </div>

                                    </div>

                                    <!-- Countdown Timer + CTA Button (Side by Side) -->
                                    <div class="flex gap-2">
                                        <!-- Countdown Timer -->
                                        <div class="countdown-timer flex-1" data-end-time="<?= $timer_end ?>">
                                            <span class="text-xs text-white">Ends in</span>
                                            <span class="timer-display text-xs font-mono">
                                                <?= sprintf('%02d:%02d:%02d', $total_hours, $minutes, $seconds) ?>
                                            </span>
                                        </div>

                                        <!-- CTA Button -->
                                        <button class="flex-1 cta-button py-2 rounded-lg font-medium text-sm transition flex items-center justify-center gap-2">
                                            <i class="fas fa-shopping-cart"></i>
                                            View Offer
                                        </button>
                                    </div>
                                </div>
                            </a>

                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        <?php else: ?>

            <!-- No Products State -->
            <div class="text-center py-20 bg-white rounded-lg border border-gray-200">
                <div class="text-gray-300 text-6xl mb-4">
                    <i class="fas fa-inbox"></i>
                </div>
                <h2 class="text-2xl font-bold text-black mb-2">No Active Offers</h2>
                <p class="text-gray-600 mb-6">There are currently no limited time offers available.</p>
                <a href="index-page-1-A-B-C-D-E" class="inline-block cta-button px-6 py-2 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Browse All Products
                </a>
            </div>

        <?php endif; ?>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <script>
        // Toggle filter section
        function toggleFilter(type) {
            const filterDiv = document.getElementById(`filter-${type}`);
            filterDiv.classList.toggle('hidden');
        }

        // Initialize countdowns
        function initCountdowns() {
            const timers = document.querySelectorAll('[data-end-time]');

            function updateTimers() {
                timers.forEach(timer => {
                    const endTime = parseInt(timer.dataset.endTime);
                    const now = Math.floor(Date.now() / 1000);
                    const remaining = endTime - now;

                    const display = timer.querySelector('.timer-display');
                    if (!display) return;

                    if (remaining <= 0) {
                        display.textContent = 'EXPIRED';
                        timer.classList.add('expired');
                        timer.classList.remove('timer-pulse');
                        return;
                    }

                    const days = Math.floor(remaining / 86400);
                    const hours = Math.floor((remaining % 86400) / 3600);
                    const minutes = Math.floor((remaining % 3600) / 60);
                    const seconds = remaining % 60;

                    const totalHours = (days * 24) + hours;
                    display.textContent = `${String(totalHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                });
            }

            updateTimers();
            setInterval(updateTimers, 1000);
        }

        // Sorting function
        function sortBy(type) {
            const grid = document.getElementById('productsGrid');
            const products = Array.from(grid.querySelectorAll('a.product-card'));

            products.sort((a, b) => {
                switch (type) {
                    case 'expiring':
                        return parseInt(a.dataset.expiry) - parseInt(b.dataset.expiry);
                    case 'discount':
                        return parseInt(b.dataset.discount) - parseInt(a.dataset.discount);
                    case 'rating':
                        return parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating);
                    case 'sold':
                        return parseInt(b.dataset.sold) - parseInt(a.dataset.sold);
                    default:
                        return 0;
                }
            });

            // Clear and re-append
            products.forEach(product => {
                grid.appendChild(product);
            });

            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.filter-btn')?.classList.add('active');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initCountdowns);
    </script>

</body>

</html>