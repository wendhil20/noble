<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ STEP 1: SESSION RESTORE
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
      $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
    }
  }
  $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
  header('Location: ../google-callback.php');
  exit;
}

// ✅ RETRIEVE USER INFO
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? 'example@example.com';
$user_picture = $_SESSION['user_picture'] ?? null;
$is_logged_in = isset($_SESSION['user_id']) || isset($_COOKIE['remember_token']);

// ✅ VALIDATE PRODUCT ID
$product_id = $_GET['id'] ?? 0;
if (!$product_id || !is_numeric($product_id) || $product_id <= 0) {
  echo "Invalid product ID.";
  exit;
}

// ============================================================
// FETCH ORDER: PRODUCTS → PRODUCT_TYPES → PRODUCT_COLORS → PRODUCT_VARIANTS
// ============================================================

// ✅ STEP 1: FETCH FROM PRODUCTS TABLE
$stmt = $conn->prepare("
  SELECT id, product_name, codename, quantity, price, main_image, sub_images, 
         description, descrip1, descrip2, descrip3, descrip4, descrip5,
         descrip6, descrip7, descrip8, descrip9, descrip10
  FROM products 
  WHERE id = ? 
  LIMIT 1
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
  echo "Product not found.";
  exit;
}

// ✅ CREATE BACKUP OF ORIGINAL PRODUCT DATA (To prevent overwriting)
$ORIGINAL_PRODUCT = $product;

// Debug log
error_log("=== PRODUCT DATA FETCHED ===");
error_log("Product ID: " . $product['id']);
error_log("Product Name: " . $product['product_name']);
error_log("Description: " . substr($product['description'], 0, 100));

// ✅ STEP 2: FETCH FROM PRODUCT_TYPES TABLE
$type_main_image = null;
$type_main_name = null;

$stmt = $conn->prepare("
  SELECT id, type_name, type_image 
  FROM product_types 
  WHERE product_id = ? 
  ORDER BY id ASC 
  LIMIT 1
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $type_row = $result->fetch_assoc();
  $type_main_image = $type_row['type_image'];
  $type_main_name = $type_row['type_name'];
}
$stmt->close();

// Determine display image and name
$display_image = !empty($type_main_image) ? $type_main_image : $product['main_image'];
$display_name = !empty($type_main_name) ? $type_main_name : $product['product_name'];

// Process sub images
$sub_images = [];
if (!empty($product['sub_images'])) {
  $decoded_sub_images = json_decode($product['sub_images'], true);
  if (is_array($decoded_sub_images)) {
    $sub_images = $decoded_sub_images;
  }
}

// ✅ STEP 3: FETCH FROM PRODUCT_COLORS TABLE
$stmt = $conn->prepare("
  SELECT id, color_name, color_code, price, image 
  FROM product_colors 
  WHERE product_id = ? 
  ORDER BY color_name
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$colors_result = $stmt->get_result();

$product_colors = [];
while ($row = $colors_result->fetch_assoc()) {
  $product_colors[] = $row;
}
$stmt->close();

// ✅ STEP 4: FETCH FROM PRODUCT_TYPES AND PRODUCT_VARIANTS TABLES
$stmt = $conn->prepare("
  SELECT 
    pt.id as type_id,
    pt.type_name,
    pt.type_image,
    pv.id as variant_id, 
    pv.namevariant, 
    pv.color, 
    pv.size, 
    pv.price as variant_price, 
    pv.percent, 
    pv.discount, 
    pv.image as variant_image,
    pv.sku_info
  FROM product_types pt
  LEFT JOIN product_variants pv ON pt.id = pv.type_id 
  WHERE pt.product_id = ?
  ORDER BY pt.id ASC, pv.size ASC
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$types_result = $stmt->get_result();

$types_data = [];
while ($row = $types_result->fetch_assoc()) {
  $type_name = $row['type_name'];
  if (!isset($types_data[$type_name])) {
    $types_data[$type_name] = [
      'id' => $row['type_id'],
      'name' => $type_name,
      'image' => $row['type_image'],
      'variants' => []
    ];
  }
  if ($row['variant_id']) {
    $types_data[$type_name]['variants'][] = $row;
  }
}
$stmt->close();

// ✅ FETCH RELATED PRODUCTS
$codename = $product['codename'];
$stmt = $conn->prepare("
  SELECT id, product_name, codename, quantity, price, main_image, sub_images, description, descrip6, descrip7
  FROM products 
  WHERE codename = ? AND id != ? 
  ORDER BY RAND()
");
$stmt->bind_param("si", $codename, $product_id);
$stmt->execute();
$related_products = $stmt->get_result();
$stmt->close();

// ✅ CHECK IF WINDOWS CATEGORY
$is_windows_category = strtolower($product['codename']) === 'windows';

// ✅ USE ALREADY FETCHED PRODUCT DATA FOR SPECIFICATIONS
$product_specs = $product;

// ✅ GET AVERAGE RATING
$avg_stmt = $conn->prepare("
  SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters 
  FROM product_ratings 
  WHERE product_id = ?
");
$avg_stmt->bind_param("i", $product['id']);
$avg_stmt->execute();
$avg_data = $avg_stmt->get_result()->fetch_assoc();
$avg_rating = $avg_data['avg_rating'] ?? 0;
$total_raters = $avg_data['total_raters'] ?? 0;
$avg_stmt->close();

// ✅ FINAL VALIDATION: Ensure product data is intact
if (!isset($product['product_name']) || empty($product['product_name'])) {
  error_log("⚠️ WARNING: Product name missing! Using backup data.");
  $product = $ORIGINAL_PRODUCT;
}

// ============================================================
// ALL DATA IS NOW READY - USE IT IN YOUR HTML/TEMPLATE
// ============================================================

// echo "<h2>Fetched Product Data:</h2>";
// if ($product) {
//   echo "<table border='1' cellpadding='10'>";
//   echo "<tr><th>Field</th><th>Value</th></tr>";
//   echo "<tr><td>ID</td><td>" . htmlspecialchars($product['id']) . "</td></tr>";
//   echo "<tr><td>Product Name</td><td>" . htmlspecialchars($product['product_name']) . "</td></tr>";
//   echo "<tr><td>Description</td><td>" . htmlspecialchars($product['description']) . "</td></tr>";
//   echo "</table>";

//   echo "<hr>";
//   echo "<h3>✅ SUCCESS - Correct product fetched!</h3>";
// } else {
//   echo "<p style='color:red;'>❌ NO PRODUCT FOUND with ID: $product_id</p>";
// }

// // Test other IDs
// echo "<hr>";
// echo "<h2>Test Other Products:</h2>";
// echo "<ul>";
// echo "<li><a href='?id=8'>Test ID 8 (Marine)</a></li>";
// echo "<li><a href='?id=21'>Test ID 21 (Sliding Window)</a></li>";
// echo "<li><a href='?id=43'>Test ID 43 (Modern Dining Table)</a></li>";
// echo "<li><a href='?id=48'>Test ID 48 (Modern King Bed)</a></li>";
// echo "<li><a href='?id=53'>Test ID 53 (Classic Tufted King Bed)</a></li>";
// echo "</ul>";
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
    /* Selection States */
    .selected {
      border-color: #f97316;
      /* Tailwind's orange-500 */
      border-width: 1px;
      box-shadow: 0 10px 15px -3px rgba(251, 146, 60, 0.2), 0 4px 6px -4px rgba(251, 146, 60, 0.2);
    }

    .color-selected {
      border-color: #f97316;
      /* Tailwind's orange-500 */
      border-width: 1px;
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
      outline-width: 1px;
      outline-color: #f97316;
      /* Tailwind's orange-500 */
      outline-offset: 1px;
    }

    /* Mobile Swiper Hide Navigation */
    @media (max-width: 768px) {

      .related-swiper .swiper-button-next,
      .related-swiper .swiper-button-prev {
        display: none;
      }
    }

    /* ==== COLOR SELECTION SECTION SPACING ==== */

    /* Section Header Spacing */
    .color-section-header {
      margin-bottom: 1.5rem;
      /* 24px */
      padding-bottom: 0.5rem;
      border-bottom: 1px solid #f3f4f6;
    }

    /* Color Grid Container */
    .color-grid-container {
      background-color: #f9fafb;
      /* gray-50 */
      border-radius: 0.75rem;
      /* 12px */
      padding: 1rem;
      /* 16px on mobile */
      margin-bottom: 1rem;
      /* 16px */
      border: 1px solid #e5e7eb;
      /* gray-200 */
    }

    @media (min-width: 1024px) {
      .color-grid-container {
        padding: 1.5rem;
        /* 24px on desktop */
      }
    }

    /* Individual Color Box Spacing */
    .color-selection-box {
      padding: 1rem;
      /* 16px internal padding */
      margin-bottom: 0.75rem;
      /* 12px between boxes */
      border: 2px solid #e5e7eb;
      /* gray-200 */
      border-radius: 0.5rem;
      /* 8px */
      background-color: #ffffff;
      transition: all 0.3s ease;
    }

    .color-selection-box:hover {
      border-color: #fed7aa;
      /* orange-200 */
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transform: translateY(-1px);
    }

    /* Color Square Section */
    .color-square {
      width: 3.5rem;
      /* 56px */
      height: 3.5rem;
      /* 56px */
      border-radius: 0.5rem;
      /* 8px */
      border: 2px solid #e5e7eb;
      margin-right: 1rem;
      /* 16px gap between color and text */
      flex-shrink: 0;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    @media (min-width: 1024px) {
      .color-square {
        width: 4rem;
        /* 64px */
        height: 4rem;
        /* 64px */
      }
    }

    /* Color Details Section */
    .color-details {
      flex: 1;
      min-width: 0;
      padding-right: 0.75rem;
      /* 12px gap between details and indicator */
    }

    .color-name {
      font-size: 0.875rem;
      /* 14px */
      font-weight: 600;
      color: #374151;
      /* gray-700 */
      margin-bottom: 0.25rem;
      /* 4px */
      line-height: 1.25;
    }

    @media (min-width: 1024px) {
      .color-name {
        font-size: 1rem;
        /* 16px */
      }
    }

    .color-price {
      font-size: 0.75rem;
      /* 12px */
      font-weight: 600;
    }

    @media (min-width: 1024px) {
      .color-price {
        font-size: 0.875rem;
        /* 14px */
      }
    }

    /* Selection Indicator Section */
    .selection-indicator {
      width: 1.5rem;
      height: 1.5rem;
      background-color: #f97316;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: all 0.3s ease;
      flex-shrink: 0;
    }

    /* Selected State */
    .color-selected {

      background-color: #fff7ed;
      /* orange-50 */
      box-shadow: 0 10px 15px -3px rgba(249, 115, 22, 0.1), 0 4px 6px -4px rgba(249, 115, 22, 0.1);
    }

    .color-selected .selection-indicator {
      opacity: 1;
      transform: scale(1.1);
    }

    .color-selected .color-square {
      border-color: #f97316;
      /* orange-500 */
      box-shadow: 0 4px 6px -1px rgba(249, 115, 22, 0.2);
    }

    /* Info Box Section */
    .color-info-box {
      margin-top: 1rem;
      /* 16px */
      padding: 1rem;
      /* 16px */
      background-color: #ffffff;
      border: 1px solid #e5e7eb;
      /* gray-200 */
      border-radius: 0.5rem;
      /* 8px */
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* ==== MAIN ISSUES AND FIXES ==== */

    /* 1. SWIPER SLIDE OVERLAP FIX */
    .colorSwiper .swiper-slide {
      width: auto;
      min-width: 280px;
      /* Was causing overlap - reduce this */
      margin-right: 0.75rem;
      /* Reduce margin */
      flex-shrink: 0;
      /* Prevent shrinking */
    }

    /* Better responsive widths */
    @media (max-width: 640px) {
      .colorSwiper .swiper-slide {
        min-width: 260px;
        margin-right: 0.5rem;
      }
    }

    @media (max-width: 480px) {
      .colorSwiper .swiper-slide {
        min-width: 240px;
        margin-right: 0.25rem;
      }
    }

    /* 2. COLOR BUTTON CLICKABILITY FIX */
    .color-btn {
      /* Ensure proper button behavior */
      display: block;
      width: 100%;
      background: transparent;
      cursor: pointer;
      outline: none;
      position: relative;
      z-index: 10;
      min-height: 80px;
      padding: 1rem;
      border-radius: 0.75rem;
      transition: all 0.2s ease;
    }

    .color-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .color-btn:active {
      transform: translateY(0);
    }

    /* 3. COLOR SELECTION BOX FIX */
    .color-selection-box {
      padding: 0;
      margin-bottom: 0;
      border: 1px solid #e5e7eb;
      border-radius: 0.75rem;
      background-color: #ffffff;
      transition: all 0.2s ease;
      overflow: hidden;
      /* Prevent content overflow */
    }

    /* 4. SWIPER CONTAINER FIXES */
    .colorSwiper {
      padding: 0.5rem 0;
      overflow: visible;
      /* Allow content to be clickable */
    }

    /* Ensure swiper wrapper doesn't block clicks */
    .colorSwiper .swiper-wrapper {
      position: relative;
      z-index: 1;
    }

    /* 5. NAVIGATION BUTTON POSITIONING FIX */
    .color-swiper-prev,
    .color-swiper-next {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 20;
      pointer-events: auto;
      width: 32px;
      height: 32px;
    }

    .color-swiper-prev {
      left: 0.5rem;
    }

    .color-swiper-next {
      right: 0.5rem;
    }

    /* 6. SELECTION STATE FIXES */
    .color-selected {
      border-color: #f97316 !important;
      background-color: #fff7ed !important;
      box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2) !important;
      transform: scale(1.02) !important;
    }

    .color-selected .selection-indicator {
      opacity: 1 !important;
      transform: scale(1.1) !important;
    }

    /* 7. TOUCH TARGET IMPROVEMENTS */
    @media (max-width: 768px) {
      .color-btn {
        min-height: 90px;
        /* Larger touch target on mobile */
        padding: 1.25rem;
      }

      .color-square {
        width: 3rem;
        height: 3rem;
        margin-right: 0.75rem;
      }
    }

    /* 8. PREVENT DOUBLE-TAP ZOOM ON MOBILE */
    .color-btn {
      touch-action: manipulation;
      -webkit-tap-highlight-color: transparent;
    }

    /* 9. ENSURE PROPER LAYERING */
    .color-grid-container {
      position: relative;
      z-index: 1;
    }

    /* 10. FIX FLEX LAYOUT ISSUES */
    .color-btn>div {
      pointer-events: none;
      /* Let clicks pass through to button */
    }

    .color-details,
    .color-square,
    .selection-indicator {
      pointer-events: none;

    }

    /* Better visual feedback */
    .color-btn:focus {
      outline: 1px solid #f97316;
      outline-offset: 1px;
    }

    /* Ensure content doesn't overflow on small screens */
    .color-name {
      word-break: break-word;
      overflow-wrap: break-word;
    }

    /* Improve selection indicator visibility */
    .selection-indicator {
      background-color: #f97316;
      border-radius: 50%;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* Better hover states for desktop */
    @media (min-width: 1024px) {
      .color-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
      }
    }
  </style>
</head>

<body class="font-roboto">
  <?php include '../navbar/top.php'; ?>

  <!-- Hero Section with Bouncing Bubbles Background -->
  <div class="bg-black text-white py-6 sm:py-7 lg:py-8 relative overflow-hidden">
    <style>

      /* Magnifier Container */
      #magnifier-container {
        position: relative;
        cursor: crosshair;
      }

      /* Tracking Lens - Hidden by default */
      #magnifier-lens {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        transition: opacity 0.15s ease;
        opacity: 0;
        visibility: hidden;
      }

      #magnifier-lens.active {
        opacity: 1;
        visibility: visible;
      }

      /* Zoom Preview Panel - Hidden by default */
      #zoom-preview-panel {
        transition: opacity 0.2s ease, transform 0.2s ease;
        opacity: 0;
        visibility: hidden;
        transform: translateX(-10px);
      }

      #zoom-preview-panel.active {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
      }

      /* Mobile: Full screen modal */
      @media (max-width: 1023px) {
        #zoom-preview-panel {
          position: fixed !important;
          top: 50% !important;
          left: 50% !important;
          transform: translate(-50%, -50%) !important;
          width: 90vw !important;
          height: 90vw !important;
          max-width: 500px;
          max-height: 500px;
          z-index: 9999 !important;
          margin: 0 !important;
        }

        #zoom-preview-panel.active {
          transform: translate(-50%, -50%) !important;
        }

        /* Add backdrop for mobile */
        #zoom-preview-panel::before {
          content: '';
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.5);
          z-index: -1;
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
  <div class="container mx-auto">
    <div class="bg-white rounded-xl overflow-hidden">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">

        <!-- PRODUCT IMAGE & INFO SECTION -->
        <div class="p-4 lg:p-8">
          <div class="relative">
            <!-- Product Image Container -->
            <div class="aspect-square mb-4 relative bg-gray-50 rounded-lg overflow-hidden 
         w-48 h-48 sm:w-64 sm:h-64 md:w-80 md:h-80 lg:w-full lg:h-auto mx-auto lg:mx-0"
              id="magnifier-container">

              <img id="main-product-image"
                src="../../<?= htmlspecialchars($display_image) ?>"
                data-original-image="../../<?= htmlspecialchars($display_image) ?>"
                data-original-name="<?= htmlspecialchars($display_name) ?>"
                class="w-full h-full object-contain transition-all duration-300"
                alt="<?= htmlspecialchars($display_name) ?>">

              <!-- Magnifier Lens - Hidden by default -->
              <div id="magnifier-lens"
                class="absolute hidden pointer-events-none bg-white/30 backdrop-blur-sm border-2 border-orange-400"
                style="width: 100px; height: 100px;"></div>
            </div>

            <!-- Zoom Preview Panel - Hidden by default -->
            <div id="zoom-preview-panel"
              class="hidden absolute top-0 left-full ml-6 w-96 h-96 z-50">
              <div class="relative w-full h-full bg-gray-50 rounded-lg shadow-2xl overflow-hidden border-2 border-gray-200">
                <div id="zoom-preview-content"
                  class="w-full h-full bg-no-repeat"
                  style="background-size: 250%;"></div>

                <div class="absolute top-3 left-3 bg-black/70 text-white text-xs px-3 py-1.5 rounded-full">
                  <i class="fas fa-search-plus mr-1"></i> 2.5x Zoom
                </div>
              </div>
            </div>
          </div>

          <!-- Thumbnail Gallery -->
          <?php if (!empty($sub_images)): ?>
            <div class="thumbnail-gallery mt-3">
              <div class="thumbnail-container overflow-x-auto scrollbar-hide">
                <div class="flex gap-1 sm:gap-2 pb-2 justify-center lg:justify-start">

                  <!-- Main Thumbnail -->
                  <div class="thumbnail-item cursor-pointer flex-shrink-0" data-index="0">
                    <img src="../../<?= htmlspecialchars($display_image) ?>" loading="lazy"
                      class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 object-contain rounded-lg border-2 border-transparent hover:border-blue-500 transition-all duration-200 thumbnail-active"
                      alt="Main Image">
                  </div>

                  <!-- Sub Images Thumbnails -->
                  <?php foreach ($sub_images as $index => $sub_image): ?>
                    <div class="thumbnail-item cursor-pointer flex-shrink-0" data-index="<?= $index + 1 ?>">
                      <img src="../<?= htmlspecialchars($sub_image) ?>" loading="lazy"
                        class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 object-contain rounded-lg border-2 border-transparent hover:border-blue-500 transition-all duration-200"
                        alt="Sub Image <?= $index + 1 ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Product Info Section -->
          <div class="space-y-4 lg:space-y-6">
            <!-- Customer Rating -->
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

            <!-- Product Name & Price Display -->
            <div>
              <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-2 mb-2">
                <h1 class="text-xl sm:text-2xl lg:text-3xl text-orange-600">
                  <?php
                  // ✅ USE ORIGINAL_PRODUCT if available, fallback to $product
                  $safe_product = isset($ORIGINAL_PRODUCT) ? $ORIGINAL_PRODUCT : $product;
                  echo htmlspecialchars($display_name ?? $safe_product['product_name'] ?? 'Product');
                  ?>
                </h1>

                <!-- Dynamic Price Display -->
                <div id="product-price-display" class="hidden">
                  <div class="text-right">
                    <div id="original-price-container" class="hidden">
                      <span class="text-sm text-gray-500 line-through" id="original-price">₱0.00</span>
                    </div>
                    <div class="text-2xl lg:text-3xl text-black" id="final-price">₱0.00</div>
                    <div id="discount-badge" class="hidden mt-1">
                      <span class="inline-block bg-red-500 text-white text-xs px-2 py-1 rounded-full font-semibold">
                        <span id="discount-percent">0</span>% OFF
                      </span>
                    </div>
                    <div id="selected-size-info" class="text-xs text-gray-500 mt-1">
                      Size: <span class="font-semibold" id="selected-size-text">-</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 mb-3">
                <span class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-medium">
                  <?php
                  $safe_product = isset($ORIGINAL_PRODUCT) ? $ORIGINAL_PRODUCT : $product;
                  echo htmlspecialchars($safe_product['codename']);
                  ?>
                </span>
              </div>
            </div>

            <!-- Product Description -->
            <div>

              <p class="text-gray-700 leading-relaxed text-sm lg:text-base">
                <?= htmlspecialchars($safe_product['description'] ?? 'No description available.') ?>
              </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 pt-4">
              <?php $safe_product = isset($ORIGINAL_PRODUCT) ? $ORIGINAL_PRODUCT : $product; ?>
              <a href="product_info.php?id=<?= $safe_product['id'] ?>"
                class="flex-1 bg-black hover:bg-orange-600 text-white text-center px-4 py-3 font-medium transition-colors">
                <i class="fas fa-info-circle mr-2"></i>View Details
              </a>
              <button onclick="shareProduct()"
                class="flex-1 bg-black hover:bg-gray-600 text-white px-4 py-3 font-medium transition-colors">
                <i class="fas fa-share-alt mr-2"></i>Share
              </button>
            </div>
          </div>
        </div>
        <!-- Mobile Sidebar Toggle Button (Smaller Version) -->
        <button id="mobileSidebarToggle"
          class="lg:hidden fixed bottom-3 right-3 z-[90] bg-black text-white px-4 py-2 text-sm rounded-full shadow-md hover:bg-orange-600 transition-all active:scale-95">
          <i class="fas fa-shopping-cart text-xs lg:text-base"></i>
          <span>Add to Cart</span>
        </button>


        <!-- Overlay for mobile sidebar -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-[100] hidden lg:hidden"></div>

        <!-- Product Options Section - Sidebar on mobile, normal on desktop -->
        <div id="productOptionsContainer"
          class="fixed lg:relative top-0 right-0 h-full lg:h-auto w-full sm:w-80 lg:w-full 
         transform translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out
         z-[101] lg:z-auto bg-white lg:bg-white shadow-xl lg:shadow-none overflow-y-auto">

          <!-- Mobile Sidebar Header -->
          <div class="lg:hidden sticky top-0 bg-white border-b border-gray-200 p-4 flex items-center justify-between z-20 shadow-sm bg-orange-500">
            <h2 class="text-lg text-white">Product Options</h2>
            <button id="closeSidebar" class="text-white hover:text-white p-1">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>


          <div class="p-4 lg:p-8 flex flex-col">

            <!-- STEP 1: TYPE SELECTION FIRST -->
            <?php if (!empty($types_data)): ?>
              <div class="mb-6 lg:mb-10">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base lg:text-xl  text-gray-800">Click Item</h3>
                  <div class="text-xs lg:text-sm text-gray-500">Required</div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                  <?php foreach ($types_data as $index => $type): ?>
                    <button type="button"
                      onclick="showVariants(<?= $type['id'] ?>, '<?= addslashes($type['name']) ?>')"
                      class="type-btn border border-gray-300 p-2 hover:border-orange-500 transition-all duration-200 bg-white rounded focus:outline-none focus:ring-2 focus:ring-orange-300">

                      <div class="aspect-square mb-1.5 overflow-hidden bg-gray-50 flex items-center justify-center relative rounded">
                        <?php if (!empty($type['image']) && file_exists("../../" . $type['image'])): ?>
                          <img src="../../<?= htmlspecialchars($type['image']) ?>"
                            class="w-full h-full object-contain"
                            alt="<?= htmlspecialchars($type['name']) ?>"
                            loading="lazy"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                          <div class="w-full h-full flex items-center justify-center text-gray-300" style="display: none;">
                            <i class="fas fa-image text-lg"></i>
                          </div>
                        <?php else: ?>
                          <div class="w-full h-full flex items-center justify-center text-gray-300">
                            <i class="fas fa-image text-lg"></i>
                          </div>
                        <?php endif; ?>
                      </div>

                      <span class="text-[10px] font-medium text-gray-700 block truncate leading-tight uppercase text-center">
                        <?= htmlspecialchars($type['name']) ?>
                      </span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- STEP 2: COLOR SELECTION -->
            <?php if (!empty($product_colors)): ?>
              <div class="mb-6 lg:mb-10" id="color-selection-section">
                <div class="flex items-center justify-between mb-4">
                  <!-- Product Image - Mobile Sidebar Only -->
                  <h3 class="text-base lg:text-xl  text-gray-800">Choose type</h3>
                  <div class="text-xs lg:text-sm text-gray-500">Required</div>
                  <div class="lg:hidden py-3 px-0 bg-white border-b border-gray-100">
                    <!-- Product Image - Mobile Sidebar Only -->
                    <div class="lg:hidden py-3 px-0 bg-white border-b border-gray-100">
                      <div class="aspect-square w-32 mx-auto overflow-hidden">
                        <h1 class="text-center">Display</h1>
                        <!-- ✅ UPDATED: Sidebar image also uses first type image -->
                        <img id="sidebar-product-image"
                          src="../../<?= htmlspecialchars($display_image) ?>"
                          class="w-full h-full object-contain"
                          alt="<?= htmlspecialchars($product['product_name']) ?>">
                      </div>
                      <h3 class="text-center mt-2 font-semibold text-gray-800 text-xs line-clamp-2">
                        <?= htmlspecialchars($product['product_name']) ?>
                      </h3>
                    </div>
                    <h3 class="text-center mt-2 font-semibold text-gray-800 text-xs line-clamp-2">
                      <?= htmlspecialchars($product['product_name']) ?>

                    </h3>
                  </div>
                </div>

                <div id="color-selection-container" class="opacity-50 pointer-events-none">
                  <div class="p-3">
                    <div class="max-h-60 lg:max-h-80 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 hover:scrollbar-thumb-gray-400 transition-all duration-300">
                      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 p-1" id="all-colors-grid">
                        <?php foreach ($product_colors as $color): ?>
                          <button type="button"
                            onclick="selectColorFromGrid(<?= $color['id'] ?>, '<?= addslashes($color['color_name']) ?>', <?= $color['price'] ?>, '<?= !empty($color['image']) ? htmlspecialchars($color['image']) : '' ?>', '<?= htmlspecialchars($color['color_code']) ?>')"
                            class="color-btn border border-black hover:border-orange-500 bg-white rounded
                           px-2 py-1.5 text-xs transition-all duration-200 text-center
                           disabled:opacity-50 disabled:cursor-not-allowed w-full min-h-[32px]"
                            data-color-id="<?= $color['id'] ?>"
                            data-color-name="<?= addslashes($color['color_name']) ?>"
                            data-price="<?= $color['price'] ?>"
                            data-color-code="<?= htmlspecialchars($color['color_code']) ?>"
                            data-image="<?= !empty($color['image']) ? htmlspecialchars($color['image']) : '' ?>"
                            disabled>
                            <span class="text-black block truncate text-[10px] lg:text-[11px] leading-tight uppercase"><?= htmlspecialchars($color['color_name']) ?></span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div id="color-disabled-message" class="text-center p-4 lg:p-6 bg-white border-2 border-dashed border-gray-300 rounded-lg">
                  <i class="fas fa-arrow-up text-orange-500 mb-2 text-lg lg:text-xl"></i>
                  <p class="text-sm lg:text-base text-gray-500">Please select an item type first</p>
                </div>
              </div>
            <?php endif; ?>

            <!-- STEP 3: SIZE/VARIANT SELECTION -->
            <div class="mb-6 lg:mb-8" id="size-selection-section">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-base lg:text-xl text-gray-800">Choose Size</h3>
                <div class="text-xs lg:text-sm text-gray-500">Required</div>
              </div>

              <div id="variant-container" class="text-gray-500 p-4 bg-white text-center rounded-lg">
                <i class="fas fa-arrow-up text-orange-500 mb-2 text-lg lg:text-xl"></i>
                <p class="text-sm lg:text-base">Please select a color first</p>
              </div>

              <?php foreach ($types_data as $type): ?>
                <div id="variants-<?= $type['id'] ?>" class="variant-group hidden">
                  <?php if (!empty($type['variants'])): ?>
                    <div class="max-h-60 lg:max-h-72 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 hover:scrollbar-thumb-gray-400 transition-all duration-300 pr-1 p-3">
                      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                        <?php foreach ($type['variants'] as $variant): ?>
                          <?php
                          $price = floatval($variant['variant_price']);
                          $percent = floatval($variant['percent']);
                          $discount = floatval($variant['discount'] ?? 0);
                          $priceWithMarkup = $price + ($price * $percent / 100);
                          $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

                          // Parse SKU info
                          $sku_info = !empty($variant['sku_info']) ? json_decode($variant['sku_info'], true) : null;
                          ?>
                          <button type="button"
                            onclick="selectVariant(this, '<?= addslashes($variant['size']) ?>'); showSkuInfo(this);"
                            class="variant-btn border border-gray-300 hover:border-orange-500 bg-white rounded
                       px-2 py-2 text-center transition-all duration-200 min-h-[40px] flex items-center justify-center"
                            data-price="<?= $price ?>"
                            data-percent="<?= $percent ?>"
                            data-discount="<?= $discount ?>"
                            data-variant-id="<?= $variant['variant_id'] ?>"
                            data-sku-info='<?= $sku_info ? htmlspecialchars(json_encode($sku_info), ENT_QUOTES) : '' ?>'>
                            <div class="text-gray-700 text-[11px] font-medium leading-tight">
                              <?= htmlspecialchars($variant['size']) ?>
                            </div>
                            <span class="hidden" data-original-price="<?= $priceWithMarkup ?>" data-final-price="<?= $finalPrice ?>" data-discount-percent="<?= $discount ?>"></span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <p class="text-gray-500 text-center p-4 text-sm">No variants available for this type.</p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>

              <!-- SKU Info Display Section (Below size buttons) -->
              <div id="sku-info-display" class="hidden mt-4 p-2 lg:p-4 bg-gray-50 border border-gray-200">
                <div class="flex items-start justify-between mb-3">
                  <div class="flex items-center gap-2">
                    <h4 class="text-base lg:text-lg text-black font-semibold">Details</h4>
                  </div>
                  <button onclick="hideSkuInfo()" class="text-black hover:text-gray-600 transition">
                    <i class="fas fa-times text-lg"></i>
                  </button>
                </div>

                <!-- Content container with collapse functionality -->
                <div id="sku-info-content-wrapper">
                  <div id="sku-info-content" class="text-sm text-black transition-all duration-300 overflow-hidden">
                    <!-- Content will be inserted here -->
                  </div>

                  <!-- See More / See Less button -->
                  <button id="toggle-sku-btn" onclick="toggleSkuContent()" class="hidden mt-3 text-orange-600 hover:text-orange-700 font-medium text-sm flex items-center gap-1 transition">
                    <span id="toggle-sku-text">See More</span>
                    <i id="toggle-sku-icon" class="fas fa-chevron-down text-xs"></i>
                  </button>
                </div>
              </div>
            </div>

            <style>
              /* Custom scrollbar styling */
              .scrollbar-thin::-webkit-scrollbar {
                width: 6px;
              }

              .scrollbar-thin::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
              }

              .scrollbar-thin::-webkit-scrollbar-thumb {
                background: #d1d5db;
                border-radius: 10px;
              }

              .scrollbar-thin::-webkit-scrollbar-thumb:hover {
                background: #9ca3af;
              }

              /* Smooth slide animation */
              #sku-info-display {
                animation: slideDown 0.3s ease-out;
              }

              @keyframes slideDown {
                from {
                  opacity: 0;
                  transform: translateY(-10px);
                }

                to {
                  opacity: 1;
                  transform: translateY(0);
                }
              }

              /* Collapsed state */
              #sku-info-content.collapsed {
                max-height: 120px;
                overflow: hidden;
                position: relative;
              }

              #sku-info-content.collapsed::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 40px;
                background: linear-gradient(to bottom, transparent, #f9fafb);
                pointer-events: none;
              }

              #sku-info-content.expanded {
                max-height: none;
              }
            </style>

            <script>
              let isExpanded = false;

              function showSkuInfo(button) {
                const display = document.getElementById('sku-info-display');
                const content = document.getElementById('sku-info-content');
                const toggleBtn = document.getElementById('toggle-sku-btn');
                const skuInfoJson = button.getAttribute('data-sku-info');

                // Reset state
                isExpanded = false;
                content.classList.remove('collapsed', 'expanded');
                toggleBtn.classList.add('hidden');

                // If no SKU info, hide the display
                if (!skuInfoJson) {
                  display.classList.add('hidden');
                  return;
                }

                try {
                  const skuInfo = JSON.parse(skuInfoJson);

                  // Build content HTML
                  let html = '';

                  // Check if it's plain text (only has 'notes' field)
                  if (Object.keys(skuInfo).length === 1 && skuInfo.notes) {
                    html = `
        <div class="bg-white p-4 rounded-lg">
          <div class="whitespace-pre-wrap text-gray-800">${escapeHtml(skuInfo.notes)}</div>
        </div>
      `;
                  } else {
                    // Structured data display as list
                    html = '<div class="bg-white p-4 rounded-lg"><div class="space-y-3">';

                    for (const [key, value] of Object.entries(skuInfo)) {
                      const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                      html += `
          <div class="flex items-start border-b border-gray-100 pb-2 last:border-0 last:pb-0">
            <span class="text-sm font-semibold text-orange-600 min-w-[120px]">${escapeHtml(label)}:</span>
            <span class="text-sm text-gray-800 flex-1">${escapeHtml(value)}</span>
          </div>
        `;
                    }

                    html += '</div></div>';
                  }

                  content.innerHTML = html;
                  display.classList.remove('hidden');

                  // Check if content height exceeds 120px and show toggle button
                  setTimeout(() => {
                    if (content.scrollHeight > 120) {
                      content.classList.add('collapsed');
                      toggleBtn.classList.remove('hidden');
                      updateToggleButton();
                    }
                  }, 50);

                } catch (e) {
                  console.error('Error parsing SKU info:', e);
                  display.classList.add('hidden');
                }
              }

              function toggleSkuContent() {
                const content = document.getElementById('sku-info-content');
                isExpanded = !isExpanded;

                if (isExpanded) {
                  content.classList.remove('collapsed');
                  content.classList.add('expanded');
                } else {
                  content.classList.remove('expanded');
                  content.classList.add('collapsed');
                }

                updateToggleButton();
              }

              function updateToggleButton() {
                const toggleText = document.getElementById('toggle-sku-text');
                const toggleIcon = document.getElementById('toggle-sku-icon');

                if (isExpanded) {
                  toggleText.textContent = 'See Less';
                  toggleIcon.classList.remove('fa-chevron-down');
                  toggleIcon.classList.add('fa-chevron-up');
                } else {
                  toggleText.textContent = 'See More';
                  toggleIcon.classList.remove('fa-chevron-up');
                  toggleIcon.classList.add('fa-chevron-down');
                }
              }

              function hideSkuInfo() {
                const display = document.getElementById('sku-info-display');
                display.classList.add('hidden');
                isExpanded = false;
              }

              function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
              }
            </script>
            <!-- PURCHASE SECTION -->
            <div class="mt-4 sticky bottom-0 lg:relative bg-white lg:bg-transparent pt-4 lg:pt-0 border-t lg:border-0 border-gray-200 z-10 shadow-lg lg:shadow-none p-4">
              <form id="productForm" method="POST" class="space-y-3 lg:space-y-4">
                <input type="hidden" name="product_id" value="<?= $product_id ?>" />
                <input type="hidden" name="selected_color_id" id="selected_color_id">
                <input type="hidden" name="selected_color" id="selected_color">
                <input type="hidden" name="selected_type" id="selected_type">
                <input type="hidden" name="selected_variant" id="selected_variant">
                <input type="hidden" name="variant_id" id="variant_id">
                <input type="hidden" name="is_windows" value="<?= $is_windows_category ? '1' : '0' ?>" />

                <!-- Total Price Display -->
                <div class="bg-gradient-to-r from-green-50 to-blue-50 p-3 lg:p-4 border border-green-200">
                  <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                    <div>
                      <p class="text-xs lg:text-sm text-gray-600 mb-1">Total Price</p>
                      <p id="totalPrice" class="text-xl lg:text-3xl text-green-600">₱0.00</p>
                    </div>
                    <div id="selectionStatus" class="text-xs lg:text-sm text-gray-500 sm:text-right">
                      Follow steps 1-3 to see total price
                    </div>
                  </div>
                </div>

                <?php if ($is_windows_category): ?>
                  <button type="button" id="contactUsBtn" onclick="openContactModal()"
                    disabled
                    class="w-full py-3 lg:py-4 text-sm lg:text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75 rounded-lg">
                    <span id="contactBtnText" class="flex items-center justify-center gap-2">
                      <i class="fas fa-phone"></i>
                      Complete Steps 1-3 to Contact Us
                    </span>
                  </button>
                <?php else: ?>
                  <div class="flex gap-2 lg:gap-3 w-full">
                    <button type="submit" id="addToCartBtn"
                      disabled
                      class="flex-1 py-2.5 lg:py-4  text-xs lg:text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75 ">
                      <span id="btnText" class="flex items-center justify-center gap-1 lg:gap-2">
                        <i class="fas fa-shopping-cart text-xs lg:text-base"></i>
                        Add to Cart
                      </span>
                    </button>

                    <button type="button" onclick="window.location.href='index-cart_view-page-8.php'"
                      class="flex-1 py-2.5 lg:py-4  text-xs lg:text-lg transition-all duration-300 bg-black hover:bg-orange-500 text-white ">
                      <span class="flex items-center justify-center gap-1 lg:gap-2">
                        <i class="fas fa-shopping-cart text-xs lg:text-base"></i>
                        View Cart
                      </span>
                    </button>
                  </div>
                <?php endif; ?>
              </form>
            </div>
          </div>
        </div>

        <!-- Contact Modal -->
        <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
          <div class="bg-white rounded-xl p-6 lg:p-8 max-w-md w-full mx-4 relative">
            <button onclick="closeContactModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>

            <div class="mb-6">
              <h3 class="text-xl lg:text-2xl font-bold text-gray-800 mb-2">Contact Us for Quote</h3>
              <p class="text-gray-600 text-sm">Get a personalized quote for your selected windows.</p>
            </div>

            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
              <h4 class="font-semibold text-gray-700 mb-2">Product: <?= htmlspecialchars($product['product_name']) ?></h4>
              <div id="selectedOptionsText" class="text-sm text-gray-600 mb-2">No specific options selected</div>
              <div id="selectedPriceText" class="text-lg font-bold text-green-600"></div>
            </div>

            <div class="space-y-4">
              <a id="emailLink"
                href="mailto:noblehomeconst.ph@gmail.com?subject=Windows Quote Request&body=Hi, I'm interested in getting a quote."
                class="block w-full bg-orange-500 hover:bg-orange-600 text-white text-center px-4 py-3 rounded-lg font-medium transition-colors">
                <i class="fas fa-envelope mr-2"></i>Send Email Quote Request
              </a>

              <div class="text-center">
                <p class="text-sm text-gray-600 mb-2">Or call us directly:</p>
                <a href="tel:+639922394563" class="text-orange-500 font-semibold text-lg hover:text-orange-600 transition-colors">
                  <i class="fas fa-phone mr-2"></i>(02) 8822-1295 / +63992-239-4563
                </a>
              </div>

              <a href="https://wa.me/639922394563" target="_blank"
                class="block w-full bg-green-500 hover:bg-green-600 text-white text-center px-4 py-3 rounded-lg font-medium transition-colors">
                <i class="fab fa-whatsapp mr-2"></i>Chat on WhatsApp
              </a>
            </div>

            <div class="mt-6 text-center text-sm text-gray-500">
              <p>We'll get back to you within 24 hours with detailed pricing and installation information.</p>
            </div>
          </div>
        </div>

        <style>
          /* Sidebar animations */
          #productOptionsContainer.sidebar-open {
            transform: translateX(0);
          }

          /* Button styles */
          .type-btn,
          .color-btn,
          .variant-btn {
            position: relative;
            transition: all 0.2s ease;
          }

          .type-btn:hover,
          .color-btn:hover,
          .variant-btn:hover {
            transform: translateY(-1px);
          }

          .type-btn:active,
          .color-btn:active,
          .variant-btn:active {
            transform: translateY(0);
          }

          /* Selected states */
          .type-btn.selected,
          .color-btn.selected,
          .variant-btn.selected {
            border-color: #f97316 !important;
            background-color: #fff7ed !important;
          }

          .variant-btn.selected .text-gray-700,
          .color-btn.selected span {
            color: #000000 !important;
            font-weight: 600;
          }

          /* Custom scrollbar */
          .scrollbar-thin {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db #f9fafb;
          }

          .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
          }

          .scrollbar-thin::-webkit-scrollbar-track {
            background: #f9fafb;
            border-radius: 3px;
          }

          .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
          }

          .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
          }

          /* Modal animations */
          #contactModal {
            animation: modalFadeIn 0.3s ease-out;
          }

          @keyframes modalFadeIn {
            from {
              opacity: 0;
              transform: scale(0.95);
            }

            to {
              opacity: 1;
              transform: scale(1);
            }
          }

          #contactModal>div {
            transform: translateY(-20px);
            animation: slideUp 0.3s ease-out forwards;
          }

          @keyframes slideUp {
            to {
              transform: translateY(0);
            }
          }
        </style>



        <?php if (!empty($product_specs)): ?>
          <section class="mt-6 lg:mt-8">
            <div class="bg-white rounded-xl p-4 lg:p-8">
              <h2 class="text-xl sm:text-2xl lg:text-3xl text-black mb-4 lg:mb-6 flex items-center gap-3">
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
                          <dd class="text-black text-sm lg:text-base"><?= htmlspecialchars($product_specs[$key]) ?></dd>
                        </div>
                    <?php endif;
                    endfor; ?>
                  </dl>
                </div>
              </div>
            </div>
          </section>
        <?php endif; ?>


        <?php if ($related_products->num_rows > 0): ?>
          <!-- Mobile Bottom Bar Trigger (Smaller & Side Floating) -->
          <button id="relatedProductsTrigger"
            class="lg:hidden fixed bottom-20 right-4 z-[80] bg-black text-white px-4 py-2 text-sm rounded-full shadow-md hover:shadow-lg transition-all active:scale-95 flex items-center gap-1">
            <i class="fas fa-th-large text-sm"></i>
            <span>Related (<?= $related_products->num_rows ?>)</span>
          </button>

          <!-- Desktop Sidebar Trigger (Fixed on right side) -->
          <button id="desktopSidebarTrigger"
            class="hidden lg:flex fixed right-0 top-1/2 -translate-y-1/2 z-[80] bg-black text-white px-3 py-6 rounded-l-lg shadow-lg hover:bg-gray-200 hover:text-black transition-all hover:px-4 flex-col items-center gap-2 group">
            <span class="text-xs writing-mode-vertical transform rotate-180">Related Products</span>
            <span class="text-xs bg-white text-orange-600 rounded-full w-6 h-6 flex items-center justify-center "><?= $related_products->num_rows ?></span>
          </button>

          <!-- Overlay for sidebars -->
          <div id="relatedOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-[110] hidden transition-opacity duration-300"></div>

          <!-- Related Products Section - Bottom Sheet on Mobile, Right Sidebar on Desktop -->
          <section id="relatedProductsContainer"
            class="fixed bottom-0 lg:top-0 lg:bottom-auto left-0 lg:left-auto right-0 lg:right-0 
           lg:h-full lg:w-80
           transform translate-y-full lg:translate-y-0 lg:translate-x-full
           transition-transform duration-300 ease-out
           z-[111] bg-white 
           shadow-2xl rounded-t-3xl lg:rounded-none 
           max-h-[80vh] lg:max-h-full overflow-hidden flex flex-col">

            <!-- Header (Mobile & Desktop) -->
            <div class="sticky top-0 bg-black text-white px-4 py-3 flex items-center justify-between z-20 shadow-md">
              <div>
                <h2 class="text-base lg:text-lg">Related Products</h2>
                <p class="text-xs text-white">Similar items you may like</p>
              </div>
              <button id="closeRelatedProducts" class="text-white hover:bg-white/20 p-2 rounded-full transition-colors">
                <i class="fas fa-times text-lg"></i>
              </button>
            </div>

            <!-- Products Grid (Scrollable) -->
            <div class="overflow-y-auto flex-1 p-3 bg-gray-50">
              <div class="grid grid-cols-2 lg:grid-cols-1 gap-3">
                <?php
                // Reset the result pointer to iterate again
                $related_products->data_seek(0);
                while ($row = $related_products->fetch_assoc()):
                ?>
                  <div class="group">
                    <a href="index-product_view-page-4-AA.php?id=<?= $row['id'] ?>"
                      class="block bg-white  hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 overflow-hidden h-full  hover:border-orange-300">

                      <!-- Product Image -->
                      <div class="relative overflow-hidden bg-gray-50" style="height: 140px;">
                        <?php if ($row['main_image']): ?>
                          <img src="../../<?= $row['main_image'] ?>"
                            loading="lazy"
                            class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-300"
                            alt="<?= htmlspecialchars($row['product_name']) ?>">
                        <?php else: ?>
                          <div class="flex flex-col items-center justify-center h-full text-gray-400">
                            <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-xs">No Image</span>
                          </div>
                        <?php endif; ?>
                      </div>

                      <!-- Product Information -->
                      <div class="p-2.5">
                        <!-- Product Name -->
                        <h3 class=" text-gray-800 text-xs mb-1.5 line-clamp-2 leading-tight">
                          <?= htmlspecialchars($row['product_name']) ?>
                        </h3>

                        <!-- Product Description -->
                        <div class="mb-2">
                          <p class="text-gray-600 text-xs line-clamp-1 mb-1">
                            <?= htmlspecialchars($row['description']) ?>
                          </p>

                          <?php if (!empty($row['descrip6'])): ?>
                            <p class="text-gray-500 text-xs line-clamp-1">
                              • <?= htmlspecialchars($row['descrip6']) ?>
                            </p>
                          <?php endif; ?>
                        </div>

                        <!-- Product Code -->
                        <div class="flex items-center justify-between">
                          <span class="text-xs px-2 py-0.5 bg-black text-white">
                            <?= htmlspecialchars($row['codename']) ?>
                          </span>
                          <span class="text-xs text-gray-400">
                            <i class="fas fa-arrow-right"></i>
                          </span>
                        </div>
                      </div>
                    </a>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </section>

          <style>
            /* Sidebar animations */
            #relatedProductsContainer.sidebar-open {
              transform: translateX(0) translateY(0);
            }

            /* Smooth scrolling */
            #relatedProductsContainer {
              -webkit-overflow-scrolling: touch;
            }

            /* Custom scrollbar */
            #relatedProductsContainer::-webkit-scrollbar {
              width: 6px;
            }

            #relatedProductsContainer::-webkit-scrollbar-track {
              background: #f1f1f1;
            }

            #relatedProductsContainer::-webkit-scrollbar-thumb {
              background: #cbd5e0;
              border-radius: 3px;
            }

            #relatedProductsContainer::-webkit-scrollbar-thumb:hover {
              background: #a0aec0;
            }

            /* Prevent body scroll when sidebar is open */
            body.related-sidebar-open {
              overflow: hidden;
            }

            /* Vertical text for desktop trigger */
            .writing-mode-vertical {
              writing-mode: vertical-rl;
            }
          </style>

          <script>
            document.addEventListener('DOMContentLoaded', function() {
              const mobileTrigger = document.getElementById('relatedProductsTrigger');
              const desktopTrigger = document.getElementById('desktopSidebarTrigger');
              const closeBtn = document.getElementById('closeRelatedProducts');
              const overlay = document.getElementById('relatedOverlay');
              const container = document.getElementById('relatedProductsContainer');

              function openSidebar() {
                container.classList.add('sidebar-open');
                overlay.classList.remove('hidden');
                document.body.classList.add('related-sidebar-open');

                setTimeout(() => {
                  overlay.style.opacity = '1';
                }, 10);
              }

              function closeSidebar() {
                container.classList.remove('sidebar-open');
                overlay.style.opacity = '0';
                document.body.classList.remove('related-sidebar-open');

                setTimeout(() => {
                  overlay.classList.add('hidden');
                }, 300);
              }

              if (mobileTrigger) {
                mobileTrigger.addEventListener('click', openSidebar);
              }

              if (desktopTrigger) {
                desktopTrigger.addEventListener('click', openSidebar);
              }

              if (closeBtn) {
                closeBtn.addEventListener('click', closeSidebar);
              }

              if (overlay) {
                overlay.addEventListener('click', closeSidebar);
              }

              // Close on swipe down (mobile only)
              let touchStartY = 0;
              let touchEndY = 0;

              if (container) {
                container.addEventListener('touchstart', (e) => {
                  touchStartY = e.changedTouches[0].screenY;
                }, {
                  passive: true
                });

                container.addEventListener('touchend', (e) => {
                  touchEndY = e.changedTouches[0].screenY;
                  handleSwipe();
                }, {
                  passive: true
                });

                function handleSwipe() {
                  const scrollTop = container.querySelector('.overflow-y-auto').scrollTop;
                  if (scrollTop === 0 && touchEndY > touchStartY + 50) {
                    closeSidebar();
                  }
                }
              }

              // Close with Escape key
              document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && container.classList.contains('sidebar-open')) {
                  closeSidebar();
                }
              });
            });
          </script>
        <?php endif; ?>

      </div>
    </div>
  </div>


  <?php include '../navbar/footer.php'; ?>


  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const container = document.getElementById('magnifier-container');
      const img = document.getElementById('main-product-image');
      const lens = document.getElementById('magnifier-lens');
      const previewPanel = document.getElementById('zoom-preview-panel');
      const previewContent = document.getElementById('zoom-preview-content');

      if (!container || !img || !lens || !previewPanel || !previewContent) return;

      const zoomLevel = 2.5;
      let isActive = false;

      // Show lens and preview when mouse enters image
      container.addEventListener('mouseenter', function() {
        isActive = true;

        // Show lens and preview
        lens.classList.remove('hidden');
        lens.classList.add('active');
        previewPanel.classList.remove('hidden');
        previewPanel.classList.add('active');

        // Setup preview image
        if (img.complete) {
          setupPreview();
        } else {
          img.addEventListener('load', setupPreview);
        }
      });

      // Hide lens and preview when mouse leaves image
      container.addEventListener('mouseleave', function() {
        isActive = false;

        // Hide with animation
        lens.classList.remove('active');
        previewPanel.classList.remove('active');

        setTimeout(() => {
          lens.classList.add('hidden');
          previewPanel.classList.add('hidden');
        }, 200);
      });

      function setupPreview() {
        if (!isActive) return;
        previewContent.style.backgroundImage = `url('${img.src}')`;
      }

      // Track mouse movement inside image
      container.addEventListener('mousemove', function(e) {
        if (!isActive) return;

        const rect = container.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Position tracking lens
        let lensX = x - (lens.offsetWidth / 2);
        let lensY = y - (lens.offsetHeight / 2);

        // Keep lens within image bounds
        lensX = Math.max(0, Math.min(lensX, rect.width - lens.offsetWidth));
        lensY = Math.max(0, Math.min(lensY, rect.height - lens.offsetHeight));

        lens.style.left = lensX + 'px';
        lens.style.top = lensY + 'px';

        // Update preview panel zoom position
        const percentX = (x / rect.width) * 100;
        const percentY = (y / rect.height) * 100;

        previewContent.style.backgroundPosition = `${percentX}% ${percentY}%`;
        previewContent.style.backgroundSize = `${zoomLevel * 100}%`;
      });

      // Update preview when image source changes (e.g., color selection)
      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'attributes' && mutation.attributeName === 'src') {
            if (isActive) {
              previewContent.style.backgroundImage = `url('${img.src}')`;
            }
          }
        });
      });

      observer.observe(img, {
        attributes: true,
        attributeFilter: ['src']
      });
    });

    // Mobile sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
      const sidebarToggle = document.getElementById('mobileSidebarToggle');
      const closeSidebar = document.getElementById('closeSidebar');
      const sidebarOverlay = document.getElementById('sidebarOverlay');
      const productOptions = document.getElementById('productOptionsContainer');

      function openSidebar() {
        productOptions.classList.add('sidebar-open');
        sidebarOverlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }

      function closeSidebarFunc() {
        productOptions.classList.remove('sidebar-open');
        sidebarOverlay.classList.add('hidden');
        document.body.style.overflow = '';
      }

      if (sidebarToggle) {
        sidebarToggle.addEventListener('click', openSidebar);
      }

      if (closeSidebar) {
        closeSidebar.addEventListener('click', closeSidebarFunc);
      }

      if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebarFunc);
      }
    });


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

      // All image sources (main + sub images) - You'll need to populate this with actual PHP data
      const allImages = [
        // Main image first, then sub images
        // This needs to be populated with actual image paths from PHP
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
            if (prevBtn) prevBtn.click();
          } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            if (nextBtn) nextBtn.click();
          }
        }
      });

      // Image zoom functionality (optional enhancement)
      if (mainImage) {
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
      }
    });

    // Fix for Contact Us button initialization
    document.addEventListener('DOMContentLoaded', function() {
      const contactBtn = document.getElementById('contactUsBtn');
      if (contactBtn) {
        contactBtn.disabled = true;
        contactBtn.classList.add('bg-gray-400');
        contactBtn.classList.remove('bg-black', 'hover:bg-blue-600');
      }
    });

    function openContactModal() {
      // Get selected options
      const colorData = window.productSelector ? window.productSelector.selectedColorData : null;
      const variantData = window.productSelector ? window.productSelector.selectedVariantData : null;
      const typeData = window.productSelector && window.productSelector.selectedTypeId ?
        document.getElementById('selected_type')?.value : null;

      // Build options text
      const options = [];
      if (typeData) {
        options.push(`Type: ${typeData}`);
      }
      if (colorData) {
        options.push(`Color: ${colorData.name}`);
      }
      if (variantData) {
        options.push(`Size: ${variantData.size}`);
      }

      // Calculate total price
      const totalPrice = window.productSelector ? window.productSelector.calculateTotalPrice().totalPrice : 0;

      // Update modal content
      const selectedOptionsElement = document.getElementById('selectedOptionsText');
      const selectedPriceElement = document.getElementById('selectedPriceText');
      const emailLink = document.getElementById('emailLink');

      if (selectedOptionsElement) {
        selectedOptionsElement.innerHTML = options.length > 0 ?
          `<strong>Selected:</strong><br>${options.join('<br>')}` :
          'No specific options selected';
      }

      if (selectedPriceElement) {
        selectedPriceElement.textContent = totalPrice > 0 ? `Total: ₱${totalPrice.toFixed(2)}` : '';
      }

      // Update email link with details
      if (emailLink) {
        const productName = document.querySelector('h1')?.textContent || 'Product';
        const subject = `Windows Quote Request - ${productName}`;
        let body = `Hi, I'm interested in getting a quote for ${productName}.`;

        if (options.length > 0) {
          body += `\n\nSelected Options:\n${options.join('\n')}`;
        }

        if (totalPrice > 0) {
          body += `\nEstimated Total: ₱${totalPrice.toFixed(2)}`;
        }

        body += `\n\nPlease contact me with more details and final pricing.`;

        emailLink.href = `mailto:noblehomeconst.ph@gmail.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      }

      const modal = document.getElementById('contactModal');
      if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeContactModal() {
      const modal = document.getElementById('contactModal');
      if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
    }

    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function() {
      const modal = document.getElementById('contactModal');
      if (modal) {
        modal.addEventListener('click', function(e) {
          if (e.target === this) {
            closeContactModal();
          }
        });
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeContactModal();
      }
    });

    // Initialize Swiper for related products
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof Swiper !== 'undefined') {
        const relatedSwiperElement = document.querySelector('.related-swiper');
        if (relatedSwiperElement) {
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
        }
      }
    });

    // Product Selection State Management - Fixed for your HTML structure
    class ProductSelector {
      constructor() {
        this.selectedTypeId = null;
        this.selectedVariantData = null;
        this.selectedColorData = null;
        this.basePrice = parseFloat(document.querySelector('[name="product_id"]')?.dataset?.basePrice) || 0;
        this.hasTypes = document.querySelectorAll('.type-btn').length > 0;
        this.isWindows = document.querySelector('[name="is_windows"]')?.value === '1';

        this.initializeElements();
        this.bindEvents();

        // Set initial states for step flow
        this.initializeStepFlow();
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
          contactUsBtn: document.getElementById('contactUsBtn'),
          contactBtnText: document.getElementById('contactBtnText'),
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
        if (this.elements.productForm) {
          this.elements.productForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

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

      // Initialize step flow states
      initializeStepFlow() {
        // Initially disable color selection (until type is selected)
        this.disableColorSelection();

        // Initially hide variant container
        this.hideVariantContainer();
      }

      // STEP 1: Type Selection Methods
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

      setTypeSelection(button, typeId, typeName) {
        // Update type selection indicators
        document.querySelectorAll('.type-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-1', 'ring-orange-500');
          btn.classList.add('border-gray-200', 'bg-white');
        });
        button.classList.add('selected', 'border-orange-500', 'bg-orange-50', 'ring-1', 'ring-orange-500');
        button.classList.remove('border-gray-200', 'bg-white');

        this.selectedTypeId = typeId;
        if (this.elements.selectedType) {
          this.elements.selectedType.value = typeName;
        }

        // Enable color selection after type is selected
        this.enableColorSelection();

        // Clear previous color and variant selections
        this.clearColorSelection(false); // Don't disable color selection
        this.clearVariantSelection();
        this.hideVariantContainer();

        this.updateDisplay();
      }

      unselectType(button) {
        // Remove selection indicators
        button.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
        button.classList.add('border-gray-200', 'bg-white');

        this.selectedTypeId = null;
        if (this.elements.selectedType) {
          this.elements.selectedType.value = '';
        }

        // Disable color and variant selection when no type is selected
        this.disableColorSelection();
        this.hideVariantContainer();

        // Clear color and variant selections
        this.clearColorSelection(false);
        this.clearVariantSelection();

        this.updateDisplay();
      }

      // STEP 2: Color Selection Methods
      enableColorSelection() {
        const container = document.getElementById('color-selection-container');
        const message = document.getElementById('color-disabled-message');

        if (container && message) {
          container.classList.remove('opacity-50', 'pointer-events-none');
          message.style.display = 'none';
        }

        // Enable all color buttons
        document.querySelectorAll('.color-btn').forEach(btn => {
          btn.disabled = false;
          btn.classList.remove('opacity-50');
        });

        // Update info message
        const infoElement = document.getElementById('color-selection-info');
        if (infoElement) {
          infoElement.innerHTML = '<i class="fas fa-info-circle text-orange-500 mr-2"></i>Select a color to continue';
        }
      }

      disableColorSelection() {
        const container = document.getElementById('color-selection-container');
        const message = document.getElementById('color-disabled-message');

        if (container && message) {
          container.classList.add('opacity-50', 'pointer-events-none');
          message.style.display = 'block';
        }

        // Disable all color buttons
        document.querySelectorAll('.color-btn').forEach(btn => {
          btn.disabled = true;
          btn.classList.add('opacity-50');
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50');
          btn.classList.add('border-gray-200', 'bg-white');
        });

        // Update info message
        const infoElement = document.getElementById('color-selection-info');
        if (infoElement) {
          infoElement.innerHTML = '<i class="fas fa-info-circle text-orange-500 mr-2"></i>Select an item type first';
        }
      }

      // Add this inside your ProductSelector class, sa setColorFromGrid method:

      setColorFromGrid(colorId, colorName, price, image, colorCode) {
        this.selectedColorData = {
          id: colorId,
          name: colorName,
          price: parseFloat(price)
        };

        // Update hidden fields
        if (this.elements.selectedColorId) {
          this.elements.selectedColorId.value = colorId;
        }
        if (this.elements.selectedColor) {
          this.elements.selectedColor.value = colorName;
        }

        // Update BOTH images if color has an image
        if (image) {
          let imagePath;

          if (image.startsWith('../../')) {
            imagePath = image;
          } else {
            imagePath = `../../${image}`;
          }

          console.log('Setting image path:', imagePath);

          // Update main product image
          if (this.elements.mainImage) {
            this.elements.mainImage.src = imagePath;
          }

          // ✅ UPDATE SIDEBAR IMAGE TOO
          const sidebarImage = document.getElementById('sidebar-product-image');
          if (sidebarImage) {
            sidebarImage.src = imagePath;
          }
        }

        // Enable variant selection if type is also selected
        if (this.selectedTypeId) {
          this.showVariantGroup(this.selectedTypeId);
        }

        this.updateDisplay();
      }

      clearColorSelection(shouldDisable = true) {
        this.selectedColorData = null;

        // Clear hidden fields
        if (this.elements.selectedColorId) {
          this.elements.selectedColorId.value = '';
        }
        if (this.elements.selectedColor) {
          this.elements.selectedColor.value = '';
        }

        // Remove selection from all color buttons
        document.querySelectorAll('.color-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50');
          btn.classList.add('border-gray-200', 'bg-white');
        });

        // Reset to original image
        if (this.elements.mainImage) {
          const originalSrc = this.elements.mainImage.dataset.originalSrc ||
            this.elements.mainImage.src.split('?')[0]; // Remove any query params
          this.elements.mainImage.src = originalSrc;
        }

        // Optionally disable color selection
        if (shouldDisable) {
          this.disableColorSelection();
        }

        // Clear variant selection and hide variants
        this.clearVariantSelection();
        this.hideVariantContainer();

        // Update display
        this.updateDisplay();
      }

      // STEP 3: Variant Selection Methods
      showVariantGroup(typeId) {
        // Hide all variant groups first
        document.querySelectorAll('.variant-group').forEach(group => {
          group.classList.add('hidden');
        });

        // Hide the default variant container message
        if (this.elements.variantContainer) {
          this.elements.variantContainer.style.display = 'none';
        }

        // Show the specific variant group for the selected type
        const targetGroup = document.getElementById(`variants-${typeId}`);
        if (targetGroup) {
          targetGroup.classList.remove('hidden');
        }

        // Update variant buttons to enable only those for selected type
        this.updateVariantAvailability(typeId);
      }

      hideVariantContainer() {
        // Hide all variant groups
        document.querySelectorAll('.variant-group').forEach(group => {
          group.classList.add('hidden');
        });

        // Show default message
        if (this.elements.variantContainer) {
          this.elements.variantContainer.style.display = 'block';
          this.elements.variantContainer.innerHTML = `
        <i class="fas fa-arrow-up text-orange-500 mb-2 text-xl"></i>
        <p>Please select a color first</p>
      `;
        }

        // Disable all variant buttons
        document.querySelectorAll('.variant-btn').forEach(btn => {
          btn.disabled = true;
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50');
          btn.classList.add('border-gray-200', 'bg-white', 'opacity-50');
        });
      }

      updateVariantAvailability(selectedTypeId) {
        // This method enables only variants that belong to the selected type
        // Since your PHP generates separate variant groups, we just need to enable buttons in the visible group
        const activeGroup = document.getElementById(`variants-${selectedTypeId}`);
        if (activeGroup) {
          const variantButtons = activeGroup.querySelectorAll('.variant-btn');
          variantButtons.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('opacity-50');
          });
        }
      }

      selectVariant(button, size, color = null) {
        const isCurrentlySelected = button.classList.contains('selected');

        if (isCurrentlySelected) {
          this.unselectVariant(button);
        } else {
          this.setVariantSelection(button, size, color);
        }

        this.updateDisplay();
      }

      setVariantSelection(button, size, color = null) {
        // Remove previous selection indicators from all variant buttons
        document.querySelectorAll('.variant-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-1', 'ring-orange-500');
          btn.classList.add('border-gray-200', 'bg-white');
        });

        // Add selection indicators to clicked button
        button.classList.add('selected', 'border-orange-500', 'bg-orange-50', 'ring-1', 'ring-orange-500');
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
          size: size,
          color: color || ''
        };

        // Update hidden fields
        if (this.elements.selectedVariant) {
          this.elements.selectedVariant.value = size;
        }
        if (this.elements.variantId) {
          this.elements.variantId.value = variantId;
        }

        // Update price display next to product name
        this.updateProductHeaderPrice();
      }

      unselectVariant(button) {
        // Remove selection indicators
        button.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-1', 'ring-orange-500');
        button.classList.add('border-gray-200', 'bg-white');

        this.selectedVariantData = null;
        if (this.elements.selectedVariant) {
          this.elements.selectedVariant.value = '';
        }
        if (this.elements.variantId) {
          this.elements.variantId.value = '';
        }

        // Hide price display in product header
        this.hideProductHeaderPrice();
      }

      clearVariantSelection() {
        this.selectedVariantData = null;
        if (this.elements.selectedVariant) {
          this.elements.selectedVariant.value = '';
        }
        if (this.elements.variantId) {
          this.elements.variantId.value = '';
        }
        document.querySelectorAll('.variant-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-1', 'ring-orange-500');
          btn.classList.add('border-gray-200', 'bg-white');
        });
      }


      // Price and Display Methods
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

      // New method to update product header price display
      updateProductHeaderPrice() {
        if (!this.selectedVariantData) {
          console.log('No variant data available');
          return;
        }

        const priceDisplay = document.getElementById('product-price-display');
        const originalPriceContainer = document.getElementById('original-price-container');
        const originalPriceElement = document.getElementById('original-price');
        const finalPriceElement = document.getElementById('final-price');
        const discountBadge = document.getElementById('discount-badge');
        const discountPercent = document.getElementById('discount-percent');
        const selectedSizeText = document.getElementById('selected-size-text');

        if (!priceDisplay || !finalPriceElement) {
          console.error('Required price display elements not found');
          return;
        }

        try {
          // Show the price display
          priceDisplay.classList.remove('hidden');

          // Calculate prices - start with variant price with markup
          let finalPrice = parseFloat(this.selectedVariantData.priceWithMarkup) || 0;

          // Add color price if selected
          if (this.selectedColorData && this.selectedColorData.price) {
            finalPrice += parseFloat(this.selectedColorData.price);
          }

          console.log('Final price calculation:', {
            variantPrice: this.selectedVariantData.priceWithMarkup,
            colorPrice: this.selectedColorData?.price || 0,
            finalPrice: finalPrice
          });

          // Update size info
          if (selectedSizeText && this.selectedVariantData.size) {
            selectedSizeText.textContent = this.selectedVariantData.size;
          }

          // Check if there's a discount
          const discountValue = parseFloat(this.selectedVariantData.discount) || 0;

          if (discountValue > 0) {
            // Show original price with strikethrough
            const originalPrice = finalPrice;
            const discountedPrice = originalPrice - (originalPrice * discountValue / 100);

            if (originalPriceContainer && originalPriceElement) {
              originalPriceContainer.classList.remove('hidden');
              originalPriceElement.textContent = `₱${originalPrice.toFixed(2)}`;
            }

            finalPriceElement.textContent = `₱${discountedPrice.toFixed(2)}`;

            // Show discount badge
            if (discountBadge && discountPercent) {
              discountBadge.classList.remove('hidden');
              discountPercent.textContent = discountValue.toFixed(0);
            }
          } else {
            // No discount, just show final price
            if (originalPriceContainer) {
              originalPriceContainer.classList.add('hidden');
            }
            finalPriceElement.textContent = `₱${finalPrice.toFixed(2)}`;
            if (discountBadge) {
              discountBadge.classList.add('hidden');
            }
          }
        } catch (error) {
          console.error('Error updating product header price:', error);
        }
      }

      // New method to hide product header price
      hideProductHeaderPrice() {
        const priceDisplay = document.getElementById('product-price-display');
        if (priceDisplay) {
          priceDisplay.classList.add('hidden');
        }
      }

      updateTotalPrice() {
        const {
          totalPrice,
          hasSelections
        } = this.calculateTotalPrice();

        if (this.elements.totalPrice) {
          if (hasSelections) {
            this.elements.totalPrice.textContent = `₱${totalPrice.toFixed(2)}`;
          } else {
            this.elements.totalPrice.textContent = '₱0.00';
          }
        }

        // Update selection status
        const status = [];
        if (this.selectedTypeId && this.elements.selectedType) status.push(`Type: ${this.elements.selectedType.value}`);
        if (this.selectedColorData) status.push(`Color: ${this.selectedColorData.name}`);
        if (this.selectedVariantData) status.push(`Size: ${this.selectedVariantData.size}`);

        if (this.elements.selectionStatus) {
          this.elements.selectionStatus.textContent =
            status.length > 0 ? status.join(', ') : 'Follow steps 1-3 to see total price';
        }
      }

      updatePurchaseButton() {
        const hasRequiredSelections = this.selectedTypeId && this.selectedColorData && this.selectedVariantData;

        if (this.isWindows) {
          // Handle Contact Us button for windows
          const contactBtn = this.elements.contactUsBtn;
          const contactBtnText = this.elements.contactBtnText;

          if (contactBtn && contactBtnText) {
            if (hasRequiredSelections) {
              contactBtn.disabled = false;
              contactBtn.className = 'w-full py-3 lg:py-4 font-bold text-lg transition-all duration-300 bg-black hover:bg-blue-600 text-white';
              contactBtnText.innerHTML = '<i class="fas fa-phone mr-2"></i>Contact Us for Quote';
            } else {
              contactBtn.disabled = true;
              contactBtn.className = 'w-full py-3 lg:py-4  text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75';
              contactBtnText.innerHTML = '<i class="fas fa-phone mr-2"></i>Complete Steps 1-3 to Contact Us';
            }
          }
        } else {
          // Handle regular Add to Cart button
          const addToCartBtn = this.elements.addToCartBtn;
          const btnText = this.elements.btnText;

          if (addToCartBtn && btnText) {
            if (hasRequiredSelections) {
              addToCartBtn.disabled = false;
              addToCartBtn.className = 'flex-1 py-3 lg:py-4  text-lg transition-all duration-300 bg-black hover:bg-orange-600 text-white';
              btnText.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Add to Cart';
            } else {
              addToCartBtn.disabled = true;
              addToCartBtn.className = 'flex-1 py-3 lg:py-4  text-lg transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75';
              btnText.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Add to cart';
            }
          }
        }
      }

      updateDisplay() {
        this.updateTotalPrice();
        this.updatePurchaseButton();
      }

      // Form Submission Methods
      validateSelections() {
        const errors = [];

        if (!this.selectedTypeId) {
          errors.push('Please select an item type');
        }

        if (!this.selectedColorData) {
          errors.push('Please select a color');
        }

        if (!this.selectedVariantData) {
          errors.push('Please select a size');
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
        const productId = document.querySelector('[name="product_id"]')?.value;

        if (productId) {
          formData.append('product_id', productId);
        }

        if (this.selectedVariantData) {
          formData.append('variant_id', this.selectedVariantData.variantId);
          if (this.elements.selectedType) {
            formData.append('selected_type', this.elements.selectedType.value);
          }
          if (this.elements.selectedVariant) {
            formData.append('selected_variant', this.elements.selectedVariant.value);
          }
        }

        if (this.selectedColorData) {
          formData.append('selected_color_id', this.selectedColorData.id);
          formData.append('selected_color', this.selectedColorData.name);
        }

        // Add quantity (default to 1 if not specified)
        const quantity = document.querySelector('[name="quantity"]')?.value || '1';
        formData.append('quantity', quantity);

        // Add any additional form fields
        const additionalFields = ['is_windows', 'base_price'];
        additionalFields.forEach(fieldName => {
          const field = document.querySelector(`[name="${fieldName}"]`);
          if (field) {
            formData.append(fieldName, field.value);
          }
        });

        return formData;
      }

      // Notification Methods
      showNotification(message, type = 'success') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification-toast fixed top-4 right-4 z-[150] px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;

        // Set colors based on type
        if (type === 'success') {
          notification.classList.add('bg-green-500', 'text-white');
        } else if (type === 'error') {
          notification.classList.add('bg-red-500', 'text-white');
        } else {
          notification.classList.add('bg-blue-500', 'text-white');
        }

        // Add icon based on type
        const iconClass = type === 'success' ? 'fas fa-check-circle' :
          type === 'error' ? 'fas fa-exclamation-circle' :
          'fas fa-info-circle';

        notification.innerHTML = `
      <div class="flex items-center">
        <i class="${iconClass} mr-3"></i>
        <span class="font-medium">${message}</span>
        <button class="ml-4 text-white hover:text-gray-200 focus:outline-none" onclick="this.parentElement.parentElement.remove()">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `;

        // Add to document
        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
          notification.classList.remove('translate-x-full');
        }, 100);

        // Auto-remove after 5 seconds
        setTimeout(() => {
          notification.classList.add('translate-x-full');
          setTimeout(() => {
            if (notification.parentElement) {
              notification.remove();
            }
          }, 300);
        }, 5000);
      }

      // Cart count update method
      updateCartCount(newCount) {
        const cartCountElements = document.querySelectorAll('.cart-count, #cartCount');
        cartCountElements.forEach(element => {
          if (element) {
            element.textContent = newCount;

            // Add animation effect
            element.classList.add('animate-bounce');
            setTimeout(() => {
              element.classList.remove('animate-bounce');
            }, 1000);
          }
        });

        // Update cart badge visibility
        const cartBadges = document.querySelectorAll('.cart-badge');
        cartBadges.forEach(badge => {
          if (newCount > 0) {
            badge.classList.remove('hidden');
          } else {
            badge.classList.add('hidden');
          }
        });
      }

      // Utility method to reset all selections
      resetAllSelections() {
        this.selectedTypeId = null;
        this.selectedColorData = null;
        this.selectedVariantData = null;

        // Clear all visual selections
        document.querySelectorAll('.type-btn, .color-btn, .variant-btn').forEach(btn => {
          btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50', 'ring-2', 'ring-orange-500');
          btn.classList.add('border-gray-200', 'bg-white');
        });

        // Clear hidden fields
        Object.values(this.elements).forEach(element => {
          if (element && element.tagName === 'INPUT' && element.type === 'hidden') {
            element.value = '';
          }
        });

        // Reset step flow
        this.initializeStepFlow();
        this.updateDisplay();
      }

      // Method to get current selection summary
      getSelectionSummary() {
        return {
          hasType: !!this.selectedTypeId,
          hasColor: !!this.selectedColorData,
          hasVariant: !!this.selectedVariantData,
          isComplete: !!(this.selectedTypeId && this.selectedColorData && this.selectedVariantData),
          typeId: this.selectedTypeId,
          typeName: this.elements.selectedType?.value || null,
          colorData: this.selectedColorData,
          variantData: this.selectedVariantData,
          totalPrice: this.calculateTotalPrice().totalPrice
        };
      }
    }

    // Global functions for HTML onclick handlers
    function showVariants(typeId, typeName) {
      if (window.productSelector) {
        window.productSelector.showVariants(typeId, typeName);
      }
    }

    function selectVariant(button, size, color = null) {
      if (window.productSelector) {
        window.productSelector.selectVariant(button, size, color);
      }
    }

    function selectColorFromGrid(colorId, colorName, price, image, colorCode) {
      // Remove selection from all color buttons
      document.querySelectorAll('.color-btn').forEach(btn => {
        btn.classList.remove('selected', 'border-orange-500', 'bg-orange-50');
        btn.classList.add('border-gray-200', 'bg-white');
      });

      // Add selection to clicked button
      const clickedButton = event.currentTarget;
      clickedButton.classList.add('selected', 'border-orange-500', 'bg-orange-50');
      clickedButton.classList.remove('border-gray-200', 'bg-white');

      // Update product selector
      if (window.productSelector) {
        window.productSelector.setColorFromGrid(colorId, colorName, price, image, colorCode);
      }
    }

    // Share product function
    function shareProduct() {
      const productName = document.querySelector('h1')?.textContent || 'Product';
      const currentUrl = window.location.href;

      if (navigator.share) {
        // Use native sharing if available
        navigator.share({
          title: productName,
          text: `Check out this product: ${productName}`,
          url: currentUrl
        }).catch(err => console.log('Error sharing:', err));
      } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(currentUrl).then(() => {
          // Show notification that link was copied
          if (window.productSelector) {
            window.productSelector.showNotification('Product link copied to clipboard!', 'success');
          } else {
            alert('Product link copied to clipboard!');
          }
        }).catch(err => {
          console.error('Failed to copy: ', err);
          alert('Could not copy link. Please copy manually: ' + currentUrl);
        });
      }
    }

    // Initialize ProductSelector when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize the product selector
      if (typeof ProductSelector !== 'undefined') {
        window.productSelector = new ProductSelector();

        // Store original image src for reset functionality
        const mainImage = document.getElementById('main-product-image');
        if (mainImage) {
          mainImage.dataset.originalSrc = mainImage.src;
        }

        console.log('ProductSelector initialized');
      } else {
        console.error('ProductSelector class not found');
      }
    });

    // Keyboard shortcuts for better UX
    document.addEventListener('keydown', function(e) {
      // ESC key to close modals
      if (e.key === 'Escape') {
        closeContactModal();
      }

      // Number keys to select variants quickly (1-9)
      if (e.key >= '1' && e.key <= '9' && !e.ctrlKey && !e.altKey && !e.metaKey) {
        const variantButtons = document.querySelectorAll('.variant-btn:not([disabled])');
        const index = parseInt(e.key) - 1;
        if (variantButtons[index]) {
          variantButtons[index].click();
        }
      }
    });

    // Touch and mobile optimizations
    document.addEventListener('DOMContentLoaded', function() {
      // Add touch feedback for buttons
      const buttons = document.querySelectorAll('.type-btn, .color-btn, .variant-btn');
      buttons.forEach(button => {
        button.addEventListener('touchstart', function() {
          this.style.transform = 'scale(0.95)';
        });

        button.addEventListener('touchend', function() {
          this.style.transform = 'scale(1)';
          this.blur(); // Remove focus outline on mobile
        });

        button.addEventListener('touchcancel', function() {
          this.style.transform = 'scale(1)';
        });
      });
    });

    // Performance optimization: Debounce rapid button clicks
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // Add loading states for async operations
    function setLoadingState(button, isLoading = true) {
      if (!button) return;

      if (isLoading) {
        button.disabled = true;
        const originalText = button.innerHTML;
        button.dataset.originalText = originalText;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
      } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || button.innerHTML;
      }
    }

    // Error handling for missing elements
    window.addEventListener('error', function(e) {
      console.error('JavaScript Error:', e.error);
    });

    // Debug function to check selector state (remove in production)
    window.debugProductSelector = function() {
      if (window.productSelector) {
        console.log('ProductSelector State:', window.productSelector.getSelectionSummary());
      } else {
        console.log('ProductSelector not initialized');
      }
    };
  </script>
</body>

</html>