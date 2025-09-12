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

// ✅ Retrieve user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? 'example@example.com';
$user_picture = $_SESSION['user_picture'] ?? null;
// Initialize $is_logged_in variable
$is_logged_in = isset($_SESSION['user_id']) || isset($_COOKIE['remember_token']);

// Continue with your product code...
$product_id = $_GET['id'] ?? 0;

if (!$product_id || !is_numeric($product_id) || $product_id <= 0) {
  echo "Invalid product ID.";
  exit;
}

// Fetch product with sub_images
$stmt = $conn->prepare("SELECT id, product_name, codename, quantity, price, main_image, sub_images, description FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
  echo "Product not found.";
  exit;
}

// Process sub images
$sub_images = [];
if (!empty($product['sub_images'])) {
  $decoded_sub_images = json_decode($product['sub_images'], true);
  if (is_array($decoded_sub_images)) {
    $sub_images = $decoded_sub_images;
  }
}

// Fetch product colors
$stmt = $conn->prepare("SELECT id, color_name, color_code, price, image FROM product_colors WHERE product_id = ? ORDER BY color_name");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$colors_result = $stmt->get_result();
$product_colors = [];
while ($row = $colors_result->fetch_assoc()) {
  $product_colors[] = $row;
}

// Fetch product types and variants
$stmt = $conn->prepare("SELECT pt.*, pv.id as variant_id, pv.namevariant, pv.color, pv.size, pv.price as variant_price, pv.percent, pv.discount, pv.image as variant_image FROM product_types pt LEFT JOIN product_variants pv ON pt.id = pv.type_id WHERE pt.product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$types_result = $stmt->get_result();

$types_data = [];
while ($row = $types_result->fetch_assoc()) {
  $type_name = $row['type_name'];
  if (!isset($types_data[$type_name])) {
    $types_data[$type_name] = [
      'id' => $row['id'],
      'name' => $type_name,
      'image' => $row['type_image'],
      'variants' => []
    ];
  }
  if ($row['variant_id']) {
    $types_data[$type_name]['variants'][] = $row;
  }
}


$codename = $product['codename']; // current product codename
$stmt = $conn->prepare("SELECT id, product_name, codename, quantity, price, main_image, description, descrip6, descrip7
                        FROM products 
                        WHERE codename = ? AND id != ? 
                        ORDER BY RAND() LIMIT 10");
$stmt->bind_param("si", $codename, $product_id);
$stmt->execute();
$related_products = $stmt->get_result();




// Fetch product specifications directly from products table
$product_specs = null;
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product_specs = $result->fetch_assoc();
$stmt->close();

// Get average rating and count of raters
$avg_stmt = $conn->prepare("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
$avg_stmt->bind_param("i", $product['id']);
$avg_stmt->execute();
$avg_data = $avg_stmt->get_result()->fetch_assoc();
$avg_rating = $avg_data['avg_rating'] ?? 0;
$total_raters = $avg_data['total_raters'] ?? 0;
$avg_stmt->close();

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
  <title><?= htmlspecialchars($product['product_name']) ?> - Noble Home</title>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }

    /* Selection States */
    .selected {
      border-color: #f97316;
      /* Tailwind's orange-500 */
      border-width: 2px;
      box-shadow: 0 10px 15px -3px rgba(251, 146, 60, 0.2), 0 4px 6px -4px rgba(251, 146, 60, 0.2);
    }

    .color-selected {
      border-color: #f97316;
      /* Tailwind's orange-500 */
      border-width: 4px;
      box-shadow: 0 10px 15px -3px rgba(251, 146, 60, 0.2), 0 4px 6px -4px rgba(251, 146, 60, 0.2);
      transform: scale(1.10);
    }

    /* Animations */
    .fade-in {
      animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Interactive Elements */
    .color-swatch {
      transition: all 0.3s;
      /* scale-105 on hover */
      /* shadow-lg on hover */
    }

    .color-swatch:hover {
      transform: scale(1.05);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    }

    .type-btn,
    .variant-btn {
      transition: all 0.3s;
      /* On hover: translateY(-0.25rem) and shadow */
    }

    .type-btn:hover,
    .variant-btn:hover {
      transform: translateY(-0.25rem);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    }

    /* Gradient Background */
    .gradient-bg {
      background: linear-gradient(135deg, #f97316 100%, #f97316 100%);
    }

    /* Swiper Styles */
    .related-swiper {
      position: relative;
      padding-left: 1rem;
      padding-right: 1rem;
    }
    @media (min-width: 768px) {
      .related-swiper {
        padding-left: 4rem;
        padding-right: 4rem;
      }
    }

    .related-swiper .swiper-button-next,
    .related-swiper .swiper-button-prev {
        top: 50%;
        transform: translateY(-50%);
        width: 2.5rem;
        height: 2.5rem;
        background-color: #f97316;
        border-radius: 9999px;
        color: #fff;
        transition: all 0.3s;
      }
      .related-swiper .swiper-button-next:hover,
      .related-swiper .swiper-button-prev:hover {
        background-color: #ea580c;
        transform: scale(1.10);
    }

    .related-swiper .swiper-button-next {
      right: 0.5rem;
      /* Tailwind's right-2 */
    }

    .related-swiper .swiper-button-prev {
      left: 0.5rem;
      /* Tailwind's left-2 */
    }

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* Focus States */
    .type-btn:focus,
    .variant-btn:focus,
    .color-btn:focus {
      outline-width: 2px;
      outline-color: #f97316; /* Tailwind's orange-500 */
      outline-offset: 2px;
      outline-style: solid;
    }

    /* Mobile Swiper Hide Navigation */
    @media (max-width: 768px) {

      .related-swiper .swiper-button-next,
      .related-swiper .swiper-button-prev {
        display: none;
      }
    }
  </style>
</head>

<body class="bg-gray-50 ">
  <?php include '../navbar/top.php'; ?>

  <!-- Hero Section with Bouncing Bubbles Background -->
  <div class="gradient-bg text-white py-6 sm:py-7 lg:py-8 relative overflow-hidden">
    <!-- Bouncing Bubbles SVG Layer -->
    <div class="absolute inset-0 pointer-events-none z-0">
      <svg width="100%" height="100%" class="w-full h-full" style="position:absolute;top:0;left:0;" xmlns="http://www.w3.org/2000/svg">
        <circle class="bubble bubble1" cx="10%" cy="80%" r="32" fill="#fff" fill-opacity="0.13" />
        <circle class="bubble bubble2" cx="25%" cy="90%" r="18" fill="#fff" fill-opacity="0.10" />
        <circle class="bubble bubble3" cx="40%" cy="85%" r="24" fill="#fff" fill-opacity="0.09" />
        <circle class="bubble bubble4" cx="60%" cy="92%" r="14" fill="#fff" fill-opacity="0.11" />
        <circle class="bubble bubble5" cx="75%" cy="88%" r="28" fill="#fff" fill-opacity="0.12" />
        <circle class="bubble bubble6" cx="90%" cy="80%" r="20" fill="#fff" fill-opacity="0.10" />
      </svg>
    </div>
    <div class="container mx-auto px-4 relative z-10">
      <h1 class="text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-bold text-center mb-2 sm:mb-4">Your Shopping</h1>
      <p class="text-sm sm:text-lg lg:text-xl text-center opacity-90 max-w-2xl mx-auto">
        Learn more about this item and check if it fits your needs.<br>
        Discover detailed specifications, available options, and make the best choice for your home.
      </p>
    </div>
    <style>
      /* Bouncing animation for bubbles */
      .bubble1 {
        animation: bubble-bounce1 7s ease-in-out infinite alternate;
      }

      .bubble2 {
        animation: bubble-bounce2 6s ease-in-out infinite alternate;
      }

      .bubble3 {
        animation: bubble-bounce3 8s ease-in-out infinite alternate;
      }

      .bubble4 {
        animation: bubble-bounce4 5.5s ease-in-out infinite alternate;
      }

      .bubble5 {
        animation: bubble-bounce5 7.5s ease-in-out infinite alternate;
      }

      .bubble6 {
        animation: bubble-bounce6 6.5s ease-in-out infinite alternate;
      }

      @keyframes bubble-bounce1 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-60px) scale(1.08);
        }
      }

      @keyframes bubble-bounce2 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-40px) scale(1.12);
        }
      }

      @keyframes bubble-bounce3 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-70px) scale(1.05);
        }
      }

      @keyframes bubble-bounce4 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-30px) scale(1.15);
        }
      }

      @keyframes bubble-bounce5 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-55px) scale(1.09);
        }
      }

      @keyframes bubble-bounce6 {
        0% {
          transform: translateY(0) scale(1);
        }

        100% {
          transform: translateY(-35px) scale(1.13);
        }
      }
    </style>
  </div>

  <!-- Breadcrumb -->
  <nav class="bg-white border-b border-gray-200 px-4 py-3">
    <div class="container mx-auto">
      <div class="flex items-center space-x-2 text-sm">
        <a href="index" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
          <i class="fas fa-home mr-1"></i>Home
        </a>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class="text-gray-600 font-medium">Products</span>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container mx-auto px-4 py-6 lg:py-8">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">

        <!-- Product Image & Info Section -->
        <div class="p-4 lg:p-8">
          <!-- Product Image -->
          <div class="aspect-square mb-4 relative bg-gray-50 rounded-lg overflow-hidden shadow-lg">
            <img id="main-product-image"
              src="../../<?= htmlspecialchars($product['main_image']) ?>"
              class="w-full h-full object-contain transition-all duration-300 hover:scale-105"
              alt="<?= htmlspecialchars($product['product_name']) ?>">

            <!-- Image Navigation Arrows (if sub images exist) -->
            <?php if (!empty($sub_images)): ?>
              <button id="prev-image" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-white/80 hover:bg-white text-gray-700 rounded-full p-2 shadow-md transition-all duration-200 opacity-0 group-hover:opacity-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
              </button>
              <button id="next-image" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-white/80 hover:bg-white text-gray-700 rounded-full p-2 shadow-md transition-all duration-200 opacity-0 group-hover:opacity-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
              </button>

              <!-- Image Counter -->
              <div class="absolute bottom-2 right-2 bg-black/60 text-white text-xs px-2 py-1 rounded">
                <span id="current-image-index">1</span> / <span id="total-images"><?= count($sub_images) + 1 ?></span>
              </div>
            <?php endif; ?>
          </div>


          <?php if (!empty($sub_images)): ?>
            <div class="thumbnail-gallery mt-3">
              <!-- Scrollable Container -->
              <div class="thumbnail-container overflow-x-auto scrollbar-hide">
                <div class="flex gap-1 sm:gap-2 pb-2">
                  <!-- Main Image Thumbnail -->
                  <div class="thumbnail-item cursor-pointer flex-shrink-0" data-index="0">
                    <img src="../../<?= htmlspecialchars($product['main_image']) ?>" loading="lazy"
                      class="w-14 h-14 sm:w-16 sm:h-16 object-contain rounded-lg border-2 border-transparent hover:border-blue-500 transition-all duration-200 thumbnail-active"
                      alt="Main Image">
                  </div>

                  <!-- Sub Images Thumbnails -->
                  <?php foreach ($sub_images as $index => $sub_image): ?>
                    <div class="thumbnail-item cursor-pointer flex-shrink-0" data-index="<?= $index + 1 ?>">
                      <img src="../<?= htmlspecialchars($sub_image) ?>" loading="lazy"
                        class="w-14 h-14 sm:w-16 sm:h-16 object-contain rounded-lg border-2 border-transparent hover:border-blue-500 transition-all duration-200"
                        alt="Sub Image <?= $index + 1 ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <style>
            * Hide scrollbar but keep functionality */ .scrollbar-hide {
              -ms-overflow-style: none;
              /* IE and Edge */
              scrollbar-width: none;
              /* Firefox */
            }

            .scrollbar-hide::-webkit-scrollbar {
              display: none;
              /* Chrome, Safari and Opera */
            }

            /* Smooth scrolling */
            .thumbnail-container {
              scroll-behavior: smooth;
            }

            /* Optional: Add fade effect on edges to indicate scrollability */
            .thumbnail-container::before,
            .thumbnail-container::after {
              content: '';
              position: absolute;
              top: 0;
              bottom: 0;
              width: 20px;
              pointer-events: none;
              z-index: 1;
            }

            .thumbnail-container::before {
              left: 0;
              background: linear-gradient(to right, rgba(255, 255, 255, 0.8), transparent);
            }

            .thumbnail-container::after {
              right: 0;
              background: linear-gradient(to left, rgba(255, 255, 255, 0.8), transparent);
            }
          </style>

          <!-- Product Basic Info -->
          <div class="space-y-4 lg:space-y-6">
            <!-- Rating -->
            <div>
              <h3 class="font-semibold text-gray-700 mb-2 text-sm lg:text-base">Customer Rating</h3>
              <?php if ($total_raters > 0): ?>
                <div class="flex items-center gap-2 text-yellow-400">
                  <div class="flex text-lg">
                    <?php
                    $full = floor($avg_rating);
                    $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                    $empty = 5 - $full - $half;

                    for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                    if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                    for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star"></i>';
                    ?>
                  </div>
                  <span class="text-gray-700 font-medium"><?= $avg_rating ?>/5</span>
                  <span class="text-gray-500 text-sm">(<?= $total_raters ?> review<?= $total_raters == 1 ? '' : 's' ?>)</span>
                </div>
              <?php else: ?>
                <p class="text-sm text-gray-500">No reviews yet</p>
              <?php endif; ?>
            </div>

            <!-- Product Name -->
            <div>
              <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-orange-600 mb-2">
                <?= htmlspecialchars($product['product_name']) ?>
              </h1>
              <div class="flex flex-wrap gap-2 mb-3">
                <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-medium">
                  <?= htmlspecialchars($product['codename']) ?>
                </span>

              </div>
            </div>

            <!-- Description -->
            <div>
              <p class="text-gray-700 leading-relaxed text-sm lg:text-base">
                <?= htmlspecialchars($product['description'] ?? 'No description available.') ?>
              </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 pt-4">
              <a href="product_info.php?id=<?= $product['id'] ?>"
                class="flex-1 bg-orange-500 hover:bg-orange-600 text-white text-center px-4 py-3 rounded-lg font-medium transition-colors">
                <i class="fas fa-info-circle mr-2"></i>View Details
              </a>
              <button onclick="shareProduct()"
                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-3 rounded-lg font-medium transition-colors">
                <i class="fas fa-share-alt mr-2"></i>Share
              </button>
            </div>
          </div>
        </div>

        <!-- Product Options Section -->
        <div class="p-4 lg:p-8 bg-gray-50 flex flex-col">

          <!-- Type Selection -->
          <?php if (!empty($types_data)): ?>
            <div class="mb-6 lg:mb-8">
              <h3 class="text-lg lg:text-xl font-bold mb-4 text-gray-800">Select</h3>

              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 lg:gap-4">
                <?php foreach ($types_data as $index => $type): ?>
                  <button type="button"
                    onclick="showVariants(<?= $type['id'] ?>, '<?= addslashes($type['name']) ?>')"
                    class="type-btn border-2 border-gray-200 p-3 lg:p-4 rounded-lg hover:border-orange-300 transition-all bg-white focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-orange-300">

                    <div class="aspect-square rounded-lg mb-2 overflow-hidden bg-gray-100 flex items-center justify-center relative">
                      <?php if (!empty($type['image']) && file_exists("../../" . $type['image'])): ?>
                        <img src="../../<?= htmlspecialchars($type['image']) ?>"
                          class="w-full h-full object-contain"
                          alt="<?= htmlspecialchars($type['name']) ?>"
                          loading="lazy"
                          onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="w-full h-full flex items-center justify-center text-gray-400" style="display: none;">
                          <i class="fas fa-image text-2xl"></i>
                        </div>
                      <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                          <i class="fas fa-image text-2xl"></i>
                        </div>
                      <?php endif; ?>
                    </div>

                    <span class="text-sm font-semibold text-orange-600 block truncate">
                      <?= htmlspecialchars($type['name']) ?>
                    </span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Color Selection -->
          <?php if (!empty($product_colors)): ?>
            <div class="mb-6 lg:mb-8">
              <!-- Section Header -->
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg lg:text-xl font-bold text-gray-800">Available Colors</h3>
                <span class="text-sm text-gray-500 italic block sm:hidden">Swipe to explore →</span>
              </div>

              <!-- Swiper Container -->
              <div class="relative">
                <!-- Swiper Navigation Buttons (hidden on md and up) -->
                <div class="swiper-button-prev color-swiper-prev !left-0 !z-10 !w-6 !h-6 md:!hidden" style="width:1.5rem;height:1.5rem;min-width:unset;min-height:unset;font-size:1rem;"></div>
                <div class="swiper-button-next color-swiper-next !right-0 !z-10 !w-6 !h-6 md:!hidden" style="width:1.5rem;height:1.5rem;min-width:unset;min-height:unset;font-size:1rem;"></div>

                <div class="swiper colorSwiper">
                  <div class="swiper-wrapper pb-2">
                    <?php foreach ($product_colors as $color): ?>
                      <div class="swiper-slide w-16 p-3">
                        <div class="flex flex-col items-center gap-2 p-6">
                          <!-- Color Button -->
                          <button
                            type="button"
                            onclick="selectColor(this, '<?= addslashes($color['color_name']) ?>', <?= $color['price'] ?>, <?= $color['id'] ?>)"
                            class="color-btn color-swatch w-12 h-12 lg:w-14 lg:h-14 rounded-full border-2 border-gray-300 overflow-hidden relative"
                            style="background-color: <?= htmlspecialchars($color['color_code']) ?>;"
                            title="<?= htmlspecialchars($color['color_name']) ?>">
                            <?php if (!empty($color['image'])): ?>
                              <img
                                src="../../<?= htmlspecialchars($color['image']) ?>"
                                alt="<?= htmlspecialchars($color['color_name']) ?>"
                                class="p-1 w-full h-full object-contain rounded-full">
                            <?php endif; ?>
                          </button>

                          <!-- Color Name -->
                          <span class="text-xs text-gray-700 font-medium text-center leading-tight">
                            <?= htmlspecialchars($color['color_name']) ?>
                          </span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <!-- Info Box -->
              <div id="selected-color-info" class="text-sm text-gray-600 mt-3 p-3 bg-white rounded-lg shadow-sm">
                <i class="fas fa-info-circle text-orange-500 mr-2"></i>
                Select a color to see pricing
              </div>
            </div>
          <?php endif; ?>


<!-- Size/Variant Selection -->
<div class="mb-6 lg:mb-8">
  <h3 class="text-lg lg:text-xl font-bold mb-4 text-gray-800">Available Sizes</h3>

  <div id="variant-container" class="text-gray-500 p-4 bg-white rounded-lg text-center">
    <i class="fas fa-arrow-up text-orange-500 mb-2 text-xl"></i>
    <p>Please select a product</p>
  </div>

  <?php foreach ($types_data as $type): ?>
    <div id="variants-<?= $type['id'] ?>" class="variant-group hidden">
      <?php if (!empty($type['variants'])): ?>
        <div class="max-h-72 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 hover:scrollbar-thumb-gray-400 transition-all duration-300 pr-1 p-4">
          <!-- ✅ Responsive uniform grid -->
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 lg:gap-4">
            <?php foreach ($type['variants'] as $variant): ?>
              <?php
              $price = floatval($variant['variant_price']);
              $percent = floatval($variant['percent']);
              $discount = floatval($variant['discount'] ?? 0);
              $priceWithMarkup = $price + ($price * $percent / 100);
              $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
              ?>
              <button type="button"
                onclick="selectVariant(this, '<?= addslashes($variant['color']) ?>')"
                class="variant-btn border-2 border-gray-200 hover:border-orange-400 bg-white rounded-lg 
                       p-2 lg:p-3 text-center transition transform hover:scale-[1.02] 
                       flex flex-col justify-between min-h-[120px] lg:min-h-[140px]"
                data-price="<?= $price ?>"
                data-percent="<?= $percent ?>"
                data-discount="<?= $discount ?>"
                data-variant-id="<?= $variant['variant_id'] ?>">

                <div class="flex flex-col items-center justify-center h-full">
                  
                  <div class="text-gray-600 text-[11px] lg:text-xs mb-1">
                    <?= htmlspecialchars($variant['size']) ?>
                  </div>

                  <!-- ✅ Price + Discount below -->
                  <div class="flex flex-col items-center">
                    <?php if ($discount > 0): ?>
                      <div class="text-[11px] text-gray-400 line-through mb-0.5">₱<?= number_format($priceWithMarkup, 2) ?></div>
                      <div class="text-red-600 font-bold text-xs lg:text-sm">₱<?= number_format($finalPrice, 2) ?></div>
                      <div class="mt-1 text-[11px] font-bold text-red-500 bg-red-100 px-2 py-0.5 rounded-full">
                        <?= number_format($discount, 0) ?>% OFF
                      </div>
                    <?php else: ?>
                      <div class="font-bold text-green-600 text-xs lg:text-sm">₱<?= number_format($finalPrice, 2) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <p class="text-gray-500 text-center p-4">No variants available for this type.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>


          <!-- Purchase Section -->
          <div class="mt-auto">
            <form id="productForm" method="POST" class="space-y-4">
              <input type="hidden" name="product_id" value="<?= $product_id ?>" />
              <input type="hidden" name="selected_color_id" id="selected_color_id">
              <input type="hidden" name="selected_color" id="selected_color">
              <input type="hidden" name="selected_type" id="selected_type">
              <input type="hidden" name="selected_variant" id="selected_variant">
              <input type="hidden" name="variant_id" id="variant_id">

              <!-- Total Price Display -->
              <div class="bg-gradient-to-r from-green-50 to-blue-50 p-4 rounded-xl border border-green-200">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                  <div>
                    <p class="text-sm text-gray-600 mb-1">Total Price</p>
                    <p id="totalPrice" class="text-2xl lg:text-3xl font-bold text-green-600">₱0.00</p>
                  </div>
                  <div id="selectionStatus" class="text-sm text-gray-500 sm:text-right">
                    <?= $is_logged_in ? 'Select all options above' : 'Please log in to pre-order' ?>
                  </div>
                </div>
              </div>

              <!-- Add to Cart Button -->
              <button type="submit" id="addToCartBtn"
                <?= !$is_logged_in ? 'disabled' : '' ?>
                class="w-full py-3 lg:py-4 font-bold text-lg transition-all duration-300
                  <?= $is_logged_in ? 'bg-gray-400 hover:bg-orange-500' : 'bg-red-400' ?> 
                  text-white disabled:cursor-not-allowed disabled:opacity-75">
                <span id="btnText" class="flex items-center justify-center gap-2">
                  <i class="fas fa-shopping-cart"></i>
                  <?= $is_logged_in ? 'Select Options to Pre-Order' : 'Login to Pre-Order' ?>
                </span>
              </button>

            </form>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($product_specs)): ?>
      <section class="mt-6 lg:mt-8">
        <div class="bg-white rounded-xl shadow-lg p-4 lg:p-8">
          <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-orange-700 mb-4 lg:mb-6 flex items-center gap-3">
            <i class="fas fa-list-alt"></i>
            Product Specifications
          </h2>

          <div class="mb-4 lg:mb-6">
            <div class="bg-gray-50 rounded-lg p-4 lg:p-6">
              <dl class="space-y-3">
                <?php for ($i = 1; $i <= 10; $i++):
                  $key = "descrip$i";
                  if (!empty($product_specs[$key])):
                ?>
                    <div class="flex flex-col sm:flex-row sm:justify-between py-2 border-b border-gray-200 last:border-b-0">
                      <dd class="text-gray-700 text-sm lg:text-base"><?= htmlspecialchars($product_specs[$key]) ?></dd>
                    </div>
                <?php endif;
                endfor; ?>
              </dl>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>

<!-- Related Products -->
<?php if ($related_products->num_rows > 0): ?>
<section class="mt-8 bg-gradient-to-br from-slate-50 to-gray-100 py-6 px-4 rounded-xl ">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-6">
            <h2 class="text-xl lg:text-2xl font-bold text-gray-800 mb-2">Related Products</h2>
            <p class="text-gray-600 text-sm">
                Similar products you may like
            </p>
            <div class="w-16 h-0.5 bg-gradient-to-r from-orange-500 to-red-500 mx-auto mt-2 rounded-full"></div>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <?php while ($row = $related_products->fetch_assoc()): ?>
            <div class="group">
                <a href="product_view.php?id=<?= $row['id'] ?>" 
                   class="block rounded-xl hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 overflow-hidden h-full">
                    
                    <!-- Product Image -->
                    <div class="relative aspect-square  overflow-hidden">
                        <?php if ($row['main_image']): ?>
                            <img src="../../<?= $row['main_image'] ?>" 
                                 loading="lazy"
                                 class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                                 alt="<?= htmlspecialchars($row['product_name']) ?>">
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs">No Image</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Product Information -->
                    <div class="p-3 flex flex-col justify-between flex-grow">
                        <!-- Product Name -->
                        <h3 class="font-semibold text-gray-800 text-sm mb-2 line-clamp-2 leading-tight">
                            <?= htmlspecialchars($row['product_name']) ?>
                        </h3>

                        <!-- Product Description -->
                        <div class="flex-grow mb-2">
                            <p class="text-gray-600 text-xs line-clamp-1 mb-1">
                                <?= htmlspecialchars($row['description']) ?>
                            </p>
                            
                            <?php if (!empty($row['descrip6'])): ?>
                                <p class="text-gray-500 text-xs line-clamp-1 mb-1">
                                    • <?= htmlspecialchars($row['descrip6']) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Product Code -->
                        <div class="flex items-center justify-between mt-auto">
                            <span class="text-xs px-2 py-1 bg-orange-100 text-orange-700 rounded-full">
                                <?= htmlspecialchars($row['codename']) ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<?php endif; ?>

  </div>


  <footer class="bg-black pattern-bg text-white py-16 mt-12 relative overflow-hidden">
    <!-- Decorative Elements -->
    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-500"></div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">
      <!-- Main Footer Content -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

        <!-- Enhanced Branding Section -->
        <div class="lg:col-span-2">
          <div class="flex items-center space-x-4 mb-6">
            <!-- Logo with glow and pulse -->
            <div class="relative">
              <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl glow-effect floating overflow-hidden">
                <img src="../img/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
              </div>
              <div class="absolute -top-1 -right-1 w-4 h-4 bg-blue-400 rounded-full animate-pulse"></div>
            </div>

            <!-- Text Branding -->
            <div>
              <h2 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">Noble Home</h2>

            </div>
          </div>


          <p class="text-gray-300 leading-relaxed mb-6 max-w-md">
            Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home awaits with our expert construction and design services.
          </p>

          <!-- Contact Info -->
          <div class="space-y-3">
            <div class="flex items-center space-x-3 text-sm">
              <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                  <path d="m18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                </svg>
              </div>
              <span class="text-gray-300">noblehomeconst.ph@gmail.com</span>
            </div>
            <div class="flex items-center space-x-3 text-sm">
              <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                </svg>
              </div>
              <span class="text-gray-300">0968 591 6536</span>
            </div>
          </div>
        </div>

        <!-- Quick Links -->
        <div>
          <h3 class="text-xl font-bold mb-6 text-white relative">
            Quick Links
            <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
          </h3>
          <nav class="space-y-3">
            <a href="index" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Home</a>
            <a href="about" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">About Us</a>
            <a href="contact" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Contact</a>
          </nav>
        </div>

        <!-- Services -->
        <div>
          <h3 class="text-xl font-bold mb-6 text-white relative">
            Our Services
            <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
          </h3>
          <ul class="space-y-3 text-gray-300">
            <li class="hover:text-orange-300 transition-colors cursor-pointer">Appointment</li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
          </ul>
        </div>
      </div>

      <!-- Divider -->
      <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent mb-8"></div>

      <!-- Bottom Section -->
      <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
        <!-- Copyright -->
        <div class="text-center lg:text-left">
          <p class="text-gray-400 text-sm">
            © 2025 Noble Home Construction. All rights reserved.
          </p>
          <p class="text-gray-500 text-xs mt-1">
            Licensed & Insured | PCAB License No. 12345
          </p>
        </div>

        <!-- Enhanced Social Media -->
        <div class="flex items-center space-x-4">
          <span class="text-gray-400 text-sm mr-2">Follow us:</span>

          <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Facebook">
            <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
            </svg>
          </a>

          <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Instagram">
            <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
            </svg>
          </a>

          <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="LinkedIn">
            <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
            </svg>
          </a>
        </div>

        <!-- Back to Top Button -->
        <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
          class="w-12 h-12 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-lg">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Background Pattern -->
    <div class="absolute bottom-0 right-0 opacity-5">
      <svg width="200" height="200" viewBox="0 0 200 200" fill="none">
        <path d="M50 50h100v100H50z" stroke="currentColor" stroke-width="2" />
        <path d="M70 70h60v60H70z" stroke="currentColor" stroke-width="1" />
        <path d="M90 90h20v20H90z" stroke="currentColor" stroke-width="1" />
      </svg>
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Highlight active thumbnail on click
      document.querySelectorAll('.thumbnail-item').forEach((item) => {
        item.addEventListener('click', () => {
          // Remove active class from all thumbnails
          document.querySelectorAll('.thumbnail-item img').forEach(img => {
            img.classList.remove('border-blue-500', 'thumbnail-active');
            img.classList.add('border-transparent');
          });

          // Add active class to clicked thumbnail
          const clickedImg = item.querySelector('img');
          clickedImg.classList.add('border-blue-500', 'thumbnail-active');
          clickedImg.classList.remove('border-transparent');

          // Scroll clicked thumbnail into view
          item.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center'
          });
        });
      });

      // Optional: Add touch/swipe support for mobile
      let isDown = false;
      let startX;
      let scrollLeft;

      const container = document.querySelector('.thumbnail-container');

      if (container) {
        container.addEventListener('mousedown', (e) => {
          isDown = true;
          startX = e.pageX - container.offsetLeft;
          scrollLeft = container.scrollLeft;
          container.style.cursor = 'grabbing';
        });

        container.addEventListener('mouseleave', () => {
          isDown = false;
          container.style.cursor = 'grab';
        });

        container.addEventListener('mouseup', () => {
          isDown = false;
          container.style.cursor = 'grab';
        });

        container.addEventListener('mousemove', (e) => {
          if (!isDown) return;
          e.preventDefault();
          const x = e.pageX - container.offsetLeft;
          const walk = (x - startX) * 2;
          container.scrollLeft = scrollLeft - walk;
        });
      }
    });

    document.addEventListener('DOMContentLoaded', function() {
      // Image gallery functionality
      const mainImage = document.getElementById('main-product-image');
      const thumbnails = document.querySelectorAll('.thumbnail-item');
      const prevBtn = document.getElementById('prev-image');
      const nextBtn = document.getElementById('next-image');
      const currentIndexSpan = document.getElementById('current-image-index');

      // All image sources (main + sub images)
      const allImages = [
        '../../<?= htmlspecialchars($product['main_image']) ?>',
        <?php foreach ($sub_images as $sub_image): ?> '../<?= htmlspecialchars($sub_image) ?>',
        <?php endforeach; ?>
      ];

      let currentImageIndex = 0;

      // Function to update main image and active thumbnail
      function updateMainImage(index) {
        if (index >= 0 && index < allImages.length) {
          currentImageIndex = index;
          mainImage.src = allImages[index];

          // Update current image counter
          if (currentIndexSpan) {
            currentIndexSpan.textContent = index + 1;
          }

          // Update active thumbnail
          thumbnails.forEach((thumb, i) => {
            thumb.querySelector('img').classList.toggle('thumbnail-active', i === index);
          });
        }
      }

      // Thumbnail click handlers
      thumbnails.forEach((thumbnail, index) => {
        thumbnail.addEventListener('click', function() {
          updateMainImage(index);
        });
      });

      // Navigation button handlers
      if (prevBtn) {
        prevBtn.addEventListener('click', function() {
          const newIndex = currentImageIndex > 0 ? currentImageIndex - 1 : allImages.length - 1;
          updateMainImage(newIndex);
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', function() {
          const newIndex = currentImageIndex < allImages.length - 1 ? currentImageIndex + 1 : 0;
          updateMainImage(newIndex);
        });
      }

      // Keyboard navigation
      document.addEventListener('keydown', function(e) {
        if (allImages.length > 1) {
          if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prevBtn.click();
          } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            nextBtn.click();
          }
        }
      });

      // Show navigation arrows on hover (if sub images exist)
      <?php if (!empty($sub_images)): ?>
        const imageGallery = document.querySelector('.product-image-gallery');
        if (imageGallery) {
          imageGallery.addEventListener('mouseenter', function() {
            if (prevBtn) prevBtn.style.opacity = '1';
            if (nextBtn) nextBtn.style.opacity = '1';
          });

          imageGallery.addEventListener('mouseleave', function() {
            if (prevBtn) prevBtn.style.opacity = '0';
            if (nextBtn) nextBtn.style.opacity = '0';
          });
        }
      <?php endif; ?>

      // Image zoom functionality (optional enhancement)
      mainImage.addEventListener('click', function() {
        // Create modal for full-size image view
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 cursor-pointer';
        modal.innerHTML = `
      <div class="relative max-w-4xl max-h-full p-4">
        <img src="${this.src}" loading="lazy" class="max-w-full max-h-full object-contain" alt="Full size image">
        <button class="absolute top-2 right-2 text-white bg-black bg-opacity-50 rounded-full p-2 hover:bg-opacity-75">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    `;

        document.body.appendChild(modal);

        // Close modal on click
        modal.addEventListener('click', function() {
          document.body.removeChild(modal);
        });
      });
    });

    // Re-initialize Swiper for colorSwiper with navigation
    if (window.colorSwiper) window.colorSwiper.destroy(true, true);
    window.colorSwiper = new Swiper(".colorSwiper", {
      slidesPerView: 2,
      grid: {
        rows: 2,
        fill: 'row'
      },
      spaceBetween: 10,
      freeMode: true,
      grabCursor: true,
      navigation: {
        nextEl: '.color-swiper-next',
        prevEl: '.color-swiper-prev',
      },
      breakpoints: {
        0: {
          slidesPerView: 'auto', // Show all slides in view on small screens
          spaceBetween: 8,
          grid: {
            rows: 1
          }
        },
        640: {
          slidesPerView: 6,
          spaceBetween: 15,
          grid: {
            rows: 2
          }
        },
        768: {
          slidesPerView: 8,
          spaceBetween: 20,
          grid: {
            rows: 2
          }
        },
        1024: {
          slidesPerView: 10,
          spaceBetween: 25,
          grid: {
            rows: 2
          }
        }
      }
    });

    // When navigation is clicked, scroll to show all color swatches on small screens
    document.querySelectorAll('.color-swiper-next, .color-swiper-prev').forEach(btn => {
      btn.addEventListener('click', function() {
        if (window.innerWidth < 640) {
          setTimeout(() => {
            document.querySelector('.colorSwiper').scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
          }, 200);
        }
      });
    });

    // Initialize Swiper for related products
    const relatedSwiper = new Swiper('.related-swiper', {
      slidesPerView: 2,
      spaceBetween: 10,
      loop: true,
      autoplay: {
        delay: 2500,
        disableOnInteraction: false,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      breakpoints: {
        640: {
          slidesPerView: 2,
          spaceBetween: 15,
        },
        768: {
          slidesPerView: 3,
          spaceBetween: 20,
        },
        1024: {
          slidesPerView: 4,
          spaceBetween: 25,
        },
        1280: {
          slidesPerView: 5,
          spaceBetween: 30,
        },
      }
    });

    // Product Selection State Management
    class ProductSelector {
      constructor() {
        this.selectedTypeId = null;
        this.selectedVariantData = null;
        this.selectedColorData = null;
        this.basePrice = parseFloat(document.querySelector('[name="product_id"]').dataset.basePrice) || 0;
        this.hasTypes = document.querySelectorAll('.type-btn').length > 0;

        this.initializeElements();
        this.bindEvents();
      }

      initializeElements() {
        this.elements = {
          mainImage: document.getElementById('main-product-image'),
          colorInfo: document.getElementById('selected-color-info'),
          variantContainer: document.getElementById('variant-container'),
          totalPrice: document.getElementById('totalPrice'),
          selectionStatus: document.getElementById('selectionStatus'),
          addToCartBtn: document.getElementById('addToCartBtn'),
          btnText: document.getElementById('btnText'),
          productForm: document.getElementById('productForm'),
          // Hidden inputs
          selectedColorId: document.getElementById('selected_color_id'),
          selectedColor: document.getElementById('selected_color'),
          selectedType: document.getElementById('selected_type'),
          selectedVariant: document.getElementById('selected_variant'),
          variantId: document.getElementById('variant_id')
        };
      }

      bindEvents() {
        // Form submission
        this.elements.productForm.addEventListener('submit', (e) => this.handleFormSubmit(e));

        // Touch event handling for mobile
        document.addEventListener('DOMContentLoaded', () => {
          const buttons = document.querySelectorAll('.color-btn, .type-btn, .variant-btn');
          buttons.forEach(button => {
            button.addEventListener('touchend', function() {
              this.blur();
            });
          });
        });
      }

      selectColor(button, colorName, colorPrice, colorId) {
        const isCurrentlySelected = button.classList.contains('color-selected');

        if (isCurrentlySelected) {
          this.unselectColor(button);
        } else {
          this.setColorSelection(button, colorName, colorPrice, colorId);
        }

        this.updateDisplay();
      }

      unselectColor(button) {
        // Remove selection indicator
        button.classList.remove('color-selected', 'ring-2', 'ring-orange-500', 'scale-110');
        this.selectedColorData = null;

        // Clear hidden fields
        this.elements.selectedColorId.value = '';
        this.elements.selectedColor.value = '';

        // Reset to original product image
        this.elements.mainImage.src = this.elements.mainImage.dataset.originalSrc || this.elements.mainImage.src;
        this.elements.colorInfo.innerHTML = '<i class="fas fa-info-circle text-orange-500 mr-2"></i>Select a color to see pricing';
      }

      setColorSelection(button, colorName, colorPrice, colorId) {
        // Remove previous selection indicators
        document.querySelectorAll('.color-btn').forEach(btn => {
          btn.classList.remove('color-selected', 'ring-2', 'ring-orange-500', 'scale-110');
        });

        // Add selection indicators to clicked button
        button.classList.add('color-selected', 'ring-2', 'ring-orange-500', 'scale-110');

        this.selectedColorData = {
          id: colorId,
          name: colorName,
          price: parseFloat(colorPrice)
        };

        // Update hidden fields
        this.elements.selectedColorId.value = colorId;
        this.elements.selectedColor.value = colorName;

        // Update display
        this.elements.colorInfo.innerHTML =
          `<i class="fas fa-check-circle text-green-500 mr-2"></i>Selected: <strong>${colorName}</strong> - Additional ₱${parseFloat(colorPrice).toFixed(2)}`;

        // Update image if available
        const colorImage = button.querySelector('img');
        if (colorImage && colorImage.src && !colorImage.src.includes('opacity-0')) {
          this.elements.mainImage.src = colorImage.src;
        }
      }

      showVariants(typeId, typeName) {
        const clickedButton = event.currentTarget;
        const isCurrentlySelected = clickedButton.classList.contains('selected');

        if (isCurrentlySelected) {
          this.unselectType(clickedButton);
        } else {
          this.setTypeSelection(clickedButton, typeId, typeName);
        }

        this.updateDisplay();
      }

      unselectType(button) {
        // Remove selection indicators
        button.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
        button.classList.add('border-gray-200', 'bg-white');

        this.selectedTypeId = null;
        this.elements.selectedType.value = '';

        // Hide all variant groups
        document.querySelectorAll('.variant-group').forEach(group => {
          group.classList.add('hidden');
        });

        // Clear variant selection
        this.clearVariantSelection();

        // Show default message
        this.elements.variantContainer.style.display = 'block';
        this.elements.variantContainer.innerHTML = `
          <div class="text-gray-500 p-4 bg-white rounded-lg text-center">
            <i class="fas fa-arrow-up text-orange-500 mb-2 text-xl"></i>
            <p>Please select a product type first</p>
          </div>
        `;
      }

      setTypeSelection(button, typeId, typeName) {
        // Hide all variant groups
        document.querySelectorAll('.variant-group').forEach(group => {
          group.classList.add('hidden');
        });

        // Show selected variant group
        const variantGroup = document.getElementById(`variants-${typeId}`);
        if (variantGroup) {
          variantGroup.classList.remove('hidden');
          variantGroup.classList.add('fade-in');
          this.elements.variantContainer.style.display = 'none';
        }

        // Update type selection indicators
        document.querySelectorAll('.type-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
          btn.classList.add('border-gray-200', 'bg-white');
        });
        button.classList.add('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
        button.classList.remove('border-gray-200', 'bg-white');

        this.selectedTypeId = typeId;
        this.elements.selectedType.value = typeName;

        // Clear previous variant selection
        this.clearVariantSelection();
      }

      selectVariant(button, color) {
        const isCurrentlySelected = button.classList.contains('selected');

        if (isCurrentlySelected) {
          this.unselectVariant(button);
        } else {
          this.setVariantSelection(button, color);
        }

        this.updateDisplay();
      }

      unselectVariant(button) {
        // Remove selection indicators
        button.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
        button.classList.add('border-gray-200', 'bg-white');

        this.selectedVariantData = null;
        this.elements.selectedVariant.value = '';
        this.elements.variantId.value = '';
      }

      setVariantSelection(button, color) {
        // Remove previous selection indicators
        document.querySelectorAll('.variant-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
          btn.classList.add('border-gray-200', 'bg-white');
        });

        // Add selection indicators to clicked button
        button.classList.add('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
        button.classList.remove('border-gray-200', 'bg-white');

        const price = parseFloat(button.dataset.price);
        const percent = parseFloat(button.dataset.percent);
        const discount = parseFloat(button.dataset.discount);
        const variantId = button.dataset.variantId;

        const priceWithMarkup = price + (price * percent / 100);
        const finalPrice = priceWithMarkup - (priceWithMarkup * discount / 100);

        this.selectedVariantData = {
          price,
          percent,
          discount,
          finalPrice,
          priceWithMarkup,
          variantId,
          color
        };

        // Update hidden fields
        this.elements.selectedVariant.value = color;
        this.elements.variantId.value = variantId;
      }

      clearVariantSelection() {
        this.selectedVariantData = null;
        this.elements.selectedVariant.value = '';
        this.elements.variantId.value = '';
        document.querySelectorAll('.variant-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
          btn.classList.add('border-gray-200', 'bg-white');
        });
      }

      calculateTotalPrice() {
        let totalPrice = 0;
        const hasSelections = this.selectedColorData || this.selectedVariantData;

        if (hasSelections) {
          totalPrice = this.basePrice;

          // Add color price if selected
          if (this.selectedColorData) {
            totalPrice += this.selectedColorData.price;
          }

          // Use variant price if selected
          if (this.selectedVariantData) {
            totalPrice = this.selectedVariantData.finalPrice;
            if (this.selectedColorData) {
              totalPrice += this.selectedColorData.price;
            }
          }
        }

        return {
          totalPrice,
          hasSelections
        };
      }

      updateTotalPrice() {
        const {
          totalPrice,
          hasSelections
        } = this.calculateTotalPrice();

        if (hasSelections) {
          this.elements.totalPrice.textContent = `₱${totalPrice.toFixed(2)}`;
        } else {
          this.elements.totalPrice.textContent = '₱0.00';
        }

        // Update selection status
        const status = [];
        if (this.selectedColorData) status.push(`Color: ${this.selectedColorData.name}`);
        if (this.selectedVariantData) status.push(`Variant: ${this.selectedVariantData.color}`);

        this.elements.selectionStatus.textContent =
          status.length > 0 ? status.join(', ') : 'Select all options above';
      }

      updatePurchaseButton() {
        const hasRequiredSelections = this.selectedColorData &&
          (!this.hasTypes || (this.selectedTypeId && this.selectedVariantData));

        if (hasRequiredSelections) {
          this.elements.addToCartBtn.disabled = false;
          this.elements.addToCartBtn.className = 'w-full py-3 lg:py-4 font-bold text-lg rounded-xl transition-all duration-300 bg-orange-500 hover:bg-orange-600 text-white';
          this.elements.btnText.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i>Add to Pre-Order';
        } else {
          this.elements.addToCartBtn.disabled = true;
          this.elements.addToCartBtn.className = 'w-full py-3 lg:py-4 font-bold text-lg rounded-xl transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75';
          this.elements.btnText.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i>Select Options to Pre-Order';
        }
      }

      updateDisplay() {
        this.updateTotalPrice();
        this.updatePurchaseButton();
      }

      validateSelections() {
        const errors = [];

        if (!this.selectedColorData) {
          errors.push('Please select a color');
        }

        if (this.hasTypes) {
          if (!this.selectedTypeId) {
            errors.push('Please select a product type');
          }
          if (!this.selectedVariantData) {
            errors.push('Please select a variant');
          }
        }

        return errors;
      }

      handleFormSubmit(e) {
        e.preventDefault();

        const errors = this.validateSelections();
        if (errors.length > 0) {
          this.showNotification(errors.join(', '), 'error');
          return;
        }

        this.submitToCart();
      }

      async submitToCart() {
        try {
          const formData = this.buildFormData();

          const response = await fetch('../cart/add_to_cart.php', {
            method: 'POST',
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            this.showNotification(data.message || 'Product added to cart!', 'success');
            this.updateCartCount(data.cart_count);

            if (data.item_added) {
              console.log('Item added:', data.item_added);
            }
          } else {
            if (data.message === 'You must be logged in to pre-order.') {
              this.showNotification('Please log in to pre-order.', 'error');

              const loginDropdown = document.querySelector('#authDropdown');
              if (loginDropdown) {
                const alpineData = Alpine?.$data(loginDropdown);
                if (alpineData) {
                  alpineData.loginOpen = true;

                  setTimeout(() => {
                    const emailInput = loginDropdown.querySelector('input[type="email"]');
                    if (emailInput) emailInput.focus();
                  }, 100);
                }
              }
            } else {
              throw new Error(data.message || 'Add to cart failed.');
            }
          }

        } catch (error) {
          this.showNotification('' + error.message, 'error');
          console.error('Add to cart error:', error);
        }
      }

      buildFormData() {
        const formData = new FormData();
        const productId = document.querySelector('[name="product_id"]').value;

        formData.append('product_id', productId);

        if (this.selectedVariantData) {
          formData.append('variant_id', this.selectedVariantData.variantId);
          formData.append('selected_type', this.elements.selectedType.value);
          formData.append('selected_variant', this.elements.selectedVariant.value);
          formData.append('variant_price', this.selectedVariantData.finalPrice);
        }

        if (this.selectedColorData) {
          formData.append('selected_color_id', this.selectedColorData.id);
          formData.append('selected_color_name', this.selectedColorData.name);
          formData.append('color_price', this.selectedColorData.price);
        }

        const {
          totalPrice
        } = this.calculateTotalPrice();
        formData.append('total_price', totalPrice);

        return formData;
      }

      showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        const bgColor = {
          success: 'bg-green-500',
          error: 'bg-red-500',
          info: 'bg-blue-500'
        } [type] || 'bg-blue-500';

        notification.className = `fixed top-4 left-1/2 -translate-x-1/2 p-4 rounded-lg z-50 ${bgColor} text-white shadow-lg transform transition-all duration-300`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 3000);
      }

      updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]');
        cartCountElements.forEach(element => {
          element.textContent = count;
          element.style.display = count > 0 ? 'inline' : 'none';
        });

        const cartBubble = document.getElementById('cart-count-bubble');
        if (cartBubble) {
          if (count > 0) {
            cartBubble.classList.remove('hidden');
            cartBubble.style.display = 'inline';
          } else {
            cartBubble.classList.add('hidden');
            cartBubble.style.display = 'none';
          }
        }
      }
    }

    // Initialize the product selector
    const productSelector = new ProductSelector();

    // Global functions for onclick handlers
    function selectColor(button, colorName, colorPrice, colorId) {
      productSelector.selectColor(button, colorName, colorPrice, colorId);
    }

    function showVariants(typeId, typeName) {
      productSelector.showVariants(typeId, typeName);
    }

    function selectVariant(button, color) {
      productSelector.selectVariant(button, color);
    }

    function shareProduct() {
      const productName = document.querySelector('h1').textContent;

      if (navigator.share) {
        navigator.share({
          title: productName,
          url: window.location.href
        }).catch(err => {
          console.log('Error sharing:', err);
          fallbackShare();
        });
      } else {
        fallbackShare();
      }
    }

    function fallbackShare() {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(window.location.href)
          .then(() => {
            productSelector.showNotification('Link copied to clipboard!', 'success');
          })
          .catch(() => {
            productSelector.showNotification('Could not copy link', 'error');
          });
      } else {
        productSelector.showNotification('Sharing not supported', 'error');
      }
    }

    // Store original image src for reset functionality
    document.addEventListener('DOMContentLoaded', function() {
      const mainImage = document.getElementById('main-product-image');
      if (mainImage) {
        mainImage.dataset.originalSrc = mainImage.src;
      }
    });
  </script>
</body>

</html>