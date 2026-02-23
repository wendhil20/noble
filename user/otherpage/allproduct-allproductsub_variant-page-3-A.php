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

// ✅ NO PAGINATION - All products will be displayed with JavaScript pagination
$displayedProducts = $products; // All products
$remainingProducts = []; // No sidebar

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

        <!-- Products Grid - Display all products -->
        <?php if (!empty($products)): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 md:gap-4 lg:gap-6" id="productsGrid">
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
                    
                    <div class="group relative bg-white rounded-lg overflow-hidden transition-all duration-300 product-card">
                        <!-- Sale Badge -->
                        <?php if ($show_sale && $firstVariant && $firstVariant['discount'] > 0): ?>
                            <div class="absolute top-2 left-2 z-20 bg-red-600 text-white px-2 py-0.5 rounded-full text-[10px] font-bold shadow">
                                <?php echo $firstVariant['discount']; ?>% OFF
                            </div>
                        <?php endif; ?>

                        <!-- Product Image -->
                        <div class="relative overflow-hidden bg-gray-50" style="height: 120px;min-height: 120px;">
                            <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500"
                                loading="lazy"
                                onerror="this.src='../../uploads/placeholder.jpg'">
                        </div>

                        <!-- Product Details -->
                        <div class="p-2 md:p-3">
                            <h3 class="text-xs md:text-sm font-medium text-gray-900 mb-1 md:mb-2 line-clamp-2 leading-tight md:leading-relaxed">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                                <?php if ($firstVariant): ?>
                                    <?php if ($firstVariant['size']): ?>
                                        <span class="text-gray-600 text-[10px] md:text-xs"> [<?php echo htmlspecialchars($firstVariant['size']); ?>]</span>
                                    <?php endif; ?>
                                    <?php if ($firstVariant['color']): ?>
                                        <span class="text-gray-600 text-[10px] md:text-xs"> [<?php echo htmlspecialchars($firstVariant['color']); ?>]</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h3>

                            <p class="text-[11px] md:text-[13px] text-gray-500 mb-1 md:mb-2 line-clamp-1 hidden md:block">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?>
                            </p>

                            <?php if ($firstVariant): ?>
                                <div class="mb-2">
                                    <?php if ($show_sale && $firstVariant['discount'] > 0): ?>
                                        <div class="flex items-baseline gap-1 md:gap-1.5 mb-0.5">
                                            <p class="text-xs md:text-sm font-bold text-red-600">
                                                ₱<?php echo number_format($discountedPrice, 2); ?>
                                            </p>
                                            <p class="text-[8px] md:text-[10px] text-gray-400 line-through">
                                                ₱<?php echo number_format($originalPrice, 2); ?>
                                            </p>
                                            <span class="text-[7px] md:text-[9px] font-semibold text-red-600 bg-red-50 px-0.5 md:px-1 py-0.5 rounded">
                                                -<?php echo $firstVariant['discount']; ?>%
                                            </span>
                                        </div>
                                        <p class="text-[7px] md:text-[9px] text-green-600 font-medium hidden md:block">
                                            Save ₱<?php echo number_format($originalPrice - $discountedPrice, 2); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-xs md:text-sm font-bold text-gray-900">
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
                                    <div class="text-[7px] md:text-[9px] text-gray-500 font-medium mb-2 hidden md:block">
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
                                <button type="submit" class="w-full bg-black text-white py-1 md:py-1.5 px-2 rounded text-[9px] md:text-[11px] uppercase hover:bg-gray-700 transition-colors font-semibold">
                                    VIEW
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Controls -->
            <div class="mt-12 flex justify-center items-center gap-2">
                <!-- Previous Button -->
                <button id="prevBtn" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition-colors font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-chevron-left mr-2"></i>
                    Prev
                </button>

                <!-- Page Numbers -->
                <div class="flex items-center gap-1" id="paginationNumbers">
                    <!-- Generated by JavaScript -->
                </div>

                <!-- Next Button -->
                <button id="nextBtn" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition-colors font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                   Next
                    <i class="fas fa-chevron-right ml-2"></i>
                </button>
            </div>

            <!-- Page Info -->
            <div class="mt-6 text-center">
                <p class="text-gray-600 font-medium">
                    Showing <span class="font-bold text-gray-900" id="showingCount">10</span> of 
                    <span class="font-bold text-gray-900"><?php echo count($products); ?></span> products
                    <span class="text-gray-500">(Page <span id="currentPage">1</span> of <span id="totalPages">1</span>)</span>
                </p>
            </div>

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



    <?php 
    // Safe include footer
    $footer_path = '../navbar/footer.php';
    if (file_exists($footer_path)) {
        include $footer_path;
    }
    ?>

    <script>
        // JavaScript Pagination System - 10 products per page
        const productsPerPage = 10;
        const allProductCards = document.querySelectorAll('.product-card');
        const totalProducts = allProductCards.length;
        const totalPages = Math.ceil(totalProducts / productsPerPage);
        let currentPage = 1;

        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const paginationNumbers = document.getElementById('paginationNumbers');
        const currentPageSpan = document.getElementById('currentPage');
        const totalPagesSpan = document.getElementById('totalPages');
        const showingCountSpan = document.getElementById('showingCount');

        function hideAllProducts() {
            allProductCards.forEach(card => card.style.display = 'none');
        }

        function showProductsForPage(page) {
            hideAllProducts();
            const startIndex = (page - 1) * productsPerPage;
            const endIndex = Math.min(startIndex + productsPerPage, totalProducts);
            
            for (let i = startIndex; i < endIndex; i++) {
                allProductCards[i].style.display = 'block';
            }

            // Update page info
            const productsShown = endIndex - startIndex;
            currentPageSpan.textContent = page;
            totalPagesSpan.textContent = totalPages;
            showingCountSpan.textContent = productsShown;
        }

        function updatePaginationButtons() {
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }

        function generatePaginationNumbers() {
            paginationNumbers.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                // Show first 3, last 3, and around current page
                if (i <= 3 || i >= totalPages - 2 || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = i === currentPage 
                        ? 'inline-flex items-center justify-center w-10 h-10 bg-red-600 text-white rounded-lg font-bold cursor-default'
                        : 'inline-flex items-center justify-center w-10 h-10 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors font-semibold';
                    pageBtn.textContent = i;
                    pageBtn.disabled = i === currentPage;
                    
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        showProductsForPage(currentPage);
                        updatePaginationButtons();
                        generatePaginationNumbers();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                    
                    paginationNumbers.appendChild(pageBtn);
                } else if (i === 4 && currentPage > 5) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'px-2 text-gray-600';
                    ellipsis.textContent = '...';
                    paginationNumbers.appendChild(ellipsis);
                    i = currentPage - 2;
                } else if (i === totalPages - 3 && currentPage < totalPages - 4) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'px-2 text-gray-600';
                    ellipsis.textContent = '...';
                    paginationNumbers.appendChild(ellipsis);
                    i = totalPages - 3;
                }
            }
        }

        // Event Listeners
        prevBtn?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                showProductsForPage(currentPage);
                updatePaginationButtons();
                generatePaginationNumbers();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        nextBtn?.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                showProductsForPage(currentPage);
                updatePaginationButtons();
                generatePaginationNumbers();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        // Initialize
        if (totalPages > 0) {
            showProductsForPage(1);
            generatePaginationNumbers();
            updatePaginationButtons();
        }
    </script>
</body>
</html>