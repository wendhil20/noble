<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token 
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
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

// ✅ Session check 
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Get subcategory_id from URL parameter
$subcategory_id = isset($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : 0;

// Initialize variables
$subcategory_name = "All Products";
$category_name = "";
$category_id = 0;

// Get subcategory and category information
if ($subcategory_id > 0) {
    $stmt = $conn->prepare("
        SELECT ps.subcategory_name, ps.category_id, c.name as category_name 
        FROM product_subcategories ps 
        LEFT JOIN categories c ON ps.category_id = c.id 
        WHERE ps.id = ?
    ");
    $stmt->bind_param("i", $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $subcategory_data = $result->fetch_assoc();
        $subcategory_name = $subcategory_data['subcategory_name'];
        $category_name = $subcategory_data['category_name'];
        $category_id = $subcategory_data['category_id'];
    } else {
        // If subcategory not found, redirect to categories page
        header('Location: subcategories.php');
        exit;
    }
    $stmt->close();
}

// Build the product query - ONLY for the selected subcategory
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
        pv.subcategory_id,
        pv.category_name,
        pv.subcategory_name,
        pc.product_id as pc_product_id,
        pc.color_name
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON pv.product_id = pc.product_id
    WHERE pv.subcategory_id = ?
    ORDER BY p.id DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subcategory_id);
$stmt->execute();
$result = $stmt->get_result();

// Group products with their variants
$products = [];
while ($row = $result->fetch_assoc()) {
    $product_id = $row['product_id'];

    if (!isset($products[$product_id])) {
        $products[$product_id] = [
            'id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'main_image' => $row['main_image'],
            'description' => $row['description'],
            'unit' => $row['unit'],
            'specification' => $row['specification'],
            'category_name' => $row['category_name'],
            'category_id' => $row['category_id'],
            'subcategory_name' => $row['subcategory_name'],
            'subcategory_id' => $row['subcategory_id'],
            'variants' => [],
            'colors' => []
        ];
    }

    // Add variant if it exists
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

    // Add color if it exists
    if ($row['color_name']) {
        if (!in_array($row['color_name'], $products[$product_id]['colors'])) {
            $products[$product_id]['colors'][] = $row['color_name'];
        }
    }
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subcategory_name); ?> - Products</title>
    <!-- Add your CSS links here -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Header Section -->
    <div class="bg-black text-white">
        <div class="container mx-auto px-6 py-12">
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">
                    <?php echo htmlspecialchars($subcategory_name); ?>
                </h1>
                <p class="text-xl text-blue-100 mb-6">
                    Discover our premium collection
                </p>
                
                <!-- Breadcrumb -->
                <nav class="flex justify-center" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-2 text-sm">
                        <li>
                            <a href="index.php" class="text-blue-200 hover:text-white">Home</a>
                        </li>
                        <li><i class="fas fa-chevron-right text-blue-300 mx-2"></i></li>
                        <li>
                            <a href="allproductsub.php?category_id=<?php echo $category_id; ?>" 
                               class="text-blue-200 hover:text-white"><?php echo htmlspecialchars($category_name); ?></a>
                        </li>
                        <li><i class="fas fa-chevron-right text-blue-300 mx-2"></i></li>
                        <li class="text-blue-100"><?php echo htmlspecialchars($subcategory_name); ?></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-12">
        
        <!-- Product Count and Filter Info -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900 mb-2">
                            <?php echo htmlspecialchars($subcategory_name); ?> Products
                        </h2>
                    </div>
                    
                    <!-- Back to Categories Button -->
                    <a href="allproductsub.php?category_id=<?php echo $category_id; ?>" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to <?php echo htmlspecialchars($category_name); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group">
                        <!-- Product Image -->
                        <div class="aspect-square overflow-hidden bg-gray-50">
                            <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                 class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                                 onerror="this.src='../../uploads/placeholder.jpg'">
                        </div>

                        <!-- Product Details -->
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 mb-2 text-sm uppercase tracking-wide">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </h3>

                            <p class="text-gray-600 text-xs mb-3 line-clamp-2">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?>
                            </p>

                            <!-- Variant Information -->
                            <?php if (!empty($product['variants'])): ?>
                                <?php $firstVariant = $product['variants'][0]; ?>
                                <div class="mb-3">
                                    <p class="text-orange-600 font-bold text-lg">
                                        ₱<?php echo number_format($firstVariant['price'], 2); ?>
                                        <?php if ($firstVariant['discount'] > 0): ?>
                                            <span class="text-xs text-green-600 ml-1">
                                                (-<?php echo $firstVariant['discount']; ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </p>

                                    <!-- Variant Details -->
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php if ($firstVariant['color']): ?>
                                            <span class="inline-block bg-gray-100 px-2 py-1 rounded mr-1">
                                                <?php echo htmlspecialchars($firstVariant['color']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($firstVariant['size']): ?>
                                            <span class="inline-block bg-gray-100 px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($firstVariant['size']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (count($product['variants']) > 1): ?>
                                        <p class="text-xs text-blue-600 mt-1">
                                            +<?php echo count($product['variants']) - 1; ?> more variants available
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- View Product Button -->
                            <form action="product_view.php" method="GET" class="mt-4">
                                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium">
                                    <i class="fas fa-eye mr-2"></i>
                                    View Product
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-box-open text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-2xl font-semibold text-gray-900 mb-3">No Products Found</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">
                    There are no products available in the "<?php echo htmlspecialchars($subcategory_name); ?>" subcategory at the moment.
                </p>
                <a href="allproductsub.php?category_id=<?php echo $category_id; ?>" 
                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Browse Other Subcategories
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add your footer here -->
    
    <style>
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