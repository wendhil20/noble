<?php
ob_start();
session_name("nobleuser");
session_start();
include '../connection/connect.php';

// Check login notification
if (isset($_SESSION['login_needed'])) {
    $notification_message = $_SESSION['login_needed'];
    unset($_SESSION['login_needed']);
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $product_id = (int)$_POST['product_id'];
    $variant_id = (int)$_POST['variant_id'];
    $selected_variant = $_POST['selected_variant'] ?? '';
    $selected_type = $_POST['selected_type'] ?? '';
    $color_name = $_POST['selected_color_name'] ?? '';
    $variant_price = (float)$_POST['variant_price'];
    
    $cart_key = $product_id . '_' . $variant_id;
    
    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['quantity'] += 1;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'product_id' => $product_id,
            'variant_id' => $variant_id,
            'variant_name' => $selected_variant,
            'type_name' => $selected_type,
            'color_name' => $color_name,
            'price' => $variant_price,
            'quantity' => 1
        ];
    }
    
    $_SESSION['cart_message'] = "Product added to cart!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// KUHAIN LAHAT NG VARIANTS + PRODUCT + COLOR INFO + RATINGS (ISANG QUERY LANG)
$allQuery = "
    SELECT 
        pv.*, pv.origin,
        pt.type_name, pt.type_image, pt.product_id,
        p.product_name, p.codename, p.main_image, p.description,
        pc.id AS color_id, pc.color_name AS color, pc.color_code, pc.price AS color_price,
        r.avg_rating, r.total_raters
    FROM product_variants pv
    JOIN product_types pt ON pv.type_id = pt.id
    JOIN products p ON pt.product_id = p.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN (
        SELECT 
            product_id,
            ROUND(AVG(rating), 1) AS avg_rating,
            COUNT(*) AS total_raters
        FROM product_ratings
        GROUP BY product_id
    ) r ON p.id = r.product_id
    ORDER BY pv.percent ASC, p.id ASC, pc.id ASC
";
$allResult = mysqli_query($conn, $allQuery);

$allProducts = [];
while ($row = mysqli_fetch_assoc($allResult)) {
    $allProducts[] = $row;
}

// ----------------- PHP FILTERING ----------------- //

// 1. Basic variants list
$result_variants = array_map(function($p) {
    return [
        'id' => $p['id'],
        'type_id' => $p['type_id'],
        'color' => $p['color'],
        'size' => $p['size'],
        'price' => $p['price'],
        'percent' => $p['percent'],
        'image' => $p['image'],
        'origin' => $p['origin']
    ];
}, $allProducts);

// 2. Furniture product list
$SYCJ_result = array_filter($allProducts, fn($p) => $p['codename'] === 'furniture');

// 3. Discount 30% materials
$material_results = array_filter($allProducts, fn($p) => $p['discount'] == 30);

// 4. Discount between 1-15%
$material_resultsone = array_filter($allProducts, fn($p) => $p['discount'] >= 1 && $p['discount'] <= 15);

// 5. Status = new
$material_resultstwo = array_filter($allProducts, fn($p) => strtolower($p['status']) === 'new');

// 6. Products without discount
$discount_result = array_filter($allProducts, fn($p) => empty($p['discount']) || $p['discount'] == 0);

// 7. Furniture codename filter
$result = array_filter($allProducts, fn($p) => $p['codename'] === 'furniture');

// 8. Material codename filter
$results = array_filter($allProducts, fn($p) => $p['codename'] === 'material');

// 9. Bedfurniture codename filter
$resultss = array_filter($allProducts, fn($p) => $p['codename'] === 'bedfurniture');

// 10. Organize discount products into columns
$products = $discount_result;
$columns = count($products) > 0 ? array_chunk($products, ceil(count($products) / 3)) : [[], [], []];

// 11. Slider images
$sql = "SELECT filename FROM discount_images ORDER BY uploaded_at DESC";
$slideresult = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Furniture Slider</title>
<script src="https://cdn.tailwindcss.com"></script>

<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<!-- Font Awesome (for stars) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body class="bg-gray-50">



<!-- First Swiper Section -->
<div class="swiper mySwiper-indoor-1 p-4">
    <div class="swiper-wrapper">
        <?php foreach ($result as $row): ?>
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
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold text-orange-600 underline underline-offset-4 truncate max-w-[60%]">
                                <?= htmlspecialchars($row['product_name']) ?>
                            </h2>

                            <?php
                            $avg_rating = $row['avg_rating'] ?? 0;
                            $total_raters = $row['total_raters'] ?? 0;
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

                        <?php if (!empty($row['descrip6']) || !empty($row['descrip7'])): ?>
                            <p class="text-xs text-gray-700 leading-snug h-10 overflow-hidden">
                                <?= htmlspecialchars($row['descrip6'] ?? '') ?>
                                <?= (!empty($row['descrip6']) && !empty($row['descrip7'])) ? '<br>' : '' ?>
                                <?= htmlspecialchars($row['descrip7'] ?? '') ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                Origin:
                                <span class="<?= $row['origin'] === 'international' ? 'text-red-500' : 'text-blue-500' ?>">
                                    <?= ucfirst($row['origin']) ?>
                                </span>
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 italic h-10">No description.</p>
                        <?php endif; ?>

                        <!-- Buttons -->
                        <div class="mt-2 space-y-2">
                            <!-- View Button -->
                            <a href="product_view?id=<?= (int)$row['product_id'] ?>"
                                class="p-2 inline-block text-center w-full bg-black hover:bg-orange-600 text-white text-sm font-semibold py-1.5 rounded transition duration-200">
                                View Product
                            </a>
                            
                            <!-- Add to Cart Button -->
                            <form method="POST" class="productForm">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                                <input type="hidden" name="variant_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                <input type="hidden" name="selected_variant" value="<?= htmlspecialchars($row['namevariant'] ?? '') ?>">
                                <input type="hidden" name="selected_type" value="<?= htmlspecialchars($row['type_name'] ?? '') ?>">
                                <input type="hidden" name="selected_color_name" value="<?= htmlspecialchars($row['color'] ?? '') ?>">
                                <input type="hidden" name="variant_price" value="<?= floatval($row['price'] ?? 0) ?>">
                                
                                <button type="submit"
                                    class="w-full bg-orange-500 text-white text-sm px-3 py-1.5 rounded hover:bg-orange-600 transition flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                                    <img src="../img/ecommerce.png" alt="Cart" class="w-4 h-4" />
                                    Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    
</div>

<!-- Second Swiper Section (View Only) -->
<div class="swiper mySwiper-indoor-2 p-4">
    <div class="swiper-wrapper">
        <?php foreach ($result as $row): ?>
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
                            $avg_rating = $row['avg_rating'] ?? 0;
                            $total_raters = $row['total_raters'] ?? 0;
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

                        <!-- View Button Only -->
                        <div class="mt-2">
                            <a href="product_view?id=<?= (int)$row['product_id'] ?>"
                                class="p-2 inline-block text-center w-full bg-black hover:bg-orange-600 text-white text-sm font-semibold py-1.5 rounded transition duration-200">
                                View Product
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize both swipers with different selectors
    new Swiper(".mySwiper-indoor-1", {
        slidesPerView: 1,
        spaceBetween: 10,
        loop: true,
        autoplay: {
            delay: 3000,
            disableOnInteraction: false
        },
        breakpoints: {
            640: { slidesPerView: 2, spaceBetween: 15 },
            768: { slidesPerView: 3, spaceBetween: 20 },
            1024: { slidesPerView: 4, spaceBetween: 25 }
        },
        pagination: {
            el: ".swiper-pagination",
            clickable: true
        }
    });
    
    new Swiper(".mySwiper-indoor-2", {
        slidesPerView: 1,
        spaceBetween: 10,
        loop: true,
        autoplay: {
            delay: 3500, // Different delay to avoid sync
            disableOnInteraction: false
        },
        breakpoints: {
            640: { slidesPerView: 2, spaceBetween: 15 },
            768: { slidesPerView: 3, spaceBetween: 20 },
            1024: { slidesPerView: 4, spaceBetween: 25 }
        }
    });
});
</script>
</body>
</html>