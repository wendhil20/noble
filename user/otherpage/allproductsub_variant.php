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

// Build the product query - ONLY for the selected subcategory AND with discount > 0
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
        ps.category_id,
        ps.id as subcategory_id,
        c.name AS category_name,
        ps.subcategory_name,
        pc.product_id as pc_product_id,
        pc.color_name
    FROM products p
    INNER JOIN product_variants pv ON p.id = pv.product_id
    INNER JOIN product_subcategories ps ON pv.subcategory_id = ps.id
    LEFT JOIN categories c ON ps.category_id = c.id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    WHERE pv.subcategory_id = ? AND pv.discount > 0
    ORDER BY pv.discount DESC, p.id DESC
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
    <title><?php echo htmlspecialchars($subcategory_name); ?> - Sale Items</title>
    <!-- Add your CSS links here -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-50 min-h-screen font-mont">
    <?php include '../navbar/top.php'; ?>

    <!-- Header Section -->
    <div class="bg-gradient-to-r from-red-600 to-red-700 text-white">
        <div class="container mx-auto px-6 py-12">
            <div class="text-center">
                <div class="inline-block bg-white text-red-600 px-4 py-2 rounded-full text-sm font-bold mb-4">
                    🔥 SALE ITEMS
                </div>
                <h1 class="text-4xl md:text-5xl font-bold mb-4 uppercase">
                    <?php echo htmlspecialchars($subcategory_name); ?>
                </h1>
                <p class="text-xl text-red-100 mb-6">
                    Limited Time Discounts - Shop Now!
                </p>

                <!-- Breadcrumb -->
                <nav class="flex justify-center" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-2 text-sm">
                        <li>
                            <a href="index.php" class="text-red-200 hover:text-white">Home</a>
                        </li>
                        <li><i class="fas fa-chevron-right text-red-300 mx-2"></i></li>
                        <li>
                            <a href="allproductsub.php"
                                class="text-red-200 hover:text-white">Sale Categories</a>
                        </li>
                        <li><i class="fas fa-chevron-right text-red-300 mx-2"></i></li>
                        <li>
                            <a href="allproductsub.php?category_id=<?php echo $category_id; ?>"
                                class="text-red-200 hover:text-white"><?php echo htmlspecialchars($category_name); ?></a>
                        </li>
                        <li><i class="fas fa-chevron-right text-red-300 mx-2"></i></li>
                        <li class="text-red-100"><?php echo htmlspecialchars($subcategory_name); ?></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-12">

        <!-- Product Count and Filter Info -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-sm border-2 border-red-200 p-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full">SALE</span>
                            <h2 class="text-2xl font-bold text-gray-900 uppercase">
                                <?php echo htmlspecialchars($subcategory_name); ?> ON SALE
                            </h2>
                        </div>
                        <p class="text-sm text-gray-600">
                            <?php echo count($products); ?> discounted product<?php echo count($products) != 1 ? 's' : ''; ?> available
                        </p>
                    </div>

                    <!-- Back to Categories Button -->
                    <a href="allproductsub.php"
                        class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors uppercase">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Sale Categories
                    </a>
                </div>
            </div>
        </div>


        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                    <div class="bg-white rounded-xl border-2 border-gray-200 overflow-hidden hover:shadow-xl hover:border-red-400 transition-all duration-300 group relative">
                        <!-- Sale Badge -->
                        <div class="absolute top-3 left-3 z-10 bg-red-600 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg">
                            <i class="fas fa-tag mr-1"></i>
                            <?php 
                            $maxDiscount = 0;
                            foreach ($product['variants'] as $variant) {
                                if ($variant['discount'] > $maxDiscount) {
                                    $maxDiscount = $variant['discount'];
                                }
                            }
                            echo $maxDiscount . '% OFF';
                            ?>
                        </div>

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
                                    <?php 
                                    $originalPrice = $firstVariant['price'] / (1 - ($firstVariant['discount'] / 100));
                                    ?>
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="text-red-600 font-bold text-xl">
                                            ₱<?php echo number_format($firstVariant['price'], 2); ?>
                                        </p>
                                        <p class="text-gray-400 text-sm line-through">
                                            ₱<?php echo number_format($originalPrice, 2); ?>
                                        </p>
                                    </div>
                                    <p class="text-xs text-green-600 font-semibold">
                                        Save ₱<?php echo number_format($originalPrice - $firstVariant['price'], 2); ?>
                                    </p>

                                    <!-- Variant Details -->
                                    <div class="text-xs text-gray-500 mt-2">
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
                                        <p class="text-xs text-red-600 mt-2 font-semibold">
                                            +<?php echo count($product['variants']) - 1; ?> more option<?php echo count($product['variants']) > 2 ? 's' : ''; ?> on sale
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- View Product Button -->
                            <form action="product_view.php" method="GET" class="mt-4">
                                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors duration-200 font-medium">
                                    <i class="fa-solid fa-bag-shopping mr-1"></i>
                                    View Sale Item
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="w-24 h-24 mx-auto mb-6 bg-red-50 rounded-full flex items-center justify-center">
                    <i class="fas fa-tags text-4xl text-red-400"></i>
                </div>
                <h3 class="text-2xl font-semibold text-gray-900 mb-3">No Sale Items Found</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto">
                    There are currently no discounted products in the "<?php echo htmlspecialchars($subcategory_name); ?>" subcategory.
                </p>
                <a href="allproductsub.php"
                    class="inline-flex items-center px-6 py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Browse Other Sale Categories
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add your footer here -->
     <?php include '../navbar/footer.php'; ?>

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