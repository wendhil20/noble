<?php
// index-subcategory-recommendations-page-15.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Session restoration from remember_token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $_COOKIE['remember_token']);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

$is_guest = !isset($_SESSION['user_id']);

$from_page = isset($_GET['from']) ? $_GET['from'] : '';

$subcategory_id = isset($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : 0;

if ($subcategory_id === 0) {
    header('Location: index-shop-page-2.php');
    exit;
}

// Get subcategory information
$sub_stmt = $conn->prepare("
    SELECT ps.subcategory_name, ps.category_id, c.name as category_name 
    FROM product_subcategories ps 
    LEFT JOIN categories c ON ps.category_id = c.id 
    WHERE ps.id = ?
");
$sub_stmt->bind_param("i", $subcategory_id);
$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();

if ($sub_result->num_rows === 0) {
    header('Location: index-shop-page-2.php');
    exit;
}

$subcategory_data = $sub_result->fetch_assoc();
$subcategory_name = $subcategory_data['subcategory_name'];
$category_name = $subcategory_data['category_name'];
$category_id = $subcategory_data['category_id'];
$sub_stmt->close();

$back_url = '';
if ($from_page === 'grid' && !empty($category_name)) {
    $back_url = 'index-subcategory_grid_page-14.php?category_name=' . urlencode(strtolower($category_name));
}

// GET ALL SUB-SUBCATEGORIES (COLLECTIONS)
$collections_query = "
    SELECT 
        pss.id,
        pss.sub_subcategory_name,
        pss.sub_subcategory_slug,
        pss.image_path,
        COALESCE((SELECT SUM(p.view_count)
         FROM products p
         INNER JOIN product_variants pv ON p.id = pv.product_id
         WHERE pv.category_id = ?
         AND (
           pv.sub_subcategory_ids LIKE CONCAT('%\"', pss.id, '\"%')
           OR pv.sub_subcategory_ids LIKE CONCAT('%,', pss.id, ',%')
           OR pv.sub_subcategory_ids LIKE CONCAT('[', pss.id, ',%')
           OR pv.sub_subcategory_ids LIKE CONCAT('%,', pss.id, ']')
           OR pv.sub_subcategory_ids LIKE CONCAT('[', pss.id, ']')
           OR pv.sub_subcategory_id = pss.id
         )
        ), 0) as total_views,
        COALESCE((SELECT COUNT(DISTINCT pv.product_id)
         FROM product_variants pv
         WHERE pv.category_id = ?
         AND (
           pv.sub_subcategory_ids LIKE CONCAT('%\"', pss.id, '\"%')
           OR pv.sub_subcategory_ids LIKE CONCAT('%,', pss.id, ',%')
           OR pv.sub_subcategory_ids LIKE CONCAT('[', pss.id, ',%')
           OR pv.sub_subcategory_ids LIKE CONCAT('%,', pss.id, ']')
           OR pv.sub_subcategory_ids LIKE CONCAT('[', pss.id, ']')
           OR pv.sub_subcategory_id = pss.id
         )
        ), 0) as product_count
    FROM product_sub_subcategories pss
    WHERE pss.subcategory_id = ?
    ORDER BY total_views DESC, pss.sub_subcategory_name ASC
";

$collections_stmt = $conn->prepare($collections_query);
$collections_stmt->bind_param("iii", $category_id, $category_id, $subcategory_id);
$collections_stmt->execute();
$collections_result = $collections_stmt->get_result();
$collections = [];

while ($collection = $collections_result->fetch_assoc()) {
    $collections[] = $collection;
}
$collections_stmt->close();

// For each collection, get TOP 6 most viewed products
foreach ($collections as &$collection) {
    // 🔥 CORRECTED: Use product_types JOIN like countdown timer (WORKS!)
    $products_query = "
        SELECT DISTINCT
            p.id,
            p.product_name,
            p.main_image,
            p.description,
            p.view_count,
            p.price as base_price,
            
            -- 🔥 SIZE PRICES via product_types (CORRECT!)
            (SELECT COALESCE(MIN(pv.price), 0)
             FROM product_variants pv
             JOIN product_types pt ON pv.type_id = pt.id
             WHERE pt.product_id = p.id
            ) as min_size_price,
            
            (SELECT COALESCE(MAX(pv.price), 0)
             FROM product_variants pv
             JOIN product_types pt ON pv.type_id = pt.id
             WHERE pt.product_id = p.id
            ) as max_size_price,
            
            -- 🔥 COLOR PRICES FROM SUBQUERY (clean)
            (SELECT COALESCE(MIN(price), 0)
             FROM product_colors 
             WHERE product_id = p.id
            ) as min_color_price,
            
            (SELECT COALESCE(MAX(price), 0)
             FROM product_colors 
             WHERE product_id = p.id
            ) as max_color_price,
            
            -- Discount & Markup
            (SELECT COALESCE(MAX(discount), 0)
             FROM product_variants pv
             JOIN product_types pt ON pv.type_id = pt.id
             WHERE pt.product_id = p.id
            ) as discount,
            
            (SELECT COALESCE(MIN(percent), 0)
             FROM product_variants pv
             JOIN product_types pt ON pv.type_id = pt.id
             WHERE pt.product_id = p.id
            ) as percent,
            
            -- Colors
            (SELECT GROUP_CONCAT(DISTINCT color_name)
             FROM product_colors 
             WHERE product_id = p.id
            ) as color_name
            
        FROM products p
        INNER JOIN product_variants pv ON p.id = pv.product_id
        WHERE pv.category_id = ?
        AND (
            pv.sub_subcategory_ids LIKE CONCAT('%\"', ?, '\"%')
            OR pv.sub_subcategory_ids LIKE CONCAT('%,', ?, ',%')
            OR pv.sub_subcategory_ids LIKE CONCAT('[', ?, ',%')
            OR pv.sub_subcategory_ids LIKE CONCAT('%,', ?, ']')
            OR pv.sub_subcategory_ids LIKE CONCAT('[', ?, ']')
            OR pv.sub_subcategory_id = ?
        )
        AND p.is_archived = 0    
        GROUP BY p.id
        ORDER BY p.view_count DESC
        LIMIT 6
    ";
    
    $products_stmt = $conn->prepare($products_query);
    $coll_id = $collection['id'];
    
    // Bind params: main WHERE + 6 OR conditions = 7 total
    $products_stmt->bind_param(
        "iiiiiii",
        $category_id,      // main WHERE category_id
        $coll_id,          // 6 OR conditions
        $coll_id,
        $coll_id,
        $coll_id,
        $coll_id,
        $coll_id
    );
    
    $products_stmt->execute();
    $products_result = $products_stmt->get_result();
    
    $collection['products'] = [];
    while ($product = $products_result->fetch_assoc()) {
        $collection['products'][] = $product;
    }
    $products_stmt->close();
}
unset($collection);

// 🔥 SMART PRICE DISPLAY - CORRECTED
function calculateSmartPriceDisplay($product)
{
    $min_size = floatval($product['min_size_price'] ?? 0);
    $max_size = floatval($product['max_size_price'] ?? 0);
    $min_color = floatval($product['min_color_price'] ?? 0);
    $max_color = floatval($product['max_color_price'] ?? 0);
    $base_price = floatval($product['base_price'] ?? $product['price'] ?? 0);

    $discount = floatval($product['discount'] ?? 0);
    $percent = floatval($product['percent'] ?? 0);

    // 🔥 SMART LOGIC: Only add if BOTH exist
    $minFinalPrice = 0;
    $maxFinalPrice = 0;

    if ($min_size > 0 && $min_color > 0) {
        // Both size and color exist - add them
        $minFinalPrice = $min_size + $min_color;
        $maxFinalPrice = $max_size + $max_color;
    } else if ($min_size > 0) {
        // Only size prices
        $minFinalPrice = $min_size;
        $maxFinalPrice = $max_size;
    } else if ($min_color > 0) {
        // Only color prices
        $minFinalPrice = $min_color;
        $maxFinalPrice = $max_color;
    } else {
        // Fallback to base price
        $minFinalPrice = $base_price;
        $maxFinalPrice = $base_price;
    }

    $result = [
        'has_range' => false,
        'min_price' => $minFinalPrice,
        'max_price' => $minFinalPrice,
        'display_price' => '₱' . number_format($minFinalPrice, 2)
    ];

    if ($minFinalPrice > 0 || $maxFinalPrice > 0) {
        if (abs($minFinalPrice - $maxFinalPrice) > 0.01) {
            $result['has_range'] = true;
            $result['min_price'] = $minFinalPrice;
            $result['max_price'] = $maxFinalPrice;
            $result['display_price'] = '₱' . number_format($minFinalPrice, 2) . ' - ₱' . number_format($maxFinalPrice, 2);
        } else {
            $result['min_price'] = $minFinalPrice;
            $result['max_price'] = $minFinalPrice;
            $result['display_price'] = '₱' . number_format($minFinalPrice, 2);
        }
    }

    $result['discount'] = $discount;
    $result['percent'] = $percent;

    return $result;
}

function formatViewCount($count) {
    if ($count >= 1000) {
        return number_format($count / 1000, 1) . 'k';
    }
    return number_format($count);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommended <?= htmlspecialchars($subcategory_name) ?> Collections</title>
</head>

<body class="min-h-screen bg-gray-50" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
    <?php include '../navbar/top.php'; ?>
<?php include 'push-notification.php'; ?>
    <!-- Header Section -->
    <div class="bg-black text-white">
        <div class="container mx-auto px-6 py-8">
            <div>
                <h1 class="text-4xl md:text-5xl mb-4 uppercase font-semibold">
                    <?= htmlspecialchars($subcategory_name) ?> Collections
                </h1>

                <!-- Breadcrumb -->
                <nav class="flex items-center" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-2 text-sm">
                        <li>
                            <a href="index-page-1-A-B-C-D-E.php" class="text-white hover:text-gray-200 uppercase">
                                Home
                            </a>
                        </li>
                        <li><i class="fas fa-chevron-right text-white mx-2"></i></li>
                        <li>
                            <a href="index-subcategory_grid_page-14.php?category_name=<?= urlencode(strtolower($category_name)) ?>"
                               class="text-white hover:text-gray-300 uppercase">
                                <?= htmlspecialchars($category_name) ?>
                            </a>
                        </li>
                        <li><i class="fas fa-chevron-right text-white mx-2"></i></li>
                        <li class="text-gray-300 uppercase">
                            <?= htmlspecialchars($subcategory_name) ?> Collections
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-12">
        
        <?php if (!empty($collections)): ?>
            <!-- Collections Loop -->
            <?php foreach ($collections as $collection): ?>
                <div class="mb-16">
                    <!-- Collection Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <div class="inline-flex items-center gap-2 <?= !empty($collection['products']) ? 'bg-orange-500' : 'bg-gray-400' ?> px-4 py-2 rounded-full text-sm mb-3 font-semibold">
                                <?= !empty($collection['products']) ? 'Recommended' : 'NO PRODUCTS YET' ?>
                            </div>
                            <h2 class="text-3xl font-bold  uppercase">
                                <?= htmlspecialchars($collection['sub_subcategory_name']) ?>
                            </h2>
                        </div>
                    </div>

                    <!-- Products Grid -->
                    <?php if (!empty($collection['products'])): ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                            <?php foreach ($collection['products'] as $row): ?>
                                <?php
                                $product_id = (int)$row['id'];
                                $view_count = (int)($row['view_count'] ?? 0);
                                
                                // 🔥 Use the smart price display function
                                $priceData = calculateSmartPriceDisplay($row);
                                $discount = (float)($row['discount'] ?? 0);

                                // Get rating
                                $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                                $rating_q->bind_param("i", $product_id);
                                $rating_q->execute();
                                $rating_result = $rating_q->get_result()->fetch_assoc();
                                $avg_rating = $rating_result['avg_rating'] ?? 0;
                                $total_raters = $rating_result['total_raters'] ?? 0;
                                $rating_q->close();

                                // Calculate star display
                                $full = floor($avg_rating);
                                $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                $empty = 5 - $full - $half;

                                // Get sold count
                                $sold_q = $conn->prepare("SELECT SUM(quantity) as total_sold FROM sold_items WHERE product_id = ?");
                                $sold_q->bind_param("i", $product_id);
                                $sold_q->execute();
                                $sold_result = $sold_q->get_result()->fetch_assoc();
                                $total_sold = (int)($sold_result['total_sold'] ?? 0);
                                $sold_q->close();

                                $colors = !empty($row['color_name']) ? explode(',', $row['color_name']) : [];
                                $firstColor = !empty($colors) ? trim($colors[0]) : '';
                                ?>

                                <div class="group relative bg-white rounded-lg overflow-hidden shadow hover:shadow-xl transition-all duration-300">

                                    <!-- Product Image -->
                                    <div class="relative overflow-hidden bg-gray-50" style="height: 180px;">
                                        <a href="index-product_view-page-4-AA.php?id=<?= $product_id ?>" class="block">
                                            <?php if (!empty($row['main_image'])): ?>
                                                <img src="../../<?= htmlspecialchars($row['main_image']) ?>"
                                                     alt="<?= htmlspecialchars($row['product_name']) ?>"
                                                     class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500"
                                                     loading="lazy"
                                                     onerror="this.src='../../uploads/placeholder.jpg'">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-image text-gray-300 text-4xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </a>

                                        <?php if ($discount > 0): ?>
                                            <div class="absolute top-2 right-2 bg-red-600 text-white px-2 py-1 rounded-full text-xs font-bold">
                                                -<?= number_format($discount, 0) ?>%
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Details -->
                                    <div class="p-3">
                                        <a href="index-product_view-page-4-AA.php?id=<?= $product_id ?>">
                                            <h3 class="text-sm font-medium  mb-1 line-clamp-1 group-hover:text-orange-600 transition-colors">
                                                <?= htmlspecialchars($row['product_name']) ?>
                                            </h3>
                                            <?php if (!empty($row['description'])): ?>
                                                <p class="text-xs  mb-2 line-clamp-2">
                                                    <?= htmlspecialchars($row['description']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </a>

                                        <!-- ⭐ RATING DISPLAY -->
                                        <div class="mb-2">
                                            <?php if ($total_raters > 0): ?>
                                                <div class="flex items-center gap-2">
                                                    <div class="flex  text-xs">
                                                        <?php
                                                        for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                                        if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                                        for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                                                        ?>
                                                    </div>
                                                    <span class="text-xs  font-medium"><?= $avg_rating ?></span>
                                                    <span class="text-xs ">(<?= $total_raters ?>)</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center gap-1">
                                                    <div class="flex  text-xs">
                                                        <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                    </div>
                                                    <span class="text-xs 0">No rating</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- 🔥 SMART PRICE RANGE DISPLAY -->
                                        <div class="flex items-baseline gap-2 flex-wrap mb-2">
                                            <p class="text-base font-bold ">
                                                <?= $priceData['display_price'] ?>
                                            </p>
                                            <?php if ($discount > 0): ?>
                                                <span class="text-xs font-semibold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">
                                                    -<?= number_format($discount, 0) ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- View Count + Sold Count -->
                                        <?php if ($view_count > 0 || $total_sold > 0): ?>
                                            <div class="text-xs mb-2 flex items-center gap-2">
                                                <?php if ($view_count > 0): ?>
                                                    <span class="flex items-center gap-1">
                                                     
                                                        <?= formatViewCount($view_count) ?> viewing
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($total_sold > 0): ?>
                                                    <?php if ($view_count > 0): ?>
                                                        <span class="text-gray-300">|</span>
                                                    <?php endif; ?>
                                                    <span class="flex items-center gap-1">
                                                        <i class="fas fa-shopping-bag "></i>
                                                        <?= number_format($total_sold) ?> sold
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Button -->
                                        <a href="index-product_view-page-4-AA.php?id=<?= $product_id ?>"
                                           class="mt-2 block w-full bg-black text-white py-2 rounded text-sm font-semibold text-center hover:bg-orange-600 transition-colors">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Empty Collection State -->
                        <div class="bg-gray-50 rounded-lg p-12 text-center">
                            <div class="w-20 h-20 mx-auto mb-4 bg-gray-200 rounded-full flex items-center justify-center">
                                <i class="fas fa-box-open text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Products Yet</h3>
                            <p class="text-gray-500 text-sm">
                                This collection doesn't have any products assigned yet.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
        <?php else: ?>
            <!-- No Collections -->
            <div class="text-center py-20 bg-white rounded-lg shadow-sm">
                <div class="w-32 h-32 mx-auto mb-6 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-boxes text-6xl text-orange-400"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mb-3">No Collections Available</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto text-lg">
                    There are no product collections in this category yet.
                </p>
                <a href="allproduct-allproductsub_variant-page-3-A.php?subcategory_id=<?= $subcategory_id ?>"
                   class="inline-flex items-center px-6 py-3 bg-black text-white font-semibold rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fas fa-shopping-bag mr-2"></i>
                    Browse All Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../navbar/footer.php'; ?>

    <style>
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</body>
</html>