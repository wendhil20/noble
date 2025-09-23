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

if (!$product_id || !is_numeric($product_id)) {
    header('Location: product_view.php');
    exit;
}

// Get product info with images and description
$stmt = $conn->prepare("SELECT id, product_name, price, product_images, descriptionpic FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();
$product = $product_result->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: product_view.php');
    exit;
}

// Parse product images from JSON
$product_images = [];
if (!empty($product['product_images'])) {
    $decoded_images = json_decode($product['product_images'], true);
    if (is_array($decoded_images)) {
        $product_images = $decoded_images;
    }
}

// Helper function to display image from file path
function displayImage($imagePath) {
    // Remove any extra slashes and construct proper path
    $cleanPath = str_replace('\\/', '/', $imagePath);
    return "../../" . $cleanPath;
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
            <div class="text-black p-8">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold mb-4">
                            <?= htmlspecialchars($product['product_name']) ?>
                        </h1>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Product Images -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-images mr-2 text-blue-600"></i>
                        Product Images
                    </h3>
                    <div class="flex justify-center">
                        <?php if (!empty($product_images)): ?>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 border border-gray-300 rounded-lg p-4 bg-white shadow-sm max-w-4xl">
                                <?php foreach ($product_images as $index => $imagePath): ?>
                                    <div class="relative overflow-hidden rounded border border-gray-200">
                                        <?php 
                                        $imageSrc = displayImage($imagePath);
                                        ?>
                                        <img
                                            src="<?= $imageSrc ?>"
                                            alt="Product Image <?= $index + 1 ?>"
                                            class="w-full h-32 object-cover image-hover"
                                            onclick="openImageModal('<?= $imageSrc ?>')"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="hidden absolute inset-0 items-center justify-center text-red-500 bg-red-50 text-sm">
                                            <div class="text-center">
                                                <i class="fas fa-exclamation-triangle mb-1"></i>
                                                <div>Image not found</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="border border-gray-300 rounded-lg p-8 bg-white shadow-sm max-w-md">
                                <div class="text-center text-gray-400">
                                    <i class="fas fa-images text-4xl mb-3"></i>
                                    <p>No images available for this product.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Product Description -->
                <div class="mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-align-left mr-2 text-blue-600"></i>
                         Description
                    </h3>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                        <?php if (!empty($product['descriptionpic'])): ?>
                            <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($product['descriptionpic']) ?></p>
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
                    <a href="product_view.php" class="inline-flex items-center justify-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Products
                    </a>
                    <button onclick="shareProduct()" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
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