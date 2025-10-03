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

// Split products into first 3 and remaining (so 4th spot is for Load More)
$displayedProducts = array_slice($products, 0, 3, true);
$remainingProducts = array_slice($products, 3, null, true);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subcategory_name); ?> - Sale Items</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="min-h-screen font-roboto">
    <?php include '../navbar/top.php'; ?>

    <!-- Header Section -->
    <div class="bg-black text-white">
        <div class="container mx-auto px-6 py-12">
            <div class="text-center">
                <div class="inline-block bg-white text-red-600 px-4 py-2 rounded-full text-sm mb-4">
                     SALE ITEMS
                </div>
                <h1 class="text-4xl md:text-5xl mb-4 uppercase">
                    <?php echo htmlspecialchars($subcategory_name); ?>
                </h1>
                <p class="text-xl text-white mb-6">
                  Shop Now!
                </p>

                <!-- Breadcrumb -->
                <nav class="flex justify-center" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-2 text-sm">
                        <li>
                            <a href="index.php" class="text-white hover:text-white">Home</a>
                        </li>
                        <li><i class="fas fa-chevron-right text-white mx-2"></i></li>
                        <li>
                            <a href="allproductsub.php" class="text-white hover:text-white">Sale Categories</a>
                        </li>
                        <li><i class="fas fa-chevron-right text-white mx-2"></i></li>
                        <li>
                            <a href="allproductsub.php?category_id=<?php echo $category_id; ?>"
                                class="text-white hover:text-white"><?php echo htmlspecialchars($category_name); ?></a>
                        </li>
                        <li><i class="fas fa-chevron-right text-red-300 mx-2"></i></li>
                        <li class="text-white"><?php echo htmlspecialchars($subcategory_name); ?></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-12">

        <!-- Product Count and Filter Info -->
        <div class="mb-8">
            <div class="bg-white rounded-lg p-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="bg-red-600 text-white text-xs px-3 py-1 rounded-full">SALE</span>
                            <h2 class="text-2xl text-gray-900 uppercase">
                                <?php echo htmlspecialchars($subcategory_name); ?> ON SALE
                            </h2>
                        </div>
                        <p class="text-sm text-gray-600">
                            <?php echo count($products); ?> discounted product<?php echo count($products) != 1 ? 's' : ''; ?> available
                        </p>
                    </div>

                    <!-- Back to Categories Button -->
                    <a href="allproductsub.php"
                        class="inline-flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-red-700 transition-colors uppercase">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Sale Categories
                    </a>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($displayedProducts as $product): ?>
                    <div class="bg-white rounded-lg  overflow-hidden hover:shadow-lg hover:border-red-400 transition-all duration-300 group relative">
                        <!-- Sale Badge -->
                        <div class="absolute top-2 left-2 z-10 bg-black text-white px-2 py-1 rounded-full text-xs shadow-lg">
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
                        <div class="aspect-square overflow-hidden " style="max-height: 200px;">
                            <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                                onerror="this.src='../../uploads/placeholder.jpg'">
                        </div>

                        <!-- Product Details -->
                        <div class="p-3">
                            <h3 class="text-gray-900 mb-1 text-sm uppercase tracking-wide font-semibold line-clamp-1">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </h3>

                            <p class="text-gray-600 text-xs mb-2 line-clamp-2">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 70)) . '...'; ?>
                            </p>

                            <!-- Variant Information -->
                            <?php if (!empty($product['variants'])): ?>
                                <?php $firstVariant = $product['variants'][0]; ?>
                                <div class="mb-2">
                                    <?php 
                                    $originalPrice = $firstVariant['price'] / (1 - ($firstVariant['discount'] / 100));
                                    ?>
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="text-black font-bold text-base">
                                            ₱<?php echo number_format($firstVariant['price'], 2); ?>
                                        </p>
                                        <p class="text-gray-400 text-xs line-through">
                                            ₱<?php echo number_format($originalPrice, 2); ?>
                                        </p>
                                    </div>
                                    <p class="text-xs text-green-600 font-medium">
                                        Save ₱<?php echo number_format($originalPrice - $firstVariant['price'], 2); ?>
                                    </p>

                                    <!-- Variant Details -->
                                    <div class="text-xs text-gray-500 mt-1 flex gap-1 flex-wrap">
                                        <?php if ($firstVariant['color']): ?>
                                            <span class="inline-block bg-gray-100 px-2 py-0.5 rounded">
                                                <?php echo htmlspecialchars($firstVariant['color']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($firstVariant['size']): ?>
                                            <span class="inline-block bg-gray-100 px-2 py-0.5 rounded">
                                                <?php echo htmlspecialchars($firstVariant['size']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (count($product['variants']) > 1): ?>
                                        <p class="text-xs text-red-600 mt-1 font-medium">
                                            +<?php echo count($product['variants']) - 1; ?> more option<?php echo count($product['variants']) > 2 ? 's' : ''; ?> on sale
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- View Product Button -->
                            <form action="product_view.php" method="GET" class="mt-2">
                                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="w-full bg-black text-white py-2 px-3 rounded-lg hover:bg-red-700 transition-colors duration-200 text-sm font-semibold">
                                    <i class="fa-solid fa-bag-shopping mr-1"></i>
                                    View Sale Item
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Load More Card (4th position) -->
                <?php if (!empty($remainingProducts)): ?>
                    <div class="bg-black rounded-lg overflow-hidden hover:shadow-xl transition-all duration-300 flex items-center justify-center p-6 cursor-pointer group" id="loadMoreCard">
                        <div class="text-center">
                            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <i class="fas fa-plus-circle text-red-600 text-2xl"></i>
                            </div>
                            <h3 class="text-white font-bold text-lg mb-2">Load More</h3>
                            <p class="text-red-100 text-sm mb-2"><?php echo count($remainingProducts); ?> more products</p>
                            <div class="inline-flex items-center text-white text-sm font-semibold">
                                <span>View All</span>
                                <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Mobile Load More Button (shows below grid on mobile) -->
            <?php if (!empty($remainingProducts)): ?>
                <div class="text-center mt-8 md:hidden">
                    <button id="loadMoreBtnMobile" class="inline-flex items-center px-6 py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Load More Products (<?php echo count($remainingProducts); ?>)
                    </button>
                </div>
            <?php endif; ?>

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

    <!-- Sidebar for More Products -->
    <div id="productSidebar" class="fixed top-0 right-0 h-full w-full md:w-96 bg-white shadow-2xl transform translate-x-full transition-transform duration-300 z-50 overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 p-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900">More Sale Products</h3>
            <button id="closeSidebar" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-4 space-y-3">
            <?php foreach ($remainingProducts as $product): ?>
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-all duration-300 group relative">
                    <!-- Sale Badge -->
                    <div class="absolute top-2 left-2 z-10 bg-black text-white px-2 py-1 rounded-full text-xs shadow-lg">
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

                    <div class="flex gap-3 p-3">
                        <!-- Product Image -->
                        <div class="w-24 h-24 flex-shrink-0 overflow-hidden bg-gray-50 rounded-lg">
                            <img src="../../<?php echo htmlspecialchars($product['main_image']); ?>"
                                alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                class="w-full h-full object-contain"
                                onerror="this.src='../../uploads/placeholder.jpg'">
                        </div>

                        <!-- Product Details -->
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-semibold text-gray-900 mb-1 line-clamp-1 uppercase">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </h4>

                            <p class="text-xs text-gray-600 mb-2 line-clamp-2">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 55)) . '...'; ?>
                            </p>

                            <!-- Variant Information -->
                            <?php if (!empty($product['variants'])): ?>
                                <?php $firstVariant = $product['variants'][0]; ?>
                                <div class="mb-2">
                                    <?php 
                                    $originalPrice = $firstVariant['price'] / (1 - ($firstVariant['discount'] / 100));
                                    ?>
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <p class="text-black font-bold text-sm">
                                            ₱<?php echo number_format($firstVariant['price'], 2); ?>
                                        </p>
                                        <p class="text-gray-400 text-xs line-through">
                                            ₱<?php echo number_format($originalPrice, 2); ?>
                                        </p>
                                    </div>
                                    <p class="text-xs text-green-600 font-medium">
                                        Save ₱<?php echo number_format($originalPrice - $firstVariant['price'], 2); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- View Product Button -->
                            <form action="product_view.php" method="GET">
                                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="w-full bg-black text-white py-1.5 px-3 rounded hover:bg-red-700 transition-colors duration-200 text-xs font-semibold">
                                    <i class="fa-solid fa-bag-shopping mr-1"></i>
                                    View
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>

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
            background-color: #e5e7eb;
            border-radius: 4px;
        }
    </style>

    <script>
        const loadMoreCard = document.getElementById('loadMoreCard');
        const loadMoreBtnMobile = document.getElementById('loadMoreBtnMobile');
        const sidebar = document.getElementById('productSidebar');
        const closeSidebarBtn = document.getElementById('closeSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.remove('translate-x-full');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        if (loadMoreCard) {
            loadMoreCard.addEventListener('click', openSidebar);
        }

        if (loadMoreBtnMobile) {
            loadMoreBtnMobile.addEventListener('click', openSidebar);
        }

        function closeSidebar() {
            sidebar.classList.add('translate-x-full');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }

        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', closeSidebar);
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });
    </script>
</body>

</html>