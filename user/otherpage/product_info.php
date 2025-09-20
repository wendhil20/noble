<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token (email or mobile-based or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        // 🔐 Store essential user session info
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';

        // 👤 Check if it's a Google account (optional)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }

    $stmt->close();
}

// ✅ Final session check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Get and validate product ID
$product_id = $_GET['id'] ?? null;

// Debug: Show what ID we're trying to fetch
if (isset($_GET['debug'])) {
    echo "<div class='bg-amber-50 border border-amber-200 p-4 text-center text-amber-800 rounded-lg mb-6'>
            <i class='fas fa-bug mr-2'></i>
            Debug: Fetching Product ID: " . htmlspecialchars($product_id) . "
          </div>";
}

if (!$product_id || !is_numeric($product_id)) {
    echo "<div class='min-h-screen flex items-center justify-center bg-gray-50'>";
    echo "<div class='max-w-md w-full bg-white p-8 rounded-lg shadow-lg border'>";
    echo "<div class='text-center'>";
    echo "<i class='fas fa-exclamation-triangle text-red-500 text-4xl mb-4'></i>";
    echo "<h2 class='text-xl font-semibold text-gray-900 mb-2'>Invalid Product ID</h2>";
    echo "<p class='text-red-600 mb-6'>Product ID: " . htmlspecialchars($product_id ?? 'NULL') . "</p>";
    echo "<a href='product_view.php' class='inline-flex items-center px-6 py-3 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition-colors'>";
    echo "<i class='fas fa-arrow-left mr-2'></i>Back to Products</a>";
    echo "</div></div></div>";
    exit;
}

// Get product info
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();
$product = $product_result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "<div class='min-h-screen flex items-center justify-center bg-gray-50'>";
    echo "<div class='max-w-md w-full bg-white p-8 rounded-lg shadow-lg'>";
    echo "<div class='text-center'>";
    echo "<i class='fas fa-box-open text-gray-400 text-4xl mb-4'></i>";
    echo "<h2 class='text-xl font-semibold text-gray-900 mb-2'>Product Not Found</h2>";
    echo "<p class='text-gray-500 mb-6'>The requested product could not be found.</p>";
    echo "<a href='product_view.php' class='inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors'>";
    echo "<i class='fas fa-arrow-left mr-2'></i>Back to Products</a>";
    echo "</div></div></div>";
    exit;
}

// Get all variants for this product
$stmt = $conn->prepare("
    SELECT pv.*, pt.type_name 
    FROM product_variants pv 
    LEFT JOIN product_types pt ON pv.type_id = pt.id 
    WHERE pv.product_id = ? 
    ORDER BY pv.namevariant
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$variants_result = $stmt->get_result();
$variants = [];
while ($row = $variants_result->fetch_assoc()) {
    $variants[] = $row;
}
$stmt->close();

// If no variants found, show error
if (empty($variants)) {
    echo "<div class='min-h-screen flex items-center justify-center bg-gray-50'>";
    echo "<div class='max-w-md w-full bg-white p-8 rounded-lg shadow-lg'>";
    echo "<div class='text-center'>";
    echo "<i class='fas fa-tags text-gray-400 text-4xl mb-4'></i>";
    echo "<h2 class='text-xl font-semibold text-gray-900 mb-2'>No Variants Available</h2>";
    echo "<p class='text-gray-500 mb-6'>This product has no available variants.</p>";
    echo "<a href='product_view.php' class='inline-flex items-center px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors'>";
    echo "<i class='fas fa-arrow-left mr-2'></i>Back to Products</a>";
    echo "</div></div></div>";
    exit;
}

// Handle variant selection if variant_id is passed
$variant = $variants[0]; // Default to first variant
if (isset($_GET['variant_id'])) {
    $variant_id = $_GET['variant_id'];

    // Find the selected variant
    foreach ($variants as $v) {
        if ($v['id'] == $variant_id) {
            $variant = $v;
            break;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title><?= htmlspecialchars($product['product_name']) ?> - Product Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .image-hover {
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .image-hover:hover {
            transform: scale(1.02);
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Breadcrumb -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <nav class="flex items-center space-x-2 text-sm text-gray-600">
                <a href="product_view.php" class="hover:text-blue-600 transition-colors">
                    <i class="fas fa-home mr-1"></i>
                    Products
                </a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-gray-900 font-medium"><?= htmlspecialchars($product['product_name']) ?></span>
            </nav>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Main Product Card -->
        <div class="bg-white rounded-lg shadow-lg border overflow-hidden">
            <!-- Product Header -->
            <div class=" text-black p-8">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold mb-4">
                            <?= htmlspecialchars($product['product_name']) ?>
                        </h1>
                     
                    </div>
                    <div class="mt-6 lg:mt-0">
                        <div class="bg-green-600 text-white px-6 py-3 rounded-lg">
                            <div class="text-sm opacity-90">Price</div>
                            <div class="text-2xl font-bold">₱<?= number_format($variant['price'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Variant Selection -->
                <?php if (count($variants) > 1): ?>
                    <div class="mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">
                            <i class="fas fa-layer-group mr-2 text-blue-600"></i>
                            Available Variants
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($variants as $v): ?>
                                <a href="?id=<?= $product_id ?>&variant_id=<?= $v['id'] ?>"
                                   class="card-hover block p-5 border-2 rounded-lg <?= ($v['id'] == $variant['id']) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300' ?>">
                                    <div class="flex items-start justify-between mb-3">
                                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($v['namevariant']) ?></h4>
                                        <?php if ($v['id'] == $variant['id']): ?>
                                            <i class="fas fa-check-circle text-blue-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-lg font-bold text-green-600">₱<?= number_format($v['price'], 2) ?></span>
                                        <div class="text-right text-sm text-gray-500">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-palette w-3 mr-1"></i>
                                                <?= htmlspecialchars($v['color'] ?? 'N/A') ?>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-ruler w-3 mr-1"></i>
                                                <?= htmlspecialchars($v['size'] ?? 'N/A') ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Product Images -->
                <div class="mb-8">
             
                    <div class="flex justify-center">
                        <div class="grid grid-cols-2 gap-3 border border-gray-300 rounded-lg p-4 bg-white shadow-sm" style="width: 500px; height: 500px;">
                            <?php
                            $imageFields = [
                                'imagedescription' => 'Image 1',
                                'imagedescriptiontwo' => 'Image 2',
                                'imagedescriptiontree' => 'Image 3',
                                'imagedescriptionfour' => 'Image 4'
                            ];

                            foreach ($imageFields as $field => $label):
                            ?>
                                <div class="relative overflow-hidden rounded border border-gray-200">
                                    <?php if (!empty($variant[$field])): ?>
                                        <img
                                            src="../<?= htmlspecialchars($variant[$field]) ?>"
                                            alt="<?= $label ?>"
                                            class="w-full h-full object-cover image-hover"
                                            onclick="openImageModal(this.src)"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="hidden absolute inset-0 items-center justify-center text-red-500 bg-red-50 text-sm">
                                            <div class="text-center">
                                                <i class="fas fa-exclamation-triangle mb-1"></i>
                                                <div>Image not found</div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-400 bg-gray-100 text-sm">
                                            <div class="text-center">
                                                <i class="fas fa-image text-xl mb-1"></i>
                                                <div>No image</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Product Description -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-align-left mr-2 text-blue-600"></i>
                        Product Description
                    </h3>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                        <?php if (!empty($variant['descriptionpic'])): ?>
                            <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($variant['descriptionpic']) ?></p>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-file-alt text-gray-300 text-3xl mb-3"></i>
                                <p class="text-gray-400">No description available for this product.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="product_view.php"
                       class="inline-flex items-center justify-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Products
                    </a>
                    <button onclick="shareProduct()"
                            class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-share-alt mr-2"></i>
                        Share Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center p-4" onclick="closeImageModal()">
        <div class="relative max-w-4xl max-h-full">
            <button onclick="closeImageModal()" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" class="max-w-full max-h-full object-contain rounded-lg">
        </div>
    </div>

    <script>
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function shareProduct() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= htmlspecialchars($product['product_name']) ?>',
                    text: 'Check out this product!',
                    url: window.location.href
                });
            } else {
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Link copied to clipboard!');
                });
            }
        }

        // Close modal with escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>

</html>