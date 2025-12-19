<?php
// allproduct-allproductsub_variant-page-3-A.php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

session_name("nobleuser");
session_start();

// Check if connection file exists
if (!file_exists('../../connection/connect.php')) {
    die('Database connection file not found');
}

include '../../connection/connect.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
}

// ✅ Restore session from remember_token 
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $token = $_COOKIE['remember_token'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'] ?? '';
                $_SESSION['user_mobile'] = $user['mobile'] ?? '';

                if (!empty($user['google_id'])) {
                    $_SESSION['google_logged_in'] = true;
                    $_SESSION['user_picture']     = $user['profile_picture'] ?? null;
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Session restore error: " . $e->getMessage());
    }
}

// ✅ Allow guest access
$is_guest = !isset($_SESSION['user_id']);

// Get parameters from URL
$subcategory_id = isset($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : 0;
$sub_subcategory_id = isset($_GET['sub_subcategory_id']) ? (int)$_GET['sub_subcategory_id'] : 0;
$show_sale = isset($_GET['sale']) ? (int)$_GET['sale'] : 0;

// Initialize variables
$subcategory_name = "All Products";
$sub_subcategory_name = "";
$category_name = "";
$category_id = 0;

// ✅ FIXED: Get sub-subcategory information (if filtering by sub-subcategory)
if ($sub_subcategory_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                pss.sub_subcategory_name,
                pss.subcategory_id,
                ps.subcategory_name, 
                ps.category_id, 
                c.name as category_name 
            FROM product_sub_subcategories pss
            JOIN product_subcategories ps ON pss.subcategory_id = ps.id
            LEFT JOIN categories c ON ps.category_id = c.id 
            WHERE pss.id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $sub_subcategory_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $sub_subcategory_name = $data['sub_subcategory_name'];
            $subcategory_name = $data['subcategory_name'];
            $subcategory_id = $data['subcategory_id'];
            $category_name = $data['category_name'];
            $category_id = $data['category_id'];
        } else {
            // Don't redirect, just set defaults
            $sub_subcategory_name = "Products";
            error_log("Sub-subcategory ID $sub_subcategory_id not found");
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Sub-subcategory query error: " . $e->getMessage());
        $sub_subcategory_name = "Products";
    }
}
// ✅ FIXED: Get subcategory information (if filtering by subcategory only)
elseif ($subcategory_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT ps.subcategory_name, ps.category_id, c.name as category_name 
            FROM product_subcategories ps 
            LEFT JOIN categories c ON ps.category_id = c.id 
            WHERE ps.id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $subcategory_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $subcategory_data = $result->fetch_assoc();
            $subcategory_name = $subcategory_data['subcategory_name'];
            $category_name = $subcategory_data['category_name'];
            $category_id = $subcategory_data['category_id'];
        } else {
            // Don't redirect, just set defaults
            $subcategory_name = "Products";
            error_log("Subcategory ID $subcategory_id not found");
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Subcategory query error: " . $e->getMessage());
        $subcategory_name = "Products";
    }
}

// ✅ FIXED: Build WHERE clause with better error handling
$where_conditions = [];
$params = [];
$param_types = "";

try {
    if ($sub_subcategory_id > 0) {
        // Support both single sub_subcategory_id and JSON array sub_subcategory_ids
        $where_conditions[] = "(
            pv.sub_subcategory_id = ? 
            OR pv.sub_subcategory_ids LIKE CONCAT('%\"', ?, '\"%')
            OR pv.sub_subcategory_ids LIKE CONCAT('%,', ?, ',%')
            OR pv.sub_subcategory_ids LIKE CONCAT('[', ?, ',%')
            OR pv.sub_subcategory_ids LIKE CONCAT('%,', ?, ']')
            OR pv.sub_subcategory_ids LIKE CONCAT('[', ?, ']')
        )";
        $params = array_merge($params, [$sub_subcategory_id, $sub_subcategory_id, $sub_subcategory_id, $sub_subcategory_id, $sub_subcategory_id, $sub_subcategory_id]);
        $param_types .= "iiiiii";
    } elseif ($subcategory_id > 0) {
        // Support both single subcategory_id and JSON array subcategory_name
        $stmt = $conn->prepare("SELECT subcategory_name FROM product_subcategories WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $subcategory_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $subcat_data = $result->fetch_assoc();
            $stmt->close();

            if ($subcat_data) {
                $subcat_name = $subcat_data['subcategory_name'];
                $where_conditions[] = "(
                    pv.subcategory_id = ? 
                    OR pv.subcategory_name LIKE CONCAT('%\"', ?, '\"%')
                    OR pv.subcategory_name LIKE CONCAT('%', ?, '%')
                )";
                $params = array_merge($params, [$subcategory_id, $subcat_name, $subcat_name]);
                $param_types .= "iss";
            }
        }
    }

    // Sale filter
    if (isset($_GET['sale']) && intval($_GET['sale']) === 1) {
        $where_conditions[] = "pv.discount > 0";
    }
} catch (Exception $e) {
    error_log("WHERE clause building error: " . $e->getMessage());
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ✅ FIXED: Main query with comprehensive error handling
$query = "
    SELECT 
        p.id as product_id,
        p.product_name,
        p.main_image,
        p.description,
        p.unit,
        p.specification,
        pv.id as variant_id,
        pv.type_id,
        pv.product_id as pv_product_id,
        pv.color,
        pv.size,
        pv.price,
        pv.discount,
        pv.namevariant,
        pv.category_id,
        pv.category_name,
        pv.subcategory_name,
        pv.subcategory_id,
        pv.sub_subcategory_ids,
        ps.subcategory_name as ps_subcategory_name,
        c.name AS c_category_name
    FROM products p
    INNER JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_subcategories ps ON pv.subcategory_id = ps.id
    LEFT JOIN categories c ON pv.category_id = c.id
    $where_clause
    ORDER BY " . ($show_sale == 1 ? "pv.discount DESC," : "") . " p.id DESC
";

$products = [];

try {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    // Group products with their variants
    while ($row = $result->fetch_assoc()) {
        $product_id = $row['product_id'];

        if (!isset($products[$product_id])) {
            $subcategory_display = 'N/A';
            if (!empty($row['subcategory_name'])) {
                $decoded_subcat = json_decode($row['subcategory_name'], true);
                if (is_array($decoded_subcat)) {
                    $subcategory_display = implode(', ', $decoded_subcat);
                } else {
                    $subcategory_display = $row['subcategory_name'];
                }
            }

            $products[$product_id] = [
                'id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'main_image' => $row['main_image'],
                'description' => $row['description'],
                'unit' => $row['unit'],
                'specification' => $row['specification'],
                'category_name' => $row['category_name'] ?? $row['c_category_name'],
                'category_id' => $row['category_id'],
                'subcategory_name' => $subcategory_display,
                'subcategory_id' => $row['subcategory_id'],
                'variants' => [],
                'colors' => [],
                'color_prices' => []
            ];
        }

        if ($row['variant_id']) {
            $products[$product_id]['variants'][] = [
                'id' => $row['variant_id'],
                'type_id' => $row['type_id'],
                'namevariant' => $row['namevariant'],
                'color' => $row['color'],
                'size' => $row['size'],
                'price' => $row['price'],
                'discount' => $row['discount']
            ];
        }
    }
    $stmt->close();

    // ✅ FIXED: Try to fetch color prices (with table existence check)
    foreach ($products as $product_id => &$product) {
        try {
            // Check if product_colors table exists first
            $table_check = $conn->query("SHOW TABLES LIKE 'product_colors'");
            if ($table_check && $table_check->num_rows > 0) {
                $color_stmt = $conn->prepare("SELECT color_name, price FROM product_colors WHERE product_id = ?");
                if ($color_stmt) {
                    $color_stmt->bind_param("i", $product_id);
                    $color_stmt->execute();
                    $color_result = $color_stmt->get_result();

                    while ($color_row = $color_result->fetch_assoc()) {
                        $product['color_prices'][$color_row['color_name']] = $color_row['price'];
                        if (!in_array($color_row['color_name'], $product['colors'])) {
                            $product['colors'][] = $color_row['color_name'];
                        }
                    }
                    $color_stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("Color prices error for product $product_id: " . $e->getMessage());
            // Continue without color prices
        }
    }
    unset($product);

} catch (Exception $e) {
    error_log("Main query execution error: " . $e->getMessage());
    error_log("Query: " . $query);
    error_log("Params: " . print_r($params, true));
    $products = [];
}

// Split products
$displayedProducts = array_slice($products, 0, 3, true);
$remainingProducts = array_slice($products, 3, null, true);

$page_label = ($show_sale == 1) ? 'SALE ITEMS' : 'ALL PRODUCTS';
$filter_description = ($show_sale == 1) ? 'discounted' : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sub_subcategory_name ?: $subcategory_name); ?> - <?php echo $page_label; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
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

        #productSidebar {
            scrollbar-width: thin;
            scrollbar-color: #e5e7eb #ffffff;
        }

        #productSidebar::-webkit-scrollbar {
            width: 8px;
        }

        #productSidebar::-webkit-scrollbar-track {
            background: #ffffff;
        }

        #productSidebar::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 4px;
        }

        #productSidebar::-webkit-scrollbar-thumb:hover {
            background-color: #9ca3af;
        }
    </style>
</head>

<body class="min-h-screen " style="font-family: 'Montserrat', sans-serif; color: #2f1200">
    <?php 
    // Safe include with error handling
    $navbar_path = '../navbar/top.php';
    if (file_exists($navbar_path)) {
        include $navbar_path;
    }
    ?>

    <!-- Header Section -->
    <div class="bg-black text-white">
        <div class="container mx-auto px-3 py-5">
            <div class="text-center">
                <h1 class="text-4xl mb-4 uppercase font-bold text-start">
                    <?php echo htmlspecialchars($sub_subcategory_name ?: $subcategory_name); ?>
                </h1>
    
                <!-- Breadcrumb -->
                <nav class="flex justify-start" aria-label="Breadcrumb">
                    <ol class="inline-flex items-start space-x-2 text-sm">
                        <?php if ($category_name): ?>
                            <li>
                                <a href="index-subcategory_grid_page-14.php?category_name=<?php echo urlencode(strtolower($category_name)); ?>"
                                    class="text-white hover:text-gray-300 uppercase">
                                    <?php echo htmlspecialchars($category_name); ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ($sub_subcategory_name): ?>
                            <li><i class="fas fa-chevron-right text-white mx-2"></i></li>
                            <li class="text-gray-100 uppercase"><?php echo htmlspecialchars($sub_subcategory_name); ?></li>
                        <?php else: ?>
                            <li><i class="fas fa-chevron-right text-red-300 mx-2"></i></li>
                            <li class="text-white uppercase"><?php echo htmlspecialchars($subcategory_name); ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-3">
        <!-- Product Count -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <?php if ($show_sale): ?>
                                <span class="bg-red-600 text-white text-xs px-3 py-1 rounded-full font-semibold">
                                    <i class="fas fa-tag mr-1"></i>SALE
                                </span>
                            <?php endif; ?>
                            <h2 class="text-2xl text-gray-900 uppercase font-bold">
                                <?php echo htmlspecialchars($sub_subcategory_name ?: $subcategory_name); ?>
                            </h2>
                        </div>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-box-open mr-1"></i>
                            <span class="font-semibold"><?php echo count($products); ?></span>
                            <?php echo $filter_description; ?>
                            product<?php echo count($products) != 1 ? 's' : ''; ?> available
                            <?php if ($show_sale != 1): ?>
                                <span class="text-gray-500">(including sale items)</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Toggle Filter Button -->
                    <div class="flex gap-2 flex-wrap">
                        <?php if ($show_sale == 1): ?>
                            <a href="?<?php echo $sub_subcategory_id > 0 ? 'sub_subcategory_id=' . $sub_subcategory_id : 'subcategory_id=' . $subcategory_id; ?>"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 hover:bg-gray-300 rounded-lg transition-colors uppercase text-sm font-semibold">
                                <i class="fas fa-list mr-2"></i>
                                Show All Products
                            </a>
                        <?php else: ?>
                            <a href="?<?php echo $sub_subcategory_id > 0 ? 'sub_subcategory_id=' . $sub_subcategory_id : 'subcategory_id=' . $subcategory_id; ?>&sale=1"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg transition-colors uppercase text-sm font-semibold">
                                <i class="fas fa-tag mr-2"></i>
                                Show Sale Items Only
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <?php foreach ($displayedProducts as $product): ?>
                    <?php
                    // Get first variant for display
                    $firstVariant = !empty($product['variants']) ? $product['variants'][0] : null;
                    
                    if ($firstVariant) {
                        $variantPrice = $firstVariant['price'];
                        $colorPrice = 0;

                        if (!empty($firstVariant['color']) && isset($product['color_prices'][$firstVariant['color']])) {
                            $colorPrice = $product['color_prices'][$firstVariant['color']];
                        } elseif (!empty($product['color_prices'])) {
                            $colorPrice = reset($product['color_prices']);
                        }

                        $totalPrice = $variantPrice + $colorPrice;
                        $discountedPrice = $totalPrice;
                        $originalPrice = $totalPrice;
                        
                        if ($firstVariant['discount'] > 0) {
                            $discountedPrice = $totalPrice * (1 - ($firstVariant['discount'] / 100));
                        }
                    }
                    ?>
                    
                    <div class="group relative bg-white rounded-lg overflow-hidden transition-all duration-300">
                        <!-- Sale Badge -->
                        <?php if ($show_sale && $firstVariant && $firstVariant['discount'] > 0): ?>
                            <div class="absolute top-2 left-2 z-20 bg-red-600 text-white px-2 py-0.5 rounded-full text-[10px] font-bold shadow">
                                <?php echo $firstVariant['discount']; ?>% OFF
                            </div>
                        <?php endif; ?>

                        <!-- Product Image -->
                        <div class="relative overflow-hidden bg-gray-50" style="height: 180px;">
                            <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500"
                                loading="lazy"
                                onerror="this.src='../../uploads/placeholder.jpg'">
                        </div>

                        <!-- Product Details -->
                        <div class="p-3">
                            <h3 class="text-sm font-medium text-gray-900 mb-2 line-clamp-2 leading-relaxed">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                                <?php if ($firstVariant): ?>
                                    <?php if ($firstVariant['size']): ?>
                                        <span class="text-gray-600"> [<?php echo htmlspecialchars($firstVariant['size']); ?>]</span>
                                    <?php endif; ?>
                                    <?php if ($firstVariant['color']): ?>
                                        <span class="text-gray-600"> [<?php echo htmlspecialchars($firstVariant['color']); ?>]</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h3>

                            <p class="text-[14px] text-gray-500 mb-2 line-clamp-1">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?>
                            </p>

                            <?php if ($firstVariant): ?>
                                <div class="mb-2">
                                    <?php if ($show_sale && $firstVariant['discount'] > 0): ?>
                                        <div class="flex items-baseline gap-1.5 mb-0.5">
                                            <p class="text-sm font-bold text-red-600">
                                                ₱<?php echo number_format($discountedPrice, 2); ?>
                                            </p>
                                            <p class="text-[10px] text-gray-400 line-through">
                                                ₱<?php echo number_format($originalPrice, 2); ?>
                                            </p>
                                            <span class="text-[9px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">
                                                -<?php echo $firstVariant['discount']; ?>%
                                            </span>
                                        </div>
                                        <p class="text-[9px] text-green-600 font-medium">
                                            Save ₱<?php echo number_format($originalPrice - $discountedPrice, 2); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-sm font-bold text-gray-900">
                                            ₱<?php echo number_format($totalPrice, 2); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <?php
                                // Try to get view/sold counts (optional)
                                $view_count = 0;
                                $total_sold = 0;
                                
                                try {
                                    $view_check = $conn->query("SHOW COLUMNS FROM products LIKE 'view_count'");
                                    if ($view_check && $view_check->num_rows > 0) {
                                        $view_stmt = $conn->prepare("SELECT view_count FROM products WHERE id = ?");
                                        if ($view_stmt) {
                                            $view_stmt->bind_param("i", $product['id']);
                                            $view_stmt->execute();
                                            $view_result = $view_stmt->get_result()->fetch_assoc();
                                            $view_count = (int)($view_result['view_count'] ?? 0);
                                            $view_stmt->close();
                                        }
                                    }

                                    $sold_check = $conn->query("SHOW TABLES LIKE 'sold_items'");
                                    if ($sold_check && $sold_check->num_rows > 0) {
                                        $sold_stmt = $conn->prepare("SELECT SUM(quantity) as total_sold FROM sold_items WHERE product_id = ?");
                                        if ($sold_stmt) {
                                            $sold_stmt->bind_param("i", $product['id']);
                                            $sold_stmt->execute();
                                            $sold_result = $sold_stmt->get_result()->fetch_assoc();
                                            $total_sold = (int)($sold_result['total_sold'] ?? 0);
                                            $sold_stmt->close();
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Silently continue
                                }
                                ?>

                                <?php if ($view_count > 0 || $total_sold > 0): ?>
                                    <div class="text-[9px] text-gray-500 font-medium mb-2">
                                        <?php if ($view_count > 0): ?>
                                            <?php echo number_format($view_count); ?> viewing
                                        <?php endif; ?>
                                        <?php if ($view_count > 0 && $total_sold > 0): ?> | <?php endif; ?>
                                        <?php if ($total_sold > 0): ?>
                                            <?php echo number_format($total_sold); ?> sold
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <form action="index-product_view-page-4-AA.php" method="GET">
                                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="w-full bg-black text-white py-1.5 rounded text-[11px] uppercase hover:bg-gray-700 transition-colors">
                                    VIEW DETAILS
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Load More Card -->
                <?php if (!empty($remainingProducts)): ?>
                    <div class="bg-gradient-to-br from-gray-900 to-black rounded-lg overflow-hidden hover:shadow-2xl transition-all duration-300 flex items-center justify-center p-6 cursor-pointer group relative" id="loadMoreCard">
                        <div class="absolute inset-0 bg-gradient-to-br from-<?php echo $show_sale ? 'red' : 'blue'; ?>-600 to-<?php echo $show_sale ? 'orange' : 'purple'; ?>-600 opacity-0 group-hover:opacity-20 transition-opacity"></div>
                        <div class="text-center relative z-10">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform shadow-lg">
                                <i class="fas fa-plus-circle text-<?php echo $show_sale ? 'red' : 'gray'; ?>-600 text-3xl"></i>
                            </div>
                            <h3 class="text-white font-bold text-xl mb-2">Load More</h3>
                            <p class="text-gray-300 text-sm mb-3">
                                <i class="fas fa-box mr-1"></i>
                                <?php echo count($remainingProducts); ?> more products
                            </p>
                            <div class="inline-flex items-center text-white text-sm font-semibold bg-white bg-opacity-10 px-4 py-2 rounded-full group-hover:bg-opacity-20 transition-all">
                                <span>View All</span>
                                <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($remainingProducts)): ?>
                <div class="text-center mt-8 md:hidden">
                    <button id="loadMoreBtnMobile" class="inline-flex items-center px-8 py-4 bg-<?php echo $show_sale ? 'red' : 'gray'; ?>-600 text-white font-bold rounded-lg hover:bg-<?php echo $show_sale ? 'red' : 'gray'; ?>-700 transition-colors shadow-lg">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Load More Products (<?php echo count($remainingProducts); ?>)
                    </button>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-20 bg-white rounded-lg shadow-sm">
                <div class="w-32 h-32 mx-auto mb-6 bg-<?php echo ($show_sale == 1) ? 'red' : 'gray'; ?>-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-<?php echo ($show_sale == 1) ? 'tags' : 'box-open'; ?> text-6xl text-<?php echo ($show_sale == 1) ? 'red' : 'gray'; ?>-400"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mb-3">
                    <?php echo ($show_sale == 1) ? 'No Sale Items Found' : 'No Products Found'; ?>
                </h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto text-lg">
                    <?php if ($show_sale == 1): ?>
                        There are currently no discounted products in this <?php echo $sub_subcategory_name ? 'collection' : 'category'; ?>.
                    <?php else: ?>
                        No products available in this <?php echo $sub_subcategory_name ? 'collection' : 'category'; ?>.
                    <?php endif; ?>
                </p>
                
                <?php if ($show_sale == 1): ?>
                    <a href="?<?php echo $sub_subcategory_id > 0 ? 'sub_subcategory_id=' . $sub_subcategory_id : 'subcategory_id=' . $subcategory_id; ?>"
                        class="inline-flex items-center px-6 py-3 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors shadow-md">
                        <i class="fas fa-list mr-2"></i>
                        View All Products
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar for More Products -->
    <div id="productSidebar" class="fixed top-0 right-0 h-full w-full md:w-[28rem] bg-white transform translate-x-full transition-transform duration-300 z-50 overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-gray-900 to-black text-white border-b border-gray-700 p-5 flex justify-between items-center z-10">
            <div>
                <h3 class="text-xl font-bold">More <?php echo $show_sale ? 'Sale' : ''; ?> Products</h3>
                <p class="text-sm text-gray-300 mt-1">
                    <i class="fas fa-box mr-1"></i>
                    <?php echo count($remainingProducts); ?> items available
                </p>
            </div>
            <button id="closeSidebar" class="text-white hover:text-gray-300 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <div class="p-4 space-y-3">
            <?php foreach ($remainingProducts as $product): ?>
                <?php
                // Get first variant for sidebar display
                $firstVariant = !empty($product['variants']) ? $product['variants'][0] : null;
                
                if ($firstVariant) {
                    $variantPrice = $firstVariant['price'];
                    $colorPrice = 0;

                    if (!empty($firstVariant['color']) && isset($product['color_prices'][$firstVariant['color']])) {
                        $colorPrice = $product['color_prices'][$firstVariant['color']];
                    } elseif (!empty($product['color_prices'])) {
                        $colorPrice = reset($product['color_prices']);
                    }

                    $totalPrice = $variantPrice + $colorPrice;
                    
                    if ($firstVariant['discount'] > 0) {
                        $discountedPrice = $totalPrice * (1 - ($firstVariant['discount'] / 100));
                        $originalPrice = $totalPrice;
                    }
                }
                ?>
                
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group relative">
                    <?php if ($show_sale && $firstVariant && $firstVariant['discount'] > 0): ?>
                        <div class="absolute top-2 left-2 z-10 bg-red-600 text-white px-2 py-1 rounded-full text-xs font-bold shadow-lg">
                            <i class="fas fa-tag mr-1"></i>
                            <?php echo $firstVariant['discount']; ?>% OFF
                        </div>
                    <?php endif; ?>

                    <div class="flex gap-3 p-3">
                        <div class="w-28 h-28 flex-shrink-0 overflow-hidden bg-gray-50 rounded-lg border border-gray-100">
                            <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-300"
                                loading="lazy"
                                onerror="this.src='../../uploads/placeholder.jpg'">
                        </div>

                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-bold text-gray-900 mb-1 line-clamp-2 uppercase">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </h4>

                            <p class="text-black line-clamp-2 text-xs mb-2">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 60)) . '...'; ?>
                            </p>

                            <?php if ($firstVariant): ?>
                                <div class="mb-2">
                                    <?php if ($show_sale && $firstVariant['discount'] > 0): ?>
                                        <div class="flex items-baseline gap-2 mb-1">
                                            <p class="text-red-600 font-bold text-base">
                                                ₱<?php echo number_format($discountedPrice, 2); ?>
                                            </p>
                                            <p class="text-gray-400 text-xs line-through">
                                                ₱<?php echo number_format($originalPrice, 2); ?>
                                            </p>
                                        </div>
                                        <p class="text-xs text-green-600 font-semibold">
                                            <i class="fas fa-piggy-bank mr-1"></i>
                                            Save ₱<?php echo number_format($originalPrice - $discountedPrice, 2); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-black font-bold text-base">
                                            ₱<?php echo number_format($totalPrice, 2); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form action="index-product_view-page-4-AA.php" method="GET">
                                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="w-full bg-black text-white py-2 px-3 rounded-lg hover:bg-<?php echo $show_sale ? 'red' : 'gray'; ?>-700 transition-colors duration-200 text-xs font-bold uppercase">
                                    <i class="fa-solid fa-shopping-cart mr-1"></i>
                                    View Product
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity"></div>

    <?php 
    // Safe include footer
    $footer_path = '../navbar/footer.php';
    if (file_exists($footer_path)) {
        include $footer_path;
    }
    ?>

    <script>
        const loadMoreCard = document.getElementById('loadMoreCard');
        const loadMoreBtnMobile = document.getElementById('loadMoreBtnMobile');
        const sidebar = document.getElementById('productSidebar');
        const closeSidebarBtn = document.getElementById('closeSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            if (sidebar && overlay) {
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }

        if (loadMoreCard) {
            loadMoreCard.addEventListener('click', openSidebar);
        }

        if (loadMoreBtnMobile) {
            loadMoreBtnMobile.addEventListener('click', openSidebar);
        }

        function closeSidebar() {
            if (sidebar && overlay) {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }

        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', closeSidebar);
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });
    </script>
</body>
</html>