<?php
// index-subcategory_grid_page-14.php
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
        $_SESSION = array_merge($_SESSION, [
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email'] ?? '',
            'user_mobile' => $user['mobile'] ?? ''
        ]);

        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

$is_guest = !isset($_SESSION['user_id']);

// Get category from URL
$category_name = isset($_GET['category_name']) ? $_GET['category_name'] : '';

if (empty($category_name)) {
    header('Location: index-shop-page-2.php');
    exit;
}

// Get category info BY NAME
$cat_stmt = $conn->prepare("SELECT id, name, image_path FROM categories WHERE name = ?");
$cat_stmt->bind_param("s", $category_name);
$cat_stmt->execute();
$category_result = $cat_stmt->get_result();

if ($category_result->num_rows === 0) {
    header('Location: index-shop-page-2.php');
    exit;
}

$category = $category_result->fetch_assoc();
$category_id = $category['id'];
$cat_stmt->close();

// Format display name
$categoryName = $category['name'];
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

// Get subcategories - ALPHABETICALLY ORDERED
$sub_query = "
    SELECT 
        ps.id,
        ps.subcategory_name,
        ps.subcategory_slug,
        ps.image_path,
        (SELECT COUNT(DISTINCT pv.product_id)
         FROM product_variants pv
         WHERE pv.category_id = ?
         AND (
           pv.subcategory_name LIKE CONCAT('%\"', ps.subcategory_name, '\"%')
           OR pv.subcategory_name LIKE CONCAT('%', ps.subcategory_name, '%')
           OR pv.subcategory_name = ps.subcategory_name
         )
        ) as product_count
    FROM product_subcategories ps
    WHERE ps.category_id = ?
    ORDER BY ps.subcategory_name ASC
";

$sub_stmt = $conn->prepare($sub_query);
$sub_stmt->bind_param("ii", $category_id, $category_id);
$sub_stmt->execute();
$subcategories = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sub_stmt->close();

// Get sub-subcategories (collections) - ALPHABETICALLY ORDERED
foreach ($subcategories as &$sub) {
    $subsub_query = "
        SELECT 
            pss.id,
            pss.sub_subcategory_name,
            pss.sub_subcategory_slug,
            pss.image_path,
            (SELECT COUNT(DISTINCT pv.product_id)
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
            ) as collection_product_count
        FROM product_sub_subcategories pss
        WHERE pss.subcategory_id = ?
        ORDER BY pss.sub_subcategory_name ASC
    ";

    $subsub_stmt = $conn->prepare($subsub_query);
    $subsub_stmt->bind_param("ii", $category_id, $sub['id']);
    $subsub_stmt->execute();
    $sub['collections'] = $subsub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subsub_stmt->close();
}
unset($sub);

// Get FIRST 4 subcategories with their FIRST collection for display at bottom
$featured_subcategories = [];
$count = 0;
foreach ($subcategories as $sub) {
    if ($count >= 4) break; // Only get 4 subcategories
    
    if (!empty($sub['collections'])) {
        // Get only the FIRST collection
        $first_collection = $sub['collections'][0];
        
   $products_query = "
    SELECT DISTINCT
        p.id,
        p.product_name,
        p.main_image,
        p.view_count,
      MIN(pv.id) as variant_id,
MIN(pv.price) as price,
MIN(pv.discount) as discount,
MIN(pv.size) as size,
MIN(pv.color) as color,
        GROUP_CONCAT(DISTINCT pc.color_name) as color_name
    FROM products p
    INNER JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
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
    ORDER BY p.product_name ASC
    LIMIT 8
";
        
        $products_stmt = $conn->prepare($products_query);
        $products_stmt->bind_param("iiiiiii", 
            $category_id, 
            $first_collection['id'], 
            $first_collection['id'], 
            $first_collection['id'], 
            $first_collection['id'], 
            $first_collection['id'], 
            $first_collection['id']
        );
        $products_stmt->execute();
        $first_collection['products'] = $products_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $products_stmt->close();
        
        // Add to featured list
        $featured_subcategories[] = [
            'subcategory' => $sub,
            'featured_collection' => $first_collection
        ];
        
        $count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($displayName) ?> - Categories</title>
    <style>
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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

<body class="bg-gray-50" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
    <?php 
    include '../navbar/top.php'; 
    
    // Helper functions
    function formatViewCount($count) {
        if ($count >= 1000) {
            return number_format($count / 1000, 1) . 'k';
        }
        return number_format($count);
    }
    ?>
<?php include 'push-notification.php'; ?>
    <!-- Breadcrumb -->
    <div class="bg-white border-b">
        <div class="container mx-auto px-6 py-4">
            <nav class="flex items-center text-sm text-gray-600">
                <a href="index-page-1-A-B-C-D-E.php" class="hover:text-gray-900">Home</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <span class="text-gray-900 font-medium"><?= htmlspecialchars($displayName) ?></span>
            </nav>
        </div>
    </div>

    <!-- Header -->
    <div class="bg-white">
        <div class="container mx-auto px-6 py-12 text-start">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 uppercase">
                <?= htmlspecialchars($displayName) ?>
            </h1>
            <p class="text-gray-600 text-lg">
                Explore our collection of <?= htmlspecialchars(strtolower($displayName)) ?>
            </p>
        </div>
    </div>

    <!-- ORIGINAL: Subcategories Grid with Collections Below -->
    <div class="container mx-auto px-2 py-7">
        <?php if (!empty($subcategories)): ?>
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8">
                <?php foreach ($subcategories as $sub): ?>
                    <div class="space-y-4">
                        <!-- Subcategory Card -->
                        <div class="bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300">

                            <!-- Image Container -->
                            <a href="index-subcategory-recommendations-page-15.php?subcategory_id=<?= $sub['id'] ?>&from=grid"
                                class="block relative bg-gray-50 overflow-hidden group" style="height: 200px;">

                                <!-- Visual indicator -->
                                <div class="absolute top-2 left-2 bg-black text-white px-2 py-1 rounded-md text-xs  z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                                    Recommended
                                </div>

                                <?php if (!empty($sub['image_path'])): ?>
                                    <img src="../../uploads/<?= htmlspecialchars($sub['subcategory_slug']) ?>/<?= htmlspecialchars($sub['image_path']) ?>"
                                        alt="<?= htmlspecialchars($sub['subcategory_name']) ?>"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        loading="lazy"
                                        onerror="this.onerror=null; this.src='../../uploads/placeholder.jpg';">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-image text-gray-300 text-5xl"></i>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <!-- Title -->
                            <div class="p-4">
                                <a href="index-subcategory-recommendations-page-15.php?subcategory_id=<?= $sub['id'] ?>&from=grid">
                                    <h3 class="text-base font-semibold hover:text-blue-600 transition-colors uppercase">
                                        <?= htmlspecialchars($sub['subcategory_name']) ?>
                                    </h3>
                                </a>
                            </div>
                        </div>

                        <!-- Collections List (Alphabetically Ordered) -->
                        <?php if (!empty($sub['collections'])): ?>
                            <div class="pl-2">
                                <ul class="space-y-1.5 text-sm">
                                    <?php foreach ($sub['collections'] as $collection): ?>
                                        <li>
                                            <a href="allproduct-allproductsub_variant-page-3-A.php?sub_subcategory_id=<?= $collection['id'] ?>"
                                                class=" hover:text-blue-600 hover:underline transition-colors flex items-start uppercase">
                                                <span><?= htmlspecialchars($collection['sub_subcategory_name']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-20">
                <div class="w-32 h-32 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-box-open text-6xl text-gray-400"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">No Categories Found</h3>
                <p class="text-gray-600 mb-6">
                    We couldn't find any categories for <?= htmlspecialchars($displayName) ?>.
                </p>
                <a href="index-shop-page-2.php" class="inline-flex items-center px-6 py-3 bg-black text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Shop
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- NEW: Products Display Section (One Collection per Subcategory) -->
    <?php if (!empty($featured_subcategories)): ?>
        <div class="bg-gray-100 py-16">
            <div class="container mx-auto px-6">
                
                <?php foreach ($featured_subcategories as $featured): ?>
                    <?php 
                    $subcategory = $featured['subcategory'];
                    $collection = $featured['featured_collection'];
                    ?>
                    
                    <div class="mb-16">
                

                        <!-- Collection Title -->
                        <div class="mb-6">
                            <a href="allproduct-allproductsub_variant-page-3-A.php?sub_subcategory_id=<?= $collection['id'] ?>"
                               class="inline-flex items-center group">
                                <h3 class="text-2xl font-semibold  group-hover:text-blue-600 transition-colors uppercase">
                                    <?= htmlspecialchars($collection['sub_subcategory_name']) ?>
                                </h3>
                                <i class="fas fa-arrow-right ml-3 text-lg opacity-0 group-hover:opacity-100 transition-opacity"></i>
                            </a>
                        
                        </div>

                        <!-- Products Grid -->
                        <?php if (!empty($collection['products'])): ?>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4 mb-6">
                                <?php foreach ($collection['products'] as $product): ?>
                                    <?php
                                    $product_id = (int)$product['id'];
                                    $discount = (float)($product['discount'] ?? 0);
                                    $view_count = (int)($product['view_count'] ?? 0);
                                    
                                    // Get rating
                                    $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
                                    $rating_q->bind_param("i", $product_id);
                                    $rating_q->execute();
                                    $rating_result = $rating_q->get_result()->fetch_assoc();
                                    $avg_rating = $rating_result['avg_rating'] ?? 0;
                                    $total_raters = $rating_result['total_raters'] ?? 0;
                                    $rating_q->close();
                                    
                                    // Get sold count
                                    $sold_q = $conn->prepare("SELECT SUM(quantity) as total_sold FROM sold_items WHERE product_id = ?");
                                    $sold_q->bind_param("i", $product_id);
                                    $sold_q->execute();
                                    $sold_result = $sold_q->get_result()->fetch_assoc();
                                    $total_sold = (int)($sold_result['total_sold'] ?? 0);
                                    $sold_q->close();
                                    
                                    $full = floor($avg_rating);
                                    $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                                    $empty = 5 - $full - $half;
                                    
                                    $colors = !empty($product['color_name']) ? explode(',', $product['color_name']) : [];
                                    $firstColor = !empty($colors) ? trim($colors[0]) : '';
                                    ?>
                                    
                                    <a href="index-product_view-page-4-AA.php?id=<?= $product['id'] ?>" 
                                       class="product-card bg-white rounded-lg overflow-hidden shadow-sm">
                                        <!-- Product Image -->
                                        <div class="relative bg-gray-50 aspect-square">
                                            <?php if (!empty($product['main_image'])): ?>
                                                <img src="../../<?= htmlspecialchars($product['main_image']) ?>"
                                                    alt="<?= htmlspecialchars($product['product_name']) ?>"
                                                    class="w-full h-full object-contain p-1.5"
                                                    loading="lazy"
                                                    onerror="this.onerror=null; this.src='../../uploads/placeholder.jpg';">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-image text-gray-300 text-3xl"></i>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Discount Badge -->
                                            <?php if ($discount > 0): ?>
                                                <div class="absolute top-2 right-2 bg-red-600 text-white px-2 py-1 rounded-md text-xs font-bold">
                                                    -<?= number_format($discount, 0) ?>%
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Product Info -->
                                        <div class="p-3">
                                            <!-- Product Name with Size & Color -->
                                            <h4 class="text-sm font-medium mb-2 line-clamp-2 hover:text-blue-600 transition-colors">
                                                <?= htmlspecialchars($product['product_name']) ?>
                                                <?php if (!empty($product['size'])): ?>
                                                    <span class="">[<?= htmlspecialchars($product['size']) ?>]</span>
                                                <?php endif; ?>
                                                <?php if ($firstColor): ?>
                                                    <span class="">[<?= htmlspecialchars($firstColor) ?>]</span>
                                                <?php endif; ?>
                                            </h4>
                                            
                                            <!-- Rating -->
                                            <div class="flex items-center gap-1 mb-2">
                                                <?php if ($total_raters > 0): ?>
                                                    <div class="flex text-yellow-400 text-xs">
                                                        <?php for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>'; ?>
                                                        <?php if ($half) echo '<i class="fas fa-star-half-alt"></i>'; ?>
                                                        <?php for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>'; ?>
                                                    </div>
                                                    <span class="text-xs text-gray-500">(<?= $total_raters ?>)</span>
                                                <?php else: ?>
                                                    <div class="flex text-gray-300 text-xs">
                                                        <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                                                    </div>
                                                    <span class="text-xs text-gray-400">No rating</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Price -->
                                            <div class="flex items-center gap-2 mb-2">
                                                <?php if ($discount > 0): ?>
                                                    <?php 
                                                    $discounted_price = $product['price'] * (1 - ($discount / 100));
                                                    ?>
                                                    <span class="text-sm font-bold ">
                                                        ₱<?= number_format($discounted_price, 2) ?>
                                                    </span>
                                                    <span class="text-xs  line-through">
                                                        ₱<?= number_format($product['price'], 2) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-sm font-bold ">
                                                        ₱<?= number_format($product['price'], 2) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- View Count & Sold Count -->
                                            <?php if ($view_count > 0 || $total_sold > 0): ?>
                                                <div class="text-xs ">
                                                    <?php if ($view_count > 0): ?>
                                                        <?= formatViewCount($view_count) ?> viewing
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($total_sold > 0): ?>
                                                        <?php if ($view_count > 0): ?> | <?php endif; ?>
                                                        <?= number_format($total_sold) ?> sold
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- View All Link -->
                            <div class="text-center">
                                <a href="allproduct-allproductsub_variant-page-3-A.php?sub_subcategory_id=<?= $collection['id'] ?>"
                                   class="inline-flex items-center px-8 py-3 bg-white border-2 border-gray-900 text-gray-900 font-semibold rounded-lg hover:bg-gray-900 hover:text-white transition-colors shadow-sm">
                                    View All <?= htmlspecialchars($collection['sub_subcategory_name']) ?>
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm italic text-center py-8">No products available in this collection</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php include '../navbar/footer.php'; ?>
</body>

</html>