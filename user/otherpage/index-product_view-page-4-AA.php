<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

include 'index-recent_views_handler-page-14.php';

// Track this product view
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
  $product_id = (int) $_GET['id'];
  trackProductView($conn, $product_id);

  // Get view count for display
  $viewData = getProductViewCount($conn, $product_id);
}
date_default_timezone_set('Asia/Manila');
$server_time = time(); // Current Unix timestamp
// ✅ STEP 1: SESSION RESTORE
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];
  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_mobile'] = $user['mobile'] ?? '';

    if (!empty($user['google_id'])) {
      $_SESSION['google_logged_in'] = true;
      $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
    }
  }
  $stmt->close();
}

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
  SELECT 
    id, 
    product_name, 
    codename, 
    quantity, 
    price, 
    main_image, 
    sub_images, 
    description,
    descrip1, 
    descrip2, 
    descrip3, 
    descrip4, 
    descrip5,
    descrip6, 
    descrip7, 
    descrip8, 
    descrip9, 
    descrip10, 
    guide_enabled,
    product_images, 
    descriptionpic,
    qr_code
  FROM products 
  WHERE id = ? 
  LIMIT 1
");

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
  echo "Product not found.";
  exit;
}

$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
  echo "Product not found.";
  exit;
}

error_log("=== PRODUCT FETCH DEBUG ===");
error_log("Product ID: " . $product['id']);
error_log("Product Name: " . $product['product_name']);
error_log("Has descriptionpic: " . (!empty($product['descriptionpic']) ? 'YES (' . strlen($product['descriptionpic']) . ' chars)' : 'NO'));
error_log("Has product_images: " . (!empty($product['product_images']) ? 'YES (' . strlen($product['product_images']) . ' chars)' : 'NO'));
error_log("Product Images Content: " . substr($product['product_images'] ?? '', 0, 100));

$ORIGINAL_PRODUCT = $product;

for ($i = 1; $i <= 10; $i++) {
  $key = "descrip$i";
  error_log("$key: " . (!empty($product[$key]) ? htmlspecialchars($product[$key]) : 'EMPTY'));
}

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

$display_image = !empty($type_main_image) ? $type_main_image : $product['main_image'];
$display_name = !empty($type_main_name) ? $type_main_name : $product['product_name'];

// ✅ PROCESS PRODUCT IMAGES GALLERY
$product_images = [];

if (!empty($product['product_images'])) {
  $decoded_images = json_decode($product['product_images'], true);

  if (is_array($decoded_images) && !empty($decoded_images)) {
    foreach ($decoded_images as $idx => $imagePath) {
      $cleanPath = str_replace(['\\/', '\\'], ['/', ''], $imagePath);
      $cleanPath = trim($cleanPath, '/');

      $imageSrc = "../../" . $cleanPath;

      if (file_exists($imageSrc)) {
        $product_images[] = [
          'index' => $idx,
          'path' => $cleanPath,
          'src' => $imageSrc
        ];
      }
    }
  }
}

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

// ✅ STEP 4: FETCH VARIANTS WITH JUNCTION TABLE STOCK (ONLY ONE QUERY!)
$stmt = $conn->prepare("
  SELECT 
    pt.id as type_id,
    pt.type_name,
    pt.type_image,
    pv.id as variant_id, 
    pv.namevariant, 
    pv.color, 
    pv.size, 
    pv.original_price,
    pv.price as variant_price, 
    pv.percent, 
    pv.discount,
    pv.image as variant_image,
    pv.sku_info,
    pv.width,
    pv.height,
    pv.length,
    pv.stock as variant_fallback_stock,
    pv.timer_discount_percent,
    pv.timer_discount_active,
    pv.timer_discount_start,
    pv.timer_discount_end,
    COALESCE(SUM(pvc.stock_quantity), 0) as total_variant_stock
  FROM product_types pt
  LEFT JOIN product_variants pv ON pt.id = pv.type_id 
  LEFT JOIN product_variant_colors pvc ON pv.id = pvc.variant_id
  WHERE pt.product_id = ?
GROUP BY pv.id, pt.id, pt.type_name, pt.type_image, pv.namevariant, pv.color, pv.size, 
           pv.price, pv.percent, pv.discount, pv.image, pv.sku_info, pv.width, pv.height, pv.length, pv.stock,
           pv.timer_discount_percent, pv.timer_discount_active, pv.timer_discount_start, pv.timer_discount_end
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
    $stock = $row['total_variant_stock'] > 0 ? $row['total_variant_stock'] : $row['variant_fallback_stock'];

    // ✅ CHECK IF TIMER DISCOUNT IS ACTIVE AND VALID
    $timer_discount = 0;
    $has_active_timer = false;

    if (
      $row['timer_discount_active'] &&
      !empty($row['timer_discount_start']) &&
      !empty($row['timer_discount_end'])
    ) {
      $now = time();
      $start = strtotime($row['timer_discount_start']);
      $end = strtotime($row['timer_discount_end']);

      if ($now >= $start && $now <= $end) {
        $timer_discount = floatval($row['timer_discount_percent']);
        $has_active_timer = true;
      }
    }

    $types_data[$type_name]['variants'][] = [
      'variant_id' => $row['variant_id'],
      'namevariant' => $row['namevariant'],
      'color' => $row['color'],
      'size' => $row['size'],
      'original_price' => $row['original_price'], // ← ADD THIS
      'variant_price' => $row['variant_price'],
      'percent' => $row['percent'],
      'discount' => $row['discount'],
      'variant_image' => $row['variant_image'],
      'sku_info' => $row['sku_info'],
      'width' => $row['width'] ?? 0,
      'height' => $row['height'] ?? 0,
      'length' => $row['length'] ?? 0,
      'stock' => $stock,
      'timer_discount' => $timer_discount,
      'has_active_timer' => $has_active_timer,
      'timer_end' => $row['timer_discount_end'],
      'timer_discount_start' => $row['timer_discount_start'],  // ← ADD
      'timer_discount_active' => $row['timer_discount_active'],  // ← ADD
    ];
  }
}
$stmt->close();

// ✅ FETCH VARIANT-COLOR COMBINATIONS WITH INDIVIDUAL STOCK FOR DYNAMIC DISPLAY
$stmt_variant_colors = $conn->prepare("
  SELECT 
    pv.id as variant_id,
    pv.size,
    pc.id as color_id,
    pc.color_name,
    pvc.stock_quantity,
    pvc.id as junction_id
  FROM product_variant_colors pvc
  JOIN product_variants pv ON pvc.variant_id = pv.id
  JOIN product_colors pc ON pvc.color_id = pc.id
  WHERE pv.type_id IN (
    SELECT id FROM product_types WHERE product_id = ?
  )
  ORDER BY pv.id, pc.color_name
");
$stmt_variant_colors->bind_param("i", $product_id);
$stmt_variant_colors->execute();
$variant_colors_result = $stmt_variant_colors->get_result();

// Create nested array: variant_id => [color_id => stock]
$variant_color_stock_map = [];
while ($row = $variant_colors_result->fetch_assoc()) {
  $variant_id = $row['variant_id'];
  $color_id = $row['color_id'];
  $stock = intval($row['stock_quantity']);

  if (!isset($variant_color_stock_map[$variant_id])) {
    $variant_color_stock_map[$variant_id] = [];
  }

  $variant_color_stock_map[$variant_id][$color_id] = $stock;
}
$stmt_variant_colors->close();

$stock_json = json_encode($variant_color_stock_map);

error_log("Stock map: " . $stock_json);

// ✅ FETCH RELATED PRODUCTS
$codename = $product['codename'];
$stmt = $conn->prepare("
  SELECT 
    p.*, 
    p.descrip6, 
    p.descrip7,
    p.view_count,
    p.unique_view_count,
    MIN(v.origin) as origin,
    MIN(v.discount) as discount,
    MIN(v.percent) as percent,
    MIN(v.status) as status,
    COALESCE(MIN(pv.price), 0) as min_size_price,  
    COALESCE(MAX(pv.price), 0) as max_size_price,  
    COALESCE(MIN(pc.price), 0) as min_color_price,
    COALESCE(MAX(pc.price), 0) as max_color_price,
    COUNT(DISTINCT pc.id) as color_count,
    AVG(r.rating) AS avg_rating,
    COUNT(r.rating) AS rating_count,
    COALESCE(SUM(si.quantity), 0) AS total_sold
  FROM products p
  LEFT JOIN product_variants v ON v.product_id = p.id
  LEFT JOIN product_variants pv ON p.id = pv.product_id
  LEFT JOIN product_colors pc ON p.id = pc.product_id
  LEFT JOIN product_ratings r ON r.product_id = p.id
  LEFT JOIN sold_items si ON si.product_id = p.id
  WHERE p.codename = ? AND p.id != ?
  AND p.is_archived = 0
  GROUP BY p.id
  ORDER BY p.view_count DESC, RAND()
  LIMIT 10
");
$stmt->bind_param("si", $codename, $product_id);
$stmt->execute();
$related_products = $stmt->get_result();
$stmt->close();

// ✅ Check if this is a Windows product
$is_windows_category = strtolower($product['codename']) === 'windows';
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

if (!isset($product['product_name']) || empty($product['product_name'])) {
  error_log("⚠️ WARNING: Product name missing! Using backup data.");
  $product = $ORIGINAL_PRODUCT;
}

$is_guest = !isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="server-time" content="<?= $server_time ?>">
  <?php if ($is_logged_in): ?>
    <meta name="user-email" content="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">
    <meta name="user-name" content="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>">
  <?php endif; ?>
  <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
  <title><?= htmlspecialchars($product['product_name']) ?> - Noble Home</title>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    footer * {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

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

    .scrollbar-thin::-webkit-scrollbar {
      height: 4px;
      /* Nipis scroll bar */
    }

    .scrollbar-thin::-webkit-scrollbar-track {
      background: transparent;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb {
      background: #c4c4c4;
      border-radius: 4px;
    }

    /* ===== FIX: ADD TO CART BUTTON OVERLAY ISSUE ===== */

    /* Ensure button is ALWAYS on top */
    #addToCartBtn {
      position: relative !important;
      z-index: 20 !important;
      pointer-events: auto !important;
    }

    /* Purchase section - make sure it's clickable */
    .sticky.bottom-0 {
      position: relative;
      z-index: 15;
      pointer-events: auto !important;
    }

    /* Form inside sidebar */
    #productForm {
      position: relative;
      z-index: 10;
      pointer-events: auto !important;
    }

    /* Sidebar container - allow clicks to pass through */
    #productOptionsContainer {
      pointer-events: auto;

    }

    #productOptionsContainer.sidebar-open {
      pointer-events: auto;
      z-index: 101;
      transform: translateX(0) !important;
    }

    /* Overlay - should NOT block button clicks */
    #sidebarOverlay {
      z-index: 100;
      pointer-events: auto;
    }

    /* Remove any blocking elements */
    #productOptionsContainer::after {
      pointer-events: none !important;
    }

    /* On mobile, ensure button is accessible */
    @media (max-width: 1024px) {
      #addToCartBtn {
        z-index: 20 !important;
        pointer-events: auto !important;
      }

      .sticky.bottom-0 {
        z-index: 15;
        pointer-events: auto !important;
      }

      #productForm {
        pointer-events: auto !important;
      }
    }
  </style>
</head>

<body class="">
  <?php include '../navbar/top.php'; ?>
  <!-- Breadcrumb -->
  <nav class="bg-white border-b border-gray-200 px-4 py-3">
    <div class="container mx-auto">
      <div class="flex items-center space-x-2 text-sm" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
        <a href="index-page-1-A-B-C-D-E" class=" hover:text-orange-700 transition duration-200 flex items-center">
          <i class="fas fa-home mr-1"></i>Home
        </a>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class=" font-medium">Products</span>
        <i class="fas fa-chevron-right text-gray-400"></i>
        <span class=" font-semibold">
          <?= htmlspecialchars($product['product_name'] ?? $display_name ?? 'Product') ?>
        </span>
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

              <img id="main-product-image" src="../../<?= htmlspecialchars($display_image) ?>"
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
            <div id="zoom-preview-panel" class="hidden absolute top-0 left-full ml-6 w-96 h-96 z-50">
              <div
                class="relative w-full h-full bg-gray-50 rounded-lg shadow-2xl overflow-hidden border-2 border-gray-200">
                <div id="zoom-preview-content" class="w-full h-full bg-no-repeat" style="background-size: 250%;"></div>

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
                  <div class="thumbnail-item cursor-pointer shrink-0 border border-gray-200 rounded-lg"
                    data-index="0">
                    <img src="../../<?= htmlspecialchars($display_image) ?>" loading="lazy"
                      class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 object-contain rounded-lg border-2 border-transparent hover:border-blue-500 transition-all duration-200 thumbnail-active"
                      alt="Main Image">
                  </div>

                  <!-- Sub Images Thumbnails - FIXED PATH -->
                  <?php foreach ($sub_images as $index => $sub_image): ?>
                    <div class="thumbnail-item cursor-pointer shrink-0 border border-gray-200 rounded-lg"
                      data-index="<?= $index + 1 ?>">
                      <img src="../../uploads/<?= htmlspecialchars($sub_image) ?>" loading="lazy"
                        class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 object-contain rounded-lg border-2 border-transparent hover:border-blue-500 transition-all duration-200"
                        alt="Sub Image <?= $index + 1 ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Mobile Sidebar Toggle Button -->
        <button id="mobileSidebarToggle"
          class="lg:hidden fixed bottom-3 right-3 z-90 bg-black text-white px-4 py-2 text-sm rounded-full shadow-md hover:bg-orange-600 transition-all active:scale-95">
          <i class="fas fa-shopping-cart text-xs lg:text-base"></i>
          <span>Add to Cart</span>
        </button>

        <!-- Overlay for mobile sidebar -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-100 hidden lg:hidden"></div>

        <!-- Product Options Section - Sidebar on mobile, normal on desktop -->
        <div id="productOptionsContainer" class="fixed lg:relative top-0 right-0 h-full lg:h-auto w-full sm:w-80 lg:w-full 
         transform translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out
         z-101 lg:z-auto bg-white lg:bg-white shadow-xl lg:shadow-none overflow-y-auto">

          <!-- Mobile Sidebar Header -->
          <div
            class="lg:hidden sticky top-0 bg-white border-b border-gray-200 p-4 flex items-center justify-between z-20 shadow-sm "
            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
            <h2 class="text-lg">Product Options</h2>
            <button id="closeSidebar" class="text-black hover:text-white p-1">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>

          <div class="p-2 flex flex-col space-y-6 lg:space-y-8">

            <!-- Product Info Section -->
            <div class="space-y-4 lg:space-y-6">

              <!-- Product Name & Price Display -->
              <div>
                <div class="flex items-center justify-between gap-4">

                  <!-- PRODUCT NAME -->
                  <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold "
                    style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                    <?php
                    $safe_product = isset($ORIGINAL_PRODUCT) ? $ORIGINAL_PRODUCT : $product;
                    echo htmlspecialchars($display_name ?? $safe_product['product_name'] ?? 'Product');
                    ?>
                  </h1>

                  <!-- RIGHT SIDE BUTTONS -->
                  <div class="flex items-center gap-3">

                    <!-- Share -->
                    <button onclick="shareProduct()"
                      class="flex items-center gap-2 bg-gray-100 px-3 py-2 rounded-xl hover:bg-gray-200 transition">
                      <i class="fas fa-share-alt text-lg text-black"></i>

                    </button>

                    <!-- Customer Service -->
                    <button onclick="window.location.href='../rules/customer-services.php'"
                      class="flex items-center gap-2 bg-gray-100 px-3 py-2 rounded-xl hover:bg-gray-200 transition">
                      <i class="fas fa-headset text-lg text-black"></i>

                    </button>

                  </div>
                </div>


                <div class="flex flex-wrap gap-2 mb-3 mt-2">
                  <span class="bg-orange-100  px-3 py-1 rounded-full text-xs font-medium uppercase"
                    style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                    <?php
                    $safe_product = isset($ORIGINAL_PRODUCT) ? $ORIGINAL_PRODUCT : $product;
                    echo htmlspecialchars($safe_product['codename']);
                    ?>
                  </span>
                </div>
              </div>

              <!-- Product Description -->
              <div>
                <p class=" leading-relaxed text-sm lg:text-base"
                  style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                  <?= htmlspecialchars($safe_product['description'] ?? 'No description available.') ?>
                </p>
              </div>
            </div>
            <!-- STEP 1: TYPE SELECTION -->
            <?php if (!empty($types_data)): ?>
              <div class="step-section">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base lg:text-xl font-semibold text-gray-800"
                    style="font-family: 'Montserrat', sans-serif; color: #2f1200">

                    Click Item Type
                  </h3>
                  <div class="text-xs lg:text-sm text-orange-600 font-medium"
                    style="font-family: 'Montserrat', sans-serif; color: #2f1200">Required</div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                  <?php foreach ($types_data as $index => $type): ?>
                    <button type="button" onclick="showVariants(<?= $type['id'] ?>, '<?= addslashes($type['name']) ?>')"
                      class="type-btn border-2 border-gray-300 p-2 hover:border-orange-500 transition-all duration-200 bg-white rounded focus:outline-none focus:ring-2 focus:ring-orange-300">

                      <div
                        class="aspect-square mb-1.5 overflow-hidden bg-gray-50 flex items-center justify-center relative rounded">
                        <?php if (!empty($type['image']) && file_exists("../../" . $type['image'])): ?>
                          <img src="../../<?= htmlspecialchars($type['image']) ?>" class="w-full h-full object-contain"
                            alt="<?= htmlspecialchars($type['name']) ?>" loading="lazy"
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

                      <span
                        class="text-[10px] font-medium text-gray-700 block truncate leading-tight uppercase text-center">
                        <?= htmlspecialchars($type['name']) ?>
                      </span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Store stock data as JSON for JavaScript -->
            <script>
              const variantColorStockMap = <?php echo $stock_json; ?>;
              console.log('Stock Map Loaded:', variantColorStockMap);
            </script>

            <!-- STEP 2: COLOR SELECTION -->
            <?php if (!empty($product_colors)): ?>
              <div class="step-section">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-base lg:text-xl font-semibold "
                    style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                    Choose Color
                  </h3>
                  <div class="text-xs lg:text-sm  font-medium"
                    style="font-family: 'Montserrat', sans-serif; color: #2f1200">Required</div>
                </div>

                <!-- Product Image - Mobile Sidebar Only -->
                <div class="lg:hidden py-3 px-0 bg-white border-b border-gray-100 mb-4">
                  <div class="aspect-square w-32 mx-auto overflow-hidden">
                    <h1 class="text-center mb-2 text-xs text-gray-600">Display</h1>
                    <img id="sidebar-product-image" src="../../<?= htmlspecialchars($display_image) ?>"
                      class="w-full h-full object-contain" alt="<?= htmlspecialchars($product['product_name']) ?>">
                  </div>
                  <h3 class="text-center mt-2 font-semibold text-gray-800 text-xs line-clamp-2"
                    style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                    <?= htmlspecialchars($product['product_name']) ?>
                  </h3>
                </div>

                <div id="color-selection-container" class="opacity-50 pointer-events-none">
                  <div
                    class="max-h-60 lg:max-h-80 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 hover:scrollbar-thumb-gray-400 transition-all duration-300">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 p-1" id="all-colors-grid">
                      <?php foreach ($product_colors as $color): ?>
                        <button type="button"
                          onclick="selectColorFromGrid(<?= $color['id'] ?>, '<?= addslashes($color['color_name']) ?>', <?= $color['price'] ?>, '<?= !empty($color['image']) ? htmlspecialchars($color['image']) : '' ?>', '<?= htmlspecialchars($color['color_code']) ?>')"
                          class="color-btn border-2 border-gray-300 hover:border-orange-500 bg-white rounded
               px-2 py-1.5 text-xs transition-all duration-200 text-center w-full min-h-[32px]"
                          data-color-id="<?= $color['id'] ?>" data-color-name="<?= addslashes($color['color_name']) ?>"
                          data-price="<?= $color['price'] ?>"
                          data-color-code="<?= htmlspecialchars($color['color_code']) ?>"
                          data-image="<?= !empty($color['image']) ? htmlspecialchars($color['image']) : '' ?>">
                          <span class="text-gray-700 block truncate text-[10px] lg:text-[11px] leading-tight font-medium"
                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                            <?= htmlspecialchars($color['color_name']) ?>
                          </span>
                          <!-- Stock indicator - Shows total stock for this color across ALL sizes -->
                          <span class="color-stock-display text-[8px] lg:text-[9px]  font-semibold block mt-1"
                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                            -
                          </span>
                        </button>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <div id="color-disabled-message"
                  class="text-center p-4 lg:p-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                  <i class="fas fa-arrow-up text-orange-500 mb-2 text-lg lg:text-xl"></i>
                  <p class="text-sm lg:text-base text-gray-500">Please select an item type first</p>
                </div>
              </div>
            <?php endif; ?>

            <!-- STEP 3: SIZE/VARIANT SELECTION -->
            <div class="step-section">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-base lg:text-xl font-semibold "
                  style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                  Choose Size
                </h3>
                <div class="text-xs lg:text-sm  font-medium"
                  style="font-family: 'Montserrat', sans-serif; color: #2f1200">Required</div>
              </div>

              <div id="variant-container"
                class="text-gray-500 p-4 lg:p-6 bg-gray-50 text-center rounded-lg border-2 border-dashed border-gray-300">
                <i class="fas fa-arrow-up text-orange-500 mb-2 text-lg lg:text-xl"></i>
                <p class="text-sm lg:text-base">Please select a color first</p>
              </div>
              <?php foreach ($types_data as $type): ?>
                <div id="variants-<?= $type['id'] ?>" class="variant-group hidden">
                  <?php if (!empty($type['variants'])): ?>
                    <div
                      class="max-h-60 lg:max-h-72 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 hover:scrollbar-thumb-gray-400 transition-all duration-300 pr-1">
                      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                        <?php foreach ($type['variants'] as $variant): ?>
                          <?php
                          $price = floatval($variant['variant_price']);
                          $percent = floatval($variant['percent']);
                          $discount = floatval($variant['discount'] ?? 0);
                          $stock = intval($variant['stock']);
                          $priceWithMarkup = $price + ($price * $percent / 100);
                          $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);
                          $sku_info = !empty($variant['sku_info']) ? json_decode($variant['sku_info'], true) : null;
                          $is_out_of_stock = $stock <= 0;

                          // ✅ FIXED: Get timer dates
                          $timer_discount = floatval($variant['timer_discount'] ?? 0);
                          $has_active_timer = (bool) ($variant['has_active_timer'] ?? false);
                          $timer_discount_start = !empty($variant['timer_discount_start']) ? strtotime($variant['timer_discount_start']) : 0;
                          $timer_discount_end = !empty($variant['timer_end']) ? strtotime($variant['timer_end']) : 0;
                          $timer_discount_active = (bool) ($variant['timer_discount_active'] ?? false);

                          // ✅ FIXED: Calculate DURATION from admin setting (not remaining time)
                          $now = time();
                          $is_timer_active = $timer_discount_active && $timer_discount_end && ($now <= $timer_discount_end);
                          $duration_seconds = ($timer_discount_end - $timer_discount_start); // Duration set in admin
                          $remaining_seconds = ($timer_discount_end - $now); // Remaining time (for stopping timer)
                          ?>
                          <button type="button"
                            onclick="selectVariant(this, '<?= addslashes($variant['size']) ?>'); showSkuInfo(this); updateCalculatorFromVariant(this); updateColorStockDisplay();"
                            class="variant-btn border-2 <?= $is_out_of_stock ? 'border-red-300 opacity-50' : 'border-gray-300 hover:border-orange-500' ?> bg-white rounded px-2 py-2 text-center transition-all duration-200 min-h-[50px] flex flex-col items-center justify-center relative"
                            data-price="<?= $price ?>" data-percent="<?= $percent ?>" data-discount="<?= $discount ?>"
                            data-variant-id="<?= $variant['variant_id'] ?>" data-stock="<?= $stock ?>"
                            data-width="<?= isset($variant['width']) ? htmlspecialchars($variant['width']) : '0' ?>"
                            data-height="<?= isset($variant['height']) ? htmlspecialchars($variant['height']) : '0' ?>"
                            data-length="<?= isset($variant['length']) ? htmlspecialchars($variant['length']) : '0' ?>"
                            data-sku-info='<?= $sku_info ? htmlspecialchars(json_encode($sku_info), ENT_QUOTES) : '' ?>'
                            data-has-timer="<?= $has_active_timer ? '1' : '0' ?>" data-timer-discount="<?= $timer_discount ?>"
                            data-timer-end="<?= !empty($variant['timer_end']) ? strtotime($variant['timer_end']) : '0' ?>"
                            <?= $is_out_of_stock ? 'disabled' : '' ?>>

                            <!-- ✅ DISCOUNT BADGE - Top Right Corner -->
                            <?php if ($discount > 0): ?>
                              <div
                                class="absolute top-1 right-1 bg-red-500 text-white text-[8px] px-1.5 py-0.5 rounded-full font-bold z-20 shadow-md">
                                -<?= round($discount) ?>%
                              </div>
                            <?php endif; ?>

                            <!-- Size Text -->
                            <div class="text-gray-700 text-[11px] leading-tight">
                              <?= htmlspecialchars($variant['size']) ?>
                            </div>

                            <!-- Stock Display -->
                            <div class="text-[9px] font-bold mt-0.5">
                              <?php if ($stock <= 0): ?>
                                <span class="text-red-600">OUT OF STOCK</span>
                              <?php elseif ($stock <= 5): ?>
                                <span class="text-orange-600"><?= $stock ?> left</span>
                              <?php else: ?>
                                <span class="text-green-600"><?= $stock ?> in stock</span>
                              <?php endif; ?>
                            </div>

                            <?php if ($is_timer_active && $timer_discount_end): ?>
                              <?php
                              $originalDisplayPrice = floatval($variant['original_price']); // 121 - original
                              $finalDisplayPrice = floatval($variant['variant_price']);      // 114.95 - already discounted
                              $priceAfterTimerDiscount = $finalDisplayPrice - ($finalDisplayPrice * $timer_discount / 100);
                              ?>

                              <div
                                class="mt-2 bg-red-700 text-white text-[10px] font-bold px-2 py-1 rounded inline-block timer-badge shadow-lg"
                                data-variant-id="<?= $variant['variant_id'] ?>" data-end-time="<?= $timer_discount_end ?>"
                                data-duration="<?= $duration_seconds ?>" data-remaining="<?= max(0, $remaining_seconds) ?>">

                                <!-- Timer Countdown -->
                                <div class="flex items-center gap-1 mb-1">
                                  <i class="fas fa-fire-alt"></i>
                                  <span>Flash Sale</span>
                                  <span class="timer-display font-mono tracking-wider" id="timer-<?= $variant['variant_id'] ?>">
                                    00:00:00
                                  </span>
                                </div>

                                <!-- Price Breakdown -->
                                <div class="text-[9px] bg-white/20 px-1 py-0.5 rounded flex items-center justify-between gap-2">
                                  <span class="line-through opacity-75">₱<?= number_format($originalDisplayPrice, 2) ?></span>
                                  <span class="text-yellow-300 font-bold">→</span>
                                  <span class="text-yellow-300 font-bold">₱<?= number_format($finalDisplayPrice, 2) ?></span>
                                </div>

                                <!-- Timer Discount Percentage -->
                                <div class="text-center mt-1 bg-yellow-400 text-red-700 font-black text-[8px] px-1 rounded">
                                  EXTRA -<?= round($timer_discount) ?>% OFF
                                </div>
                              </div>
                            <?php endif; ?>

                            <span class="hidden" data-original-price="<?= $priceWithMarkup ?>"
                              data-final-price="<?= $finalPrice ?>" data-discount-percent="<?= $discount ?>"></span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <p class="text-gray-500 text-center p-4 text-sm">No variants available for this type.</p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <style>
              /* Variant button discount badge styling */
              .variant-btn {
                position: relative;
              }

              /* Discount badge animation */
              .variant-btn .bg-red-500 {
                animation: discountPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
              }

              @keyframes discountPop {
                0% {
                  transform: scale(0) rotate(-20deg);
                  opacity: 0;
                }

                50% {
                  transform: scale(1.2) rotate(5deg);
                }

                100% {
                  transform: scale(1) rotate(0deg);
                  opacity: 1;
                }
              }

              /* Hover effect on discount badge */
              .variant-btn:hover .bg-red-500 {
                transform: scale(1.15);
              }
            </style>

            <script>
              // ✅ Show total stock for each color (sum of all variants)
              function initializeColorStockDisplay() {
                console.log('Initializing color stock display...');

                document.querySelectorAll('.color-btn').forEach(btn => {
                  const colorId = parseInt(btn.dataset.colorId);
                  let totalStock = 0;

                  // Sum stock across ALL variants for this color
                  for (const variantId in variantColorStockMap) {
                    const stock = variantColorStockMap[variantId][colorId] ?? 0;
                    totalStock += stock;
                  }

                  console.log(`Color ${colorId} - Total stock across all sizes:`, totalStock);

                  // Update display
                  let stockSpan = btn.querySelector('.color-stock-display');
                  if (stockSpan) {
                    if (totalStock > 0) {
                      stockSpan.className = 'color-stock-display text-[8px] lg:text-[9px] text-red-600 font-semibold block mt-1';
                      stockSpan.textContent = totalStock + ' Stock';
                    } else {
                      stockSpan.className = 'color-stock-display text-[8px] lg:text-[9px] text-red-600 font-semibold block mt-1';
                      stockSpan.textContent = 'OUT OF STOCK';
                    }
                  }
                });
              }

              // ✅ Update color stock display for SELECTED size
              function updateColorStockDisplay() {
                const selectedVariantBtn = document.querySelector('.variant-btn.selected');

                if (!selectedVariantBtn) {
                  // If no size selected, show total stock
                  initializeColorStockDisplay();
                  return;
                }

                const variantId = parseInt(selectedVariantBtn.dataset.variantId);

                console.log('Updating color display for variant:', variantId);
                console.log('Stock map:', variantColorStockMap);

                // Update ALL color buttons with their respective stock for THIS VARIANT
                document.querySelectorAll('.color-btn').forEach(btn => {
                  const btnColorId = parseInt(btn.dataset.colorId);
                  const btnStock = variantColorStockMap[variantId]?.[btnColorId] ?? 0;

                  console.log(`Color ${btnColorId} for variant ${variantId} - Stock:`, btnStock);

                  // Update the color button display
                  let stockSpan = btn.querySelector('.color-stock-display');
                  if (stockSpan) {
                    if (btnStock > 0) {
                      stockSpan.className = 'color-stock-display text-[8px] lg:text-[9px] text-green-600 font-semibold block mt-1';
                      stockSpan.textContent = btnStock + ' stock';
                      btn.classList.remove('opacity-50', 'cursor-not-allowed', 'border-red-300');
                      btn.classList.add('border-gray-300', 'hover:border-orange-500');
                      btn.disabled = false;
                    } else {
                      stockSpan.className = 'color-stock-display text-[8px] lg:text-[9px] text-red-600 font-semibold block mt-1';
                      stockSpan.textContent = 'NOT AVAIL';
                      btn.classList.add('opacity-50', 'cursor-not-allowed', 'border-red-300');
                      btn.classList.remove('border-gray-300', 'hover:border-orange-500');
                      btn.disabled = true;
                    }
                  }
                });
              }

              // ✅ Initialize on page load
              document.addEventListener('DOMContentLoaded', function () {
                initializeColorStockDisplay();
              });
            </script>


            <!-- Calculator Guide Display -->
            <?php if (isset($product['guide_enabled']) && $product['guide_enabled'] == 1): ?>
              <div id="calculatorSection" class="mt-4 bg-white rounded p-3 lg:p-4 border border-gray-200 hidden">
                <div class="flex items-center gap-2 mb-4">
                  <div>
                    <h3 class="text-base text-gray-900 font-semibold">Area</h3>
                    <p class="text-xs text-gray-600">Calculate coverage based on selected size</p>
                  </div>
                </div>

                <!-- Selected Size Display -->
                <div class="mb-4 bg-gray-100 border border-gray-300 rounded p-3">
                  <div class="flex items-center justify-between">
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">Selected Size</label>
                      <div id="selectedSizeDisplay" class="text-sm font-medium text-gray-900">-</div>
                    </div>
                    <div class="text-xs text-gray-600">
                      <i class="fas fa-check-circle"></i>
                    </div>
                  </div>
                </div>

                <!-- Dimensions Display -->
                <div id="calculatorDimensionsDisplay" class="mb-4">
                  <div class="grid grid-cols-4 gap-2">
                    <div class="text-center">
                      <label class="block text-xs text-gray-600 mb-1">Length (mm)</label>
                      <div class="bg-gray-50 rounded px-2 py-2">
                        <div id="calcLength" class="text-xs font-semibold text-gray-900">-</div>
                      </div>
                    </div>
                    <div class="text-center">
                      <label class="block text-xs text-gray-600 mb-1">Height (mm)</label>
                      <div class="bg-gray-50 rounded px-2 py-2">
                        <div id="calcHeight" class="text-xs font-semibold text-gray-900">-</div>
                      </div>
                    </div>
                    <div class="text-center">
                      <label class="block text-xs text-gray-600 mb-1">Width (mm)</label>
                      <div class="bg-gray-50 rounded px-2 py-2">
                        <div id="calcWidth" class="text-xs font-semibold text-gray-900">-</div>
                      </div>
                    </div>
                    <div class="text-center">
                      <label class="block text-xs text-gray-600 mb-1">Per Piece</label>
                      <div class="bg-gray-200 rounded px-2 py-2">
                        <div id="calcAreaPerPiece" class="text-xs font-bold text-gray-900"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Calculator Input -->
                <div class="mb-4">
                  <div class="flex items-center gap-3">
                    <div class="flex-1">
                      <label class="block text-xs font-semibold text-gray-900 mb-1">
                        <i class="fas fa-ruler-combined text-gray-700 mr-1"></i>
                        Area (sqm)
                      </label>
                      <div class="bg-gray-50 rounded">
                        <input type="number" id="userArea" step="0.01" placeholder="Enter area"
                          oninput="calculateFromArea()"
                          class="w-full px-3 py-2 bg-transparent text-center text-sm text-gray-900 outline-none border border-gray-200 rounded">
                      </div>
                    </div>
                    <div class="text-gray-700 text-xl pt-5">
                      <i class="fas fa-arrow-right"></i>
                    </div>
                    <div class="flex-1">
                      <label class="block text-xs font-semibold text-gray-900 mb-1">
                        <i class="fas fa-box text-gray-700 mr-1"></i>
                        Pieces
                      </label>
                      <div class="bg-gray-100 rounded border border-gray-300">
                        <div id="piecesFromArea" class="px-3 py-2 text-center text-sm font-bold text-gray-900">0</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Additional Results -->
                <div id="userCalculationResults" class="hidden">
                  <div class="bg-gray-100 rounded-lg p-3 border border-gray-300">
                    <h4 class="text-xs font-semibold text-gray-900 mb-2">
                      <i class="fas fa-tools text-gray-700 mr-1"></i>
                      Additional Materials Needed
                    </h4>
                    <div class="grid grid-cols-2 gap-3">
                      <div class="bg-white rounded px-3 py-2 text-center border border-gray-200">
                        <label class="block text-xs text-gray-600 mb-1">Adhesive</label>
                        <div class="text-sm font-bold text-gray-900">
                          <span id="userAdhesiveNeededKg">0</span> kg
                          <div class="text-xs text-gray-600 mt-1" id="userAdhesiveBagsDisplay">(<span
                              id="userAdhesiveBags">0</span> bags, buy <span id="userAdhesiveWholeBags">0</span>)</div>
                        </div>
                      </div>
                      <div class="bg-white rounded px-3 py-2 text-center border border-gray-200">
                        <label class="block text-xs text-gray-600 mb-1">Brackets</label>
                        <div class="text-sm font-bold text-gray-900">
                          <span id="userBracketsNeeded">0</span> pcs
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <script>
                let selectedVariantDimensions = {
                  width: 0,
                  height: 0,
                  length: 0,
                  size: '',
                  areaPerPiece: 0
                };

                function updateCalculatorFromVariant(button) {
                  const width = parseFloat(button.dataset.width) || 0;
                  const height = parseFloat(button.dataset.height) || 0;
                  const length = parseFloat(button.dataset.length) || 0;
                  // prefer data-size if available, otherwise fallback to inner text
                  const size = button.dataset.size || (button.querySelector('.text-gray-700') ? button.querySelector('.text-gray-700').textContent.trim() : '');

                  // Convert mm to meters
                  const widthM = width / 1000;
                  const heightM = height / 1000;
                  const areaPerPiece = widthM * heightM;

                  // Store dimensions
                  selectedVariantDimensions = {
                    width,
                    height,
                    length,
                    size,
                    areaPerPiece
                  };

                  // Show calculator section
                  const calcSection = document.getElementById('calculatorSection');
                  if (calcSection) {
                    calcSection.classList.remove('hidden');
                  }

                  // SET SIZE ONCE - THIS WILL NOT CHANGE
                  const sizeDisplay = document.getElementById('selectedSizeDisplay');
                  if (sizeDisplay) {
                    sizeDisplay.textContent = size || `${width}×${height}`;
                  }

                  // Update dimension displays
                  const widthEl = document.getElementById('calcWidth');
                  const heightEl = document.getElementById('calcHeight');
                  const lengthEl = document.getElementById('calcLength');
                  const areaEl = document.getElementById('calcAreaPerPiece');

                  if (widthEl) widthEl.textContent = width || '-';
                  if (heightEl) heightEl.textContent = height || '-';
                  if (lengthEl) lengthEl.textContent = length || '-';
                  if (areaEl) areaEl.textContent = areaPerPiece > 0 ? areaPerPiece.toFixed(4) + ' m²' : '-';

                  // Clear previous calculations
                  const areaInput = document.getElementById('userArea');
                  const piecesDisplay = document.getElementById('piecesFromArea');
                  const resultsSection = document.getElementById('userCalculationResults');

                  if (areaInput) areaInput.value = '';
                  if (piecesDisplay) piecesDisplay.textContent = '0';
                  if (resultsSection) resultsSection.classList.add('hidden');
                }

                function calculateFromArea() {
                  const areaInput = document.getElementById('userArea');
                  const piecesDisplay = document.getElementById('piecesFromArea');
                  const resultsSection = document.getElementById('userCalculationResults');
                  const adhesiveKgEl = document.getElementById('userAdhesiveNeededKg');
                  const adhesiveBagsEl = document.getElementById('userAdhesiveBags');
                  const adhesiveWholeBagsEl = document.getElementById('userAdhesiveWholeBags');
                  const bracketsEl = document.getElementById('userBracketsNeeded');

                  if (!areaInput || !piecesDisplay) return;

                  const area = parseFloat(areaInput.value);

                  // Validation
                  if (!area || area <= 0) {
                    piecesDisplay.textContent = '0';
                    if (resultsSection) resultsSection.classList.add('hidden');
                    return;
                  }

                  if (!selectedVariantDimensions.areaPerPiece || selectedVariantDimensions.areaPerPiece <= 0) {
                    piecesDisplay.textContent = '0';
                    if (resultsSection) resultsSection.classList.add('hidden');
                    return;
                  }

                  // CALCULATE PIECES NEEDED (SIZE STAYS THE SAME)
                  const piecesNeeded = Math.ceil(area / selectedVariantDimensions.areaPerPiece);

                  // ---------- ADHESIVE CALC (explicit units) ----------
                  // Default adhesive consumption in kg per sqm (adjustable)
                  const adhesiveRateKgPerSqm = 3.0; // default: 3 kg per sqm (changeable)
                  const bagWeightKg = 20.0; // default: 20 kg per bag (change if your product uses 25kg bags)

                  // adhesive in kilograms required
                  const adhesiveKgNeeded = +(area * adhesiveRateKgPerSqm).toFixed(2);

                  // adhesive in sacks/bags (float). Round up to get whole bags if you buy by bag.
                  const adhesiveBagsNeededFloat = +(adhesiveKgNeeded / bagWeightKg).toFixed(3);
                  const adhesiveWholeBags = Math.ceil(adhesiveBagsNeededFloat);

                  // ---------- BRACKETS ----------
                  // Rate: 0.25 means 1 bracket per 4 pieces (adjustable)
                  const bracketRatePerPiece = 0.25;
                  const bracketsNeeded = Math.ceil(piecesNeeded * bracketRatePerPiece);

                  // UPDATE DISPLAY - SIZE DOES NOT CHANGE
                  piecesDisplay.textContent = piecesNeeded.toLocaleString();

                  if (adhesiveKgEl) adhesiveKgEl.textContent = adhesiveKgNeeded.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  });
                  if (adhesiveBagsEl) adhesiveBagsEl.textContent = adhesiveBagsNeededFloat;
                  if (adhesiveWholeBagsEl) adhesiveWholeBagsEl.textContent = adhesiveWholeBags;
                  if (bracketsEl) bracketsEl.textContent = bracketsNeeded.toLocaleString();
                  if (resultsSection) resultsSection.classList.remove('hidden');
                }

                // DISABLE KEYBOARD SHORTCUTS COMPLETELY WHEN IN INPUTS
                document.addEventListener('keydown', function (e) {
                  const activeElement = document.activeElement;

                  if (activeElement &&
                    (activeElement.tagName === 'INPUT' ||
                      activeElement.tagName === 'TEXTAREA' ||
                      activeElement.isContentEditable)) {
                    // Let the user type freely
                    return;
                  }

                  // ESC key to close modals (only when NOT typing)
                  if (e.key === 'Escape') {
                    if (typeof closeContactModal === 'function') {
                      closeContactModal();
                    }
                  }

                  // Number keys to select variants (ONLY when NOT in ANY input field)
                  if (e.key >= '1' && e.key <= '9' && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    const variantButtons = document.querySelectorAll('.variant-btn:not([disabled])');
                    const index = parseInt(e.key) - 1;
                    if (variantButtons[index]) {
                      e.preventDefault();
                      e.stopPropagation();
                      variantButtons[index].click();
                    }
                  }
                });

                // Prevent keydown on area input from triggering shortcuts
                document.addEventListener('DOMContentLoaded', function () {
                  const areaInput = document.getElementById('userArea');
                  if (areaInput) {
                    areaInput.addEventListener('keydown', function (e) {
                      e.stopPropagation();
                    });
                  }
                });

                // CLEAR CALCULATOR FUNCTION
                function clearCalculator() {
                  const calcSection = document.getElementById('calculatorSection');
                  const areaInput = document.getElementById('userArea');
                  const piecesDisplay = document.getElementById('piecesFromArea');
                  const resultsSection = document.getElementById('userCalculationResults');
                  const adhesiveKgEl = document.getElementById('userAdhesiveNeededKg');
                  const adhesiveBagsEl = document.getElementById('userAdhesiveBags');
                  const adhesiveWholeBagsEl = document.getElementById('userAdhesiveWholeBags');
                  const bracketsEl = document.getElementById('userBracketsNeeded');

                  if (calcSection) calcSection.classList.add('hidden');
                  if (areaInput) areaInput.value = '';
                  if (piecesDisplay) piecesDisplay.textContent = '0';
                  if (resultsSection) resultsSection.classList.add('hidden');

                  if (adhesiveKgEl) adhesiveKgEl.textContent = '0';
                  if (adhesiveBagsEl) adhesiveBagsEl.textContent = '0';
                  if (adhesiveWholeBagsEl) adhesiveWholeBagsEl.textContent = '0';
                  if (bracketsEl) bracketsEl.textContent = '0';

                  selectedVariantDimensions = {
                    width: 0,
                    height: 0,
                    length: 0,
                    size: '',
                    areaPerPiece: 0
                  };
                }
              </script>
            <?php endif; ?>


            <!-- SKU Info Display Section -->
            <div id="sku-info-display" class="hidden mt-4 p-2 lg:p-4 bg-gray-50 border border-gray-200 rounded-lg">
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
                <button id="toggle-sku-btn" onclick="toggleSkuContent()"
                  class="mt-3 text-orange-600 hover:text-orange-700 font-medium text-sm flex items-center gap-1 transition">
                  <span id="toggle-sku-text">See More</span>
                  <i id="toggle-sku-icon" class="fas fa-chevron-down text-xs"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- STEP 4: QUANTITY SELECTION -->
          <div class="step-section p-2">
            <div class="flex items-start justify-start mb-2">
              <h3 class="text-base lg:text-lg font-semibold text-gray-900">
                Quantity
              </h3>
            </div>

            <!-- Quantity Controls -->
            <div class="flex items-start justify-start gap-2 mb-3">
              <button type="button" onclick="decreaseQuantity()"
                class="w-9 h-9 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg flex items-center justify-center transition"
                id="decreaseBtn">
                <i class="fas fa-minus text-sm"></i>
              </button>

              <input type="number" id="quantityInput" name="quantity" value="1" min="1" max="9999"
                class="w-20 text-center text-base font-semibold border-2 border-gray-300 rounded-lg py-1 focus:outline-none focus:border-orange-500"
                onchange="validateQuantity()" oninput="validateQuantity()">

              <button type="button" onclick="increaseQuantity()"
                class="w-9 h-9 bg-orange-500 hover:bg-orange-600 text-white rounded-lg flex items-center justify-center transition"
                id="increaseBtn">
                <i class="fas fa-plus text-sm"></i>
              </button>
            </div>

            <!-- Quick Buttons -->
            <div class="flex flex-wrap gap-2 justify-start mb-3">
              <button type="button" onclick="setQuantity(5)"
                class="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-orange-100 hover:text-orange-600 rounded-lg transition">5</button>
              <button type="button" onclick="setQuantity(10)"
                class="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-orange-100 hover:text-orange-600 rounded-lg transition">10</button>
              <button type="button" onclick="setQuantity(25)"
                class="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-orange-100 hover:text-orange-600 rounded-lg transition">25</button>
              <button type="button" onclick="setQuantity(50)"
                class="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-orange-100 hover:text-orange-600 rounded-lg transition">50</button>
            </div>

            <!-- Preview -->
            <div id="quantityPricePreview" class="mt-3 p-3 hidden">
              <div class="flex justify-between items-center text-sm">
                <span class="text-gray-700 font-medium"><span id="previewQty">1</span> pcs</span>
                <span class="font-bold text-red-600 text-lg" id="previewTotal">₱0.00</span>
              </div>
            </div>
          </div>

          <!-- PURCHASE SECTION -->
          <div
            class="mt-6 p-2 sticky bottom-0 lg:relative bg-white lg:bg-transparent pt-4 lg:pt-0 border-t lg:border-0 border-gray-200 z-10 shadow-lg lg:shadow-none">
            <form id="productForm" method="POST" class="space-y-3 lg:space-y-4">
              <input type="hidden" name="product_id" value="<?= $product_id ?>" />
              <input type="hidden" name="selected_color_id" id="selected_color_id">
              <input type="hidden" name="selected_color" id="selected_color">
              <input type="hidden" name="selected_type" id="selected_type">
              <input type="hidden" name="selected_variant" id="selected_variant">
              <input type="hidden" name="variant_id" id="variant_id">
              <input type="hidden" name="is_windows" value="0" />



              <!-- Total Price Display -->
              <div class="bg-linear-to-r from-green-50 to-blue-50 p-2 lg:p-5 ">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                  <div>
                    <p class="text-xs lg:text-sm text-gray-600 mb-1 font-medium">Total Price</p>
                    <p id="totalPrice" class="text-2xl lg:text-3xl font-bold text-green-600">₱0.00</p>
                  </div>
                  <div id="selectionStatus" class="text-xs lg:text-sm text-gray-500 sm:text-right">
                    Complete steps 1-3 to see price
                  </div>
                </div>
              </div>

              <!-- CUSTOMIZE BUTTON - Shows only for Windows products -->
              <?php if ($is_windows_category): ?>
                <div id="customizeButtonContainer">
                  <button type="button" onclick="openCustomizeModal()"
                    class="w-full py-3 lg:py-4 text-sm lg:text-lg font-semibold transition-all duration-300 bg-black text-white hover:bg-orange-500 ">
                    <span class="flex items-center justify-center gap-2">
                      Quote Customize
                    </span>
                  </button>
                </div>
              <?php endif; ?>
              <div class="flex gap-2 lg:gap-3 w-full">
                <button type="submit" id="addToCartBtn" disabled
                  class="flex-1 py-3 lg:py-4 text-sm lg:text-lg font-semibold transition-all duration-300 bg-gray-400 text-white disabled:cursor-not-allowed disabled:opacity-75 "
                  style="font-family: 'Montserrat', sans-serif; ">
                  <span id="btnText" class="flex items-center justify-center gap-2">
                    <i class="fas fa-shopping-cart text-sm lg:text-base"></i>
                    Add to Cart
                  </span>
                </button>

                <button type="button" onclick="window.location.href='index-cart_view-page-8.php'"
                  class="flex-1 py-3 lg:py-4 text-sm lg:text-lg font-semibold transition-all duration-300 bg-black hover:bg-orange-500 text-white"
                  style="font-family: 'Montserrat', sans-serif;">
                  <span class="flex items-center justify-center gap-2">
                    <i class="fas fa-shopping-cart text-sm lg:text-base"></i>
                    View Cart
                  </span>
                </button>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>



  <style>
    /* Step Section Spacing */
    .step-section {
      padding-bottom: 1.5rem;
      margin-bottom: 1.5rem;
      border-bottom: 1px solid #e5e7eb;
    }

    .step-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
    }

    @media (min-width: 1024px) {
      .step-section {
        padding-bottom: 2rem;
        margin-bottom: 2rem;
      }
    }

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

    /* Selected state for buttons */
    .type-btn.selected,
    .color-btn.selected,
    .variant-btn.selected {
      border-color: #f97316 !important;
      background-color: #fff7ed !important;
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
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

    /* Remove spinner from number input */
    #quantityInput::-webkit-outer-spin-button,
    #quantityInput::-webkit-inner-spin-button {
      -webkit-appearance: none;
      appearance: none;
      margin: 0;
    }

    #quantityInput[type=number] {
      -webkit-appearance: textfield;
      -moz-appearance: textfield;
      appearance: textfield;
    }
  </style>

  <?php if ($is_windows_category): ?>
    <?php include 'index-product-windows-customize-modal.php'; ?>
  <?php endif; ?>

  <?php if ($related_products->num_rows > 0): ?>
    <?php include 'index-product-related-products.php'; ?>
  <?php endif; ?>

  <?php if (!empty($product_specs)): ?>
    <section class="mt-6 lg:mt-8 px-4 lg:px-0 max-w-7xl mx-auto">
      <div class="bg-white rounded-xl overflow-hidden shadow-sm">

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
          <div class="flex">
            <button onclick="switchTab('specifications')" id="tab-specifications"
              class="product-tab flex-1 px-4 lg:px-6 py-3 lg:py-4 text-sm lg:text-base font-semibold  hover:text-orange-600 border-b-2 border-transparent transition-all duration-200 active"
              style="font-family: 'Montserrat', sans-serif; color: #2f1200">
              <i class="fas fa-list-alt mr-2"></i>
              Product Specifications
            </button>

            <button onclick="switchTab('reviews')" id="tab-reviews"
              class="product-tab flex-1 px-4 lg:px-6 py-3 lg:py-4 text-sm lg:text-base font-semibold  hover:text-orange-600 border-b-2 border-transparent transition-all duration-200"
              style="font-family: 'Montserrat', sans-serif; color: #2f1200">
              <i class="fas fa-star mr-2"></i>
              Reviews
              <?php if ($total_raters > 0): ?>
                <span class="ml-1 text-md bg-orange-500 text-white px-2 py-0.5 rounded-full"><?= $total_raters ?></span>
              <?php endif; ?>
            </button>

            <button onclick="switchTab('productinfo')" id="tab-productinfo"
              class="product-tab flex-1 px-4 lg:px-6 py-3 lg:py-4 text-sm lg:text-base font-semibold  hover:text-orange-600 border-b-2 border-transparent transition-all duration-200"
              style="font-family: 'Montserrat', sans-serif; color: #2f1200">
              <i class="fas fa-info-circle mr-2"></i>
              Product Information
            </button>
          </div>
        </div>

        <!-- Tab Content -->
        <div class="p-4 lg:p-8">

          <!-- Specifications Tab Content -->
          <div id="content-specifications" class="tab-content">
            <div class="space-y-6">


              <!-- Description 1: Product Details -->
              <?php if (!empty($product_specs['descrip1'])): ?>
                <div>
                  <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-align-left mr-3"></i>
                    Product Details
                  </h3>
                  <div class="text-gray-700 text-base space-y-2">
                    <?php
                    $lines = explode("\n", $product_specs['descrip1']);
                    foreach ($lines as $line):
                      $trimmed = trim($line);
                      if (!empty($trimmed)):
                        ?>
                        <div class="py-1 border-b border-gray-200 last:border-b-0">
                          <?= htmlspecialchars($trimmed) ?>
                        </div>
                        <?php
                      endif;
                    endforeach;
                    ?>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Description 6 & 7: Unit & Specifications -->
              <?php if (!empty($product_specs['descrip6']) || !empty($product_specs['descrip7'])): ?>
                <div class="space-y-3">

                  <!-- Unit Information Section -->
                  <?php if (!empty($product_specs['descrip6'])): ?>
                    <div class="py-2 border-b border-gray-200">
                      <div class="text-gray-700 text-base">
                        <?= htmlspecialchars($product_specs['descrip6']) ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- Technical Specifications Section -->
                  <?php if (!empty($product_specs['descrip7'])): ?>
                    <div class="py-2 border-b border-gray-200 last:border-b-0">
                      <div class="text-gray-700 text-base">
                        <?= htmlspecialchars($product_specs['descrip7']) ?>
                      </div>
                    </div>
                  <?php endif; ?>

                </div>
              <?php endif; ?>

              <!-- No Descriptions Message -->
              <?php if (empty($product_specs['descrip1']) && empty($product_specs['descrip6']) && empty($product_specs['descrip7'])): ?>
                <div class="bg-gray-50 rounded-xl p-6 text-center border border-dashed border-gray-300">
                  <i class="fas fa-inbox text-gray-300 text-4xl mb-3 block"></i>
                  <p class="text-gray-400 italic">No product descriptions available</p>
                </div>
              <?php endif; ?>

            </div>
          </div>

          <!-- Reviews Tab Content -->
          <div id="content-reviews" class="tab-content hidden">
            <div class="space-y-6">

              <!-- Rating Summary -->
              <div class="bg-linear-to-r from-orange-50 to-yellow-50 rounded-lg p-6 border border-orange-100">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">

                  <!-- Average Rating -->
                  <div class="text-center lg:text-left">
                    <div class="text-5xl font-bold text-orange-600 mb-2">
                      <?= $total_raters > 0 ? $avg_rating : '0.0' ?>
                    </div>
                    <div class="flex items-center justify-center lg:justify-start gap-1 mb-2">
                      <div class="flex text-yellow-400 text-xl">
                        <?php
                        if ($total_raters > 0) {
                          $full = floor($avg_rating);
                          $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
                          $empty = 5 - $full - $half;

                          for ($i = 0; $i < $full; $i++)
                            echo '<i class="fas fa-star"></i>';
                          if ($half)
                            echo '<i class="fas fa-star-half-alt"></i>';
                          for ($i = 0; $i < $empty; $i++)
                            echo '<i class="far fa-star text-gray-300"></i>';
                        } else {
                          for ($i = 0; $i < 5; $i++)
                            echo '<i class="far fa-star text-gray-300"></i>';
                        }
                        ?>
                      </div>
                    </div>
                    <div class="text-sm text-gray-600">
                      Based on <?= $total_raters ?> review<?= $total_raters == 1 ? '' : 's' ?>
                    </div>
                  </div>

                  <!-- Rating Distribution -->
                  <?php if ($total_raters > 0): ?>
                    <div class="flex-1 max-w-md">
                      <?php
                      // Get rating distribution
                      $rating_dist_query = $conn->prepare("
                      SELECT rating, COUNT(*) as count 
                      FROM product_ratings 
                      WHERE product_id = ? 
                      GROUP BY rating 
                      ORDER BY rating DESC
                    ");
                      $rating_dist_query->bind_param("i", $product_id);
                      $rating_dist_query->execute();
                      $rating_dist_result = $rating_dist_query->get_result();

                      $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                      while ($dist = $rating_dist_result->fetch_assoc()) {
                        $rating_counts[$dist['rating']] = $dist['count'];
                      }
                      $rating_dist_query->close();

                      foreach ([5, 4, 3, 2, 1] as $star):
                        $count = $rating_counts[$star];
                        $percentage = $total_raters > 0 ? ($count / $total_raters) * 100 : 0;
                        ?>
                        <div class="flex items-center gap-2 mb-2">
                          <div class="flex items-center gap-1 w-16">
                            <span class="text-sm font-medium text-gray-700"><?= $star ?></span>
                            <i class="fas fa-star text-yellow-400 text-xs"></i>
                          </div>
                          <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full transition-all duration-300"
                              style="width: <?= $percentage ?>%"></div>
                          </div>
                          <span class="text-sm text-gray-600 w-12 text-right"><?= $count ?></span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Reviews List -->
              <?php if ($total_raters > 0): ?>
                <div class="space-y-4">
                  <h3 class="text-lg font-semibold text-gray-800 mb-4">Customer Reviews</h3>

                  <?php
                  // Fetch all reviews for this product
                  $reviews_query = $conn->prepare("
                  SELECT pr.*, u.name as user_name, u.profile_picture 
                  FROM product_ratings pr
                  LEFT JOIN users u ON pr.user_id = u.id
                  WHERE pr.product_id = ?
                  ORDER BY pr.created_at DESC
                  LIMIT 10
                ");
                  $reviews_query->bind_param("i", $product_id);
                  $reviews_query->execute();
                  $reviews_result = $reviews_query->get_result();

                  while ($review = $reviews_result->fetch_assoc()):
                    ?>
                    <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                      <div class="flex items-start gap-4">

                        <!-- User Avatar -->
                        <div class="shrink-0">
                          <?php if (!empty($review['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($review['profile_picture']) ?>"
                              alt="<?= htmlspecialchars($review['user_name']) ?>" class="w-10 h-10 rounded-full object-cover">
                          <?php else: ?>
                            <div
                              class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center text-white font-semibold">
                              <?= strtoupper(substr($review['user_name'], 0, 1)) ?>
                            </div>
                          <?php endif; ?>
                        </div>

                        <!-- Review Content -->
                        <div class="flex-1">
                          <div class="flex items-center justify-between mb-2">
                            <div>
                              <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($review['user_name']) ?></h4>
                              <div class="flex items-center gap-2 mt-1">
                                <div class="flex text-yellow-400 text-sm">
                                  <?php
                                  $review_rating = (int) $review['rating'];
                                  for ($i = 0; $i < $review_rating; $i++)
                                    echo '<i class="fas fa-star"></i>';
                                  for ($i = $review_rating; $i < 5; $i++)
                                    echo '<i class="far fa-star text-gray-300"></i>';
                                  ?>
                                </div>
                                <span class="text-xs text-gray-500">
                                  <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                </span>
                              </div>
                            </div>
                          </div>
                          <!-- Review Title/Comment -->
                          <?php if (!empty($review['comment'])): ?>
                            <h5 class="font-semibold text-gray-700 text-sm mb-2">
                              <?= htmlspecialchars($review['comment']) ?>
                            </h5>
                          <?php endif; ?>
                          <?php if (!empty($review['review'])): ?>
                            <p class="text-gray-700 text-sm leading-relaxed">
                              <?= nl2br(htmlspecialchars($review['review'])) ?>
                            </p>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                  <?php $reviews_query->close(); ?>
                </div>
              <?php else: ?>
                <!-- No Reviews State -->
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                  <div class="w-20 h-20 mx-auto mb-4 bg-gray-200 rounded-full flex items-center justify-center">
                    <i class="fas fa-star text-3xl text-gray-400"></i>
                  </div>
                  <h3 class="text-xl font-semibold text-gray-700 mb-2">No Reviews Yet</h3>
                  <p class="text-gray-500 text-sm">Be the first to review this product!</p>
                </div>
              <?php endif; ?>
            </div>
          </div>


          <!-- Product Info Tab Content -->
          <div id="content-productinfo" class="tab-content hidden">

            <!-- Hero Section with Gradient Background -->
            <div class="relative overflow-hidden rounded-2xl p-8 mb-8">
              <div class="relative z-10 text-center">
                <h2 class="text-3xl font-bold text-black mb-2">Product Details</h2>
                <p class="text-black max-w-2xl mx-auto">Explore high-quality images and comprehensive information about
                  this product</p>
              </div>
            </div>

            <div class="space-y-8">

              <!-- PRODUCT SPECIFICATIONS SECTION -->
              <?php
              $has_specs = false;
              for ($i = 1; $i <= 10; $i++) {
                if (!empty($product_specs["descrip$i"])) {
                  $has_specs = true;
                  break;
                }
              }
              ?>

              <!-- PRODUCT IMAGES SECTION -->
              <div class="bg-white rounded-2xl overflow-hidden shadow-sm">
                <!-- Section Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">

                      <div>
                        <h3 class="text-lg font-semibold text-gray-900">Product</h3>
                        <p class="text-xs text-gray-500">High-quality product images</p>
                      </div>
                    </div>
                    <!-- ✅ FIXED: $product_images already processed above - no need to decode again! -->
                    <?php if (!empty($product_images)): ?>
                      <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                        <?= count($product_images) ?>     <?= count($product_images) == 1 ? 'Image' : 'Images' ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Images Grid -->
                <div class="p-6">
                  <?php if (!empty($product_images)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                      <!-- ✅ FIXED: Use pre-processed $product_images array -->
                      <?php foreach ($product_images as $imageData): ?>
                        <div
                          class="group relative overflow-hidden rounded-xl bg-linear-to-br from-gray-50 to-gray-100 shadow-md hover:shadow-2xl transition-all duration-300">

                          <!-- Zoom Icon -->
                          <div
                            class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 shadow-lg">
                            <i class="fas fa-search-plus text-gray-700 text-sm"></i>
                          </div>

                          <!-- Image -->
                          <div class="aspect-square relative overflow-hidden cursor-pointer"
                            onclick="openImageModal('<?= htmlspecialchars($imageData['src']) ?>')">
                            <img src="<?= htmlspecialchars($imageData['src']) ?>"
                              alt="Product Image <?= $imageData['index'] + 1 ?>"
                              class="w-full h-full object-cover transition-all duration-500 group-hover:scale-110 group-hover:brightness-110"
                              loading="lazy"
                              onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                            <!-- Error State -->
                            <div class="hidden absolute inset-0 items-center justify-center bg-red-50 text-red-500">
                              <div class="text-center">
                                <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                                <div class="text-sm font-medium">Image Not Found</div>
                              </div>
                            </div>
                          </div>

                          <!-- Hover Overlay -->
                          <div
                            class="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
                            <span class="text-white text-sm font-medium">Click to enlarge</span>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-16 px-4">
                      <div
                        class="inline-flex items-center justify-center w-20 h-20 bg-linear-to-br from-gray-100 to-gray-200 rounded-2xl mb-4">
                        <i class="fas fa-images text-4xl text-gray-400"></i>
                      </div>
                      <h4 class="text-lg font-semibold text-gray-700 mb-2">No Images Available</h4>
                      <p class="text-sm text-gray-500 max-w-sm mx-auto">Product images haven't been uploaded yet. Check back
                        later for visual content.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- DETAILED DESCRIPTION SECTION - DESCRIPTIONPIC -->
              <?php if (!empty($product_specs['descriptionpic'])): ?>
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm">
                  <!-- Section Header -->
                  <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                      <div>
                        <h3 class="text-lg font-semibold text-gray-900">Detailed Description</h3>
                        <p class="text-xs text-gray-500">Complete product information</p>
                      </div>
                    </div>
                  </div>

                  <!-- Description Content -->
                  <div class="p-6">
                    <div class=" rounded-xl p-6">
                      <div class="prose prose-gray max-w-none">
                        <p class="text-gray-800 leading-relaxed text-base">
                          <?= nl2br(htmlspecialchars($product_specs['descriptionpic'])) ?>
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
    </section>

    <style>
      /* Tab Styles */
      .product-tab {
        position: relative;
      }

      .product-tab.active {
        color: #f97316;
        /* orange-600 */
        border-bottom-color: #f97316;
        background-color: #fff7ed;
        /* orange-50 */
      }

      .tab-content {
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

      /* Smooth transitions */
      .product-tab:hover {
        background-color: #f9fafb;
      }

      /* Image hover effect for product info tab */
      .image-hover {
        transition: transform 0.2s ease;
        cursor: pointer;
      }

      .image-hover:hover {
        transform: scale(1.02);
      }
    </style>

    <script>
      function switchTab(tabName) {
        // Hide all tab contents
        const allContents = document.querySelectorAll('.tab-content');
        allContents.forEach(content => {
          content.classList.add('hidden');
        });

        // Remove active class from all tabs
        const allTabs = document.querySelectorAll('.product-tab');
        allTabs.forEach(tab => {
          tab.classList.remove('active');
        });

        // Show selected tab content
        const selectedContent = document.getElementById('content-' + tabName);
        if (selectedContent) {
          selectedContent.classList.remove('hidden');
        }

        // Add active class to selected tab
        const selectedTab = document.getElementById('tab-' + tabName);
        if (selectedTab) {
          selectedTab.classList.add('active');
        }

        // Save active tab to localStorage
        localStorage.setItem('activeProductTab', tabName);
      }

      // Open image modal
      function openImageModal(src) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('productImageModal');
        if (!modal) {
          modal = document.createElement('div');
          modal.id = 'productImageModal';
          modal.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4 hidden';
          modal.onclick = closeImageModal;

          const modalContent = document.createElement('div');
          modalContent.className = 'relative max-w-4xl max-h-full';
          modalContent.onclick = function (e) {
            e.stopPropagation();
          };

          const closeBtn = document.createElement('button');
          closeBtn.onclick = closeImageModal;
          closeBtn.className = 'absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl';
          closeBtn.innerHTML = '<i class="fas fa-times"></i>';

          const img = document.createElement('img');
          img.id = 'modalProductImage';
          img.className = 'max-w-full max-h-screen object-contain rounded-lg';

          modalContent.appendChild(closeBtn);
          modalContent.appendChild(img);
          modal.appendChild(modalContent);
          document.body.appendChild(modal);
        }

        document.getElementById('modalProductImage').src = src;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }

      // Close image modal
      function closeImageModal() {
        const modal = document.getElementById('productImageModal');
        if (modal) {
          modal.classList.add('hidden');
          document.body.style.overflow = 'auto';
        }
      }

      // Share product function
      function shareProduct() {
        if (navigator.share) {
          navigator.share({
            title: document.title,
            text: 'Check out this product!',
            url: window.location.href
          }).catch(err => console.log('Error sharing:', err));
        } else {
          // Fallback to copy link
          navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Product link copied to clipboard!');
          }).catch(err => {
            console.log('Error copying link:', err);
            alert('Could not copy link. Please copy manually: ' + window.location.href);
          });
        }
      }

      // Restore active tab on page load
      document.addEventListener('DOMContentLoaded', function () {
        const savedTab = localStorage.getItem('activeProductTab') || 'specifications';
        switchTab(savedTab);

        // Close modal with escape key
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') {
            closeImageModal();
          }
        });
      });
    </script>
  <?php endif; ?>

  <script
    src="js/index-product-view-junction-stock.obfuscated.js?v=<?= filemtime('js/index-product-view-junction-stock.obfuscated.js') ?>"></script>

  <script
    src="js/index-product-view-page-4-AA.obfuscated.js?v=<?= filemtime('js/index-product-view-page-4-AA.obfuscated.js') ?>"></script>

  <?php include '../navbar/footer.php'; ?>

  <script>
    // ✅ REAL-TIME TIMER - Accurate countdown
    function initFlashSaleTimers() {
      console.log('🔥 Initializing flash sale timers...');

      const timerBadges = document.querySelectorAll('.timer-badge');
      console.log(`Found ${timerBadges.length} timer badges`);

      if (timerBadges.length === 0) return;

      // Get server time (synced once at page load)
      const serverTimeElement = document.querySelector('meta[name="server-time"]');
      const serverTime = serverTimeElement ? parseInt(serverTimeElement.getAttribute('content')) : Math.floor(Date.now() / 1000);

      // Calculate offset
      const clientTime = Math.floor(Date.now() / 1000);
      const timeOffset = serverTime - clientTime;

      console.log(`📅 Server time: ${new Date(serverTime * 1000).toLocaleString('en-US', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
      })}`);
      console.log(`⏱️ Time offset: ${timeOffset}s`);

      timerBadges.forEach((badge) => {
        const endTime = parseInt(badge.dataset.endTime); // Unix timestamp from database
        const variantId = badge.dataset.variantId;
        const timerDisplay = badge.querySelector('.timer-display');

        if (!endTime || !timerDisplay) {
          console.warn(`⚠️ Invalid timer for variant ${variantId}`);
          return;
        }

        console.log(`\n✅ Timer for variant ${variantId}:`);
        console.log(`   End time (unix): ${endTime}`);
        console.log(`   End time (readable): ${new Date(endTime * 1000).toLocaleString('en-US', {
          year: 'numeric', month: '2-digit', day: '2-digit',
          hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        })}`);

        let timerInterval;

        function updateTimer() {
          // Calculate current time with server offset
          const now = Math.floor(Date.now() / 1000) + timeOffset;
          const remaining = endTime - now;

          // Timer expired
          if (remaining <= 0) {
            timerDisplay.textContent = 'EXPIRED';
            badge.classList.remove('bg-red-700', 'bg-red-700', 'bg-red-800', 'animate-bounce');
            badge.classList.add('bg-gray-400');
            badge.style.pointerEvents = 'none';
            badge.style.opacity = '0.5';
            clearInterval(timerInterval);
            console.log(`❌ Timer EXPIRED for variant ${variantId}`);
            return;
          }

          // Calculate hours:minutes:seconds
          const hours = Math.floor(remaining / 3600);
          const minutes = Math.floor((remaining % 3600) / 60);
          const seconds = remaining % 60;

          // Format as HH:MM:SS
          const timeText = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
          timerDisplay.textContent = timeText;

          // Visual feedback based on remaining time
          if (remaining > 3600) {
            // Normal - more than 1 hour
            badge.classList.remove('bg-red-700', 'bg-red-800');
            badge.classList.add('bg-red-600');
          } else if (remaining > 300) {
            // Warning - between 5 min and 1 hour
            badge.classList.remove('bg-red-800');
            badge.classList.add('bg-red-600');
          } else if (remaining > 60) {
            // Urgent - between 1 min and 5 min
            badge.classList.remove('bg-red-600');
            badge.classList.add('bg-red-700');
          } else {
            // Critical - less than 1 minute
            badge.classList.remove('bg-red-600', 'bg-red-700');
            badge.classList.add('bg-red-800');
            timerDisplay.style.fontWeight = 'bold';
          }
        }

        // Initial update immediately
        updateTimer();

        // Update every second
        timerInterval = setInterval(updateTimer, 1000);
      });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initFlashSaleTimers);

    // Or if already loaded
    if (document.readyState !== 'loading') {
      initFlashSaleTimers();
    }
  </script>

</body>

</html>