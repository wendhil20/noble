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
    // Not logged in — redirect to login or Google auth
    header('Location: ../google-callback.php'); // You may replace with `index.php` if default login
    exit;
}

// Get and validate product ID
$product_id = $_GET['id'] ?? null;

// Debug: Show what ID we're trying to fetch
if (isset($_GET['debug'])) {
    echo "<div class='bg-amber-50 border border-amber-200 p-3 text-center text-amber-800 rounded-lg mb-4'>Trying to fetch Product ID: " . htmlspecialchars($product_id) . "</div>";
}

if (!$product_id || !is_numeric($product_id)) {
    echo "<div class='max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-sm border mt-8'>";
    echo "<div class='text-center'>";
    echo "<i class='fas fa-exclamation-triangle text-red-500 text-4xl mb-4'></i>";
    echo "<p class='text-red-600 text-lg font-medium mb-4'>Invalid or missing Product ID: " . htmlspecialchars($product_id ?? 'NULL') . "</p>";
    echo "<a href='product_view.php' class='inline-flex items-center px-6 py-3 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors duration-200'>";
    echo "<i class='fas fa-arrow-left mr-2'></i>Back to Products</a>";
    echo "</div></div>";
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
    echo "<div class='max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-sm border mt-8'>";
    echo "<div class='text-center'>";
    echo "<i class='fas fa-box-open text-gray-400 text-5xl mb-4'></i>";
    echo "<p class='text-red-600 text-lg font-medium'>Product not found</p>";
    echo "</div></div>";
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
    echo "<div class='max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-sm border mt-8'>";
    echo "<div class='text-center'>";
    echo "<i class='fas fa-tags text-gray-400 text-5xl mb-4'></i>";
    echo "<p class='text-red-600 text-lg font-medium'>No variants found for this product</p>";
    echo "</div></div>";
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
    <title><?= htmlspecialchars($product['product_name']) ?> - Product Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .image-zoom {
            transition: transform 0.3s ease;
        }

        .image-zoom:hover {
            transform: scale(1.05);
        }

        .variant-card {
            transition: all 0.2s ease;
        }

        .variant-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .price-badge {
            background: linear-gradient(135deg, #059669, #10b981);
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen">
    <?php include 'navbar/top.php'; ?>
    <!-- Navigation Breadcrumb -->
    <div class="bg-white border-b border-slate-200">
        <div class=" px-4 py-3">
            <nav class="flex items-center space-x-2 text-sm text-slate-600">
                <a href="product_view.php?id=<?= $product['id'] ?>" class="hover:text-slate-900 flex items-center">
                    <i class="fas fa-home mr-1"></i>
                    Products
                </a>
                <i class="fas fa-chevron-right text-slate-400 text-xs"></i>
                <span class="text-slate-900 font-medium"><?= htmlspecialchars($product['product_name']) ?></span>
            </nav>
        </div>
    </div>

    <div class="">
        <!-- Main Product Card -->
        <div class="bg-white shadow-sm border border-slate-200 overflow-hidden">
            <!-- Product Header -->
            <div class="bg-orange-600 text-white p-8">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold mb-3">
                            <?= htmlspecialchars($product['product_name']) ?>
                        </h1>
                        <div class="flex flex-wrap gap-4 text-white">
                            <div class="flex items-center">
                                <i class="fas fa-tag mr-2"></i>
                                <span>ID: <?= $product['id'] ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-code mr-2"></i>
                                <span><?= htmlspecialchars($product['codename'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-cube mr-2"></i>
                                <span><?= htmlspecialchars($variant['namevariant']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Variant Selection -->
                <?php if (count($variants) > 1): ?>
                    <div class="mb-8">
                        <h3 class="text-xl font-semibold text-slate-800 mb-4 flex items-center">
                            <i class="fas fa-layer-group mr-2 text-blue-600"></i>
                            Available Variants
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($variants as $v): ?>
                                <a href="?id=<?= $product_id ?>&variant_id=<?= $v['id'] ?>"
                                    class="variant-card block p-5 border-2 rounded-xl <?= ($v['id'] == $variant['id']) ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-slate-200 bg-white hover:border-slate-300' ?>">
                                    <div class="flex items-start justify-between mb-3">
                                        <h4 class="font-semibold text-slate-800"><?= htmlspecialchars($v['namevariant']) ?></h4>
                                        <?php if ($v['id'] == $variant['id']): ?>
                                            <i class="fas fa-check-circle text-blue-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-lg font-bold text-green-600">₱<?= number_format($v['price'], 2) ?></span>
                                        <div class="text-right text-sm text-slate-500">
                                            <div><?= htmlspecialchars($v['color'] ?? 'N/A') ?></div>
                                            <div><?= htmlspecialchars($v['size'] ?? 'N/A') ?></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Compact Centered Bintana-Style Description Image Viewer -->
                <div class="mb-6 flex justify-center">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 mb-3 text-center">Product Description</h3>

                        <div class="grid grid-cols-2 gap-1 border-2 border-slate-300 rounded-lg overflow-hidden shadow-sm w-[550px] h-[550px] bg-white">
                            <?php
                            $imageFields = [
                                'imagedescription' => 'Top Left',
                                'imagedescriptiontwo' => 'Top Right',
                                'imagedescriptiontree' => 'Bottom Left',
                                'imagedescriptionfour' => 'Bottom Right'
                            ];

                            foreach ($imageFields as $field => $position):
                            ?>
                                <div class="relative group overflow-hidden">
                                    <?php if (!empty($variant[$field])): ?>
                                        <img
                                            src="../<?= htmlspecialchars($variant[$field]) ?>"
                                            alt="<?= $position ?>"
                                            class="w-full h-full object-cover transition duration-200 group-hover:scale-105 cursor-pointer"
                                            onclick="openImageModal(this.src)"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="hidden absolute inset-0 items-center justify-center text-red-500 bg-white text-xs">
                                            <i class="fas fa-times-circle mr-1"></i> Not found
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-400 bg-slate-100 text-xs">
                                            <i class="fas fa-image text-base mr-1"></i> No image
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <script>
                    function openImageModal(src) {
                        const win = window.open("");
                        win.document.write('<img src="' + src + '" style="width:100%">');
                    }
                </script>

                <!-- Product Description -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-slate-800 mb-4 flex items-center">
                        <i class="fas fa-align-left mr-2 text-orange-600"></i>
                        Product Description
                    </h3>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-6">
                        <?php if (!empty($variant['descriptionpic'])): ?>
                            <div class="prose prose-slate max-w-none">
                                <p class="text-slate-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($variant['descriptionpic']) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-file-alt text-slate-300 text-4xl mb-3"></i>
                                <p class="text-slate-400 italic">No description available for this product.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="product_view.php?id=<?= $product['id'] ?>"
                        class="inline-flex items-center justify-center px-6 py-3 bg-slate-600 text-white rounded-xl hover:bg-slate-700 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Products
                    </a>
                    <button class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors duration-200">
                        <i class="fas fa-share-alt mr-2"></i>
                        Share Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section></section>
    

    <!-- Enhanced Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-90 hidden z-50 flex items-center justify-center p-4" onclick="closeImageModal()">
        <div class="relative max-w-5xl max-h-full">
            <button onclick="closeImageModal()" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-2xl">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
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

        // Close modal with escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>