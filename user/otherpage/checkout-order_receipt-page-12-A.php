<?php
// order_receipt.php
// Main order receipt display page with product rating functionality
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ============================================================================
// SESSION & AUTHENTICATION CHECK
// ============================================================================
// Verify user is logged in, redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

// ============================================================================
// ORDER ID VALIDATION
// ============================================================================
// Ensure order_id is provided and is numeric for security
if (!$order_id || !is_numeric($order_id)) {
    header('Location: order_history');
    exit;
}

// ============================================================================
// FETCH ORDER DETAILS FROM DATABASE
// ============================================================================
// Get the specific order that belongs to the current user
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();

// Redirect if order doesn't exist or doesn't belong to user
if ($order_result->num_rows === 0) {
    header('Location: order_history');
    exit;
}

$order = $order_result->fetch_assoc();
$stmt->close();

// ============================================================================
// FETCH ORDER ITEMS (PRODUCTS IN ORDER)
// ============================================================================
// Get all items in the order with product details from products table
$stmt = $conn->prepare("
    SELECT 
        oi.*,
        p.id as product_id_from_products,
        p.codename as product_category,
        p.main_image as product_image,
        p.product_name as catalog_product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? 
    ORDER BY oi.id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];
while ($row = $items_result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

// ============================================================================
// HELPER FUNCTION: GET USER'S RATING FOR A PRODUCT
// ============================================================================
// Retrieves the rating given by the current user for a specific product
function getUserRating($conn, $user_id, $product_id)
{
    // Validate product ID
    if (!$product_id || $product_id <= 0) return 0;

    $stmt = $conn->prepare("
        SELECT rating FROM product_ratings 
        WHERE user_id = ? AND product_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    // Return user's rating or 0 if no rating exists
    $rating = $result->num_rows > 0 ? $result->fetch_assoc()['rating'] : 0;
    $stmt->close();
    return $rating;
}

// ============================================================================
// HELPER FUNCTION: GET AVERAGE RATING FOR A PRODUCT
// ============================================================================
// Calculates the average rating and total number of ratings for a product
function getAverageRating($conn, $product_id)
{
    // Validate product ID
    if (!$product_id || $product_id <= 0) return ['avg_rating' => 0, 'total_ratings' => 0];

    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
        FROM product_ratings 
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    // Return average rounded to 1 decimal place and total count
    return [
        'avg_rating' => round($data['avg_rating'] ?? 0, 1),
        'total_ratings' => $data['total_ratings'] ?? 0
    ];
}

// ============================================================================
// ORDER STATUS DETERMINATION
// ============================================================================
// Check if order status allows rating (only delivered/completed orders can be rated)
$order_status = strtolower($order['status'] ?? 'pending');
$can_rate_order = in_array($order_status, ['delivered', 'completed', 'received']);

// ============================================================================
// ORDER TOTAL CALCULATION
// ============================================================================
// Calculate subtotal from all order items
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['subtotal'];
}

// Use the stored total from orders table for consistency
$final_total = $order['total'];

// Get delivery fee from order
$delivery_fee = $order['delivery_fee'] ?? 0;

// Calculate VAT amount backwards from stored total
// Formula: total = subtotal + vat + delivery
$subtotal_with_vat = $final_total - $delivery_fee;
$calculated_subtotal = $subtotal_with_vat / 1.12;
$vat_amount = $subtotal_with_vat - $calculated_subtotal;

// ============================================================================
// TOTAL MISMATCH HANDLING
// ============================================================================
// If calculated amounts don't match order items, recalculate and flag warning
if (abs($calculated_subtotal - $subtotal) > 0.01) {
    // Recalculate based on items
    $vat_rate = 0.12;
    $vat_amount = $subtotal * $vat_rate;
    $total_with_vat_and_delivery = $subtotal + $vat_amount + $delivery_fee;
    
    // Flag if final total doesn't match
    $has_total_mismatch = abs($total_with_vat_and_delivery - $final_total) > 0.01;
} else {
    $total_with_vat_and_delivery = $final_total;
    $has_total_mismatch = false;
}

// ============================================================================
// FORMAT ORDER DATE
// ============================================================================
// Convert database timestamp to readable format
$order_date = date('F j, Y g:i A', strtotime($order['created_at']));

// ============================================================================
// PRODUCT CATEGORY DISPLAY NAMES
// ============================================================================
// Map database category codes to user-friendly names
$category_names = [
    'furniture' => 'Furniture',
    'material' => 'Materials',
    'electrical' => 'Electrical',
    'lighting' => 'Lighting',
    'bedfurniture' => 'Bedroom Furniture',
    'aircon' => 'Air Conditioners',
    'doors' => 'Doors',
    'tiles' => 'Tiles',
    'windows' => 'Windows',
    'bathroom' => 'Bathroom Fixtures',
    'kitchen' => 'Kitchen Fixtures',
    'pipes' => 'Pipes',
    'aacblock' => 'AAC Blocks',
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Order Receipt - <?= htmlspecialchars($order['reference_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ============================================================================
           PRINT STYLES - Hide interactive elements when printing
           ============================================================================ */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .print-shadow {
                box-shadow: none !important;
            }
        }

        /* ============================================================================
           STAR BUTTON ANIMATIONS - Hover and transition effects
           ============================================================================ */
        .star-button {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .star-button:hover {
            transform: scale(1.1);
        }

        .star-button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* ============================================================================
           RATING SECTION STYLING - Gradient background for rating area
           ============================================================================ */
        .rating-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- ============================================================================
         BREADCRUMB NAVIGATION
         ============================================================================ -->
    <nav class="bg-white border-b border-gray-200 px-4 py-3">
        <div class="">
            <div class="flex items-center space-x-2 text-sm">
                <a href="index" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
                    <i class="fas fa-home mr-1"></i>Home
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <a href="order_history" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
                    Order History
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-600 font-medium">Receipt</span>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- ============================================================================
             RECEIPT CONTAINER - Main receipt display
             ============================================================================ -->
        <div class="bg-white shadow-lg print-shadow rounded-lg overflow-hidden" id="receipt">
            
            <!-- ============================================================================
                 RECEIPT HEADER - Company branding and order reference
                 ============================================================================ -->
            <div class="bg-orange-600 text-white p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Order Receipt</h1>
                        <p class="text-orange-100">Thank you for your order!</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold"><?= htmlspecialchars($order['reference_no']) ?></div>
                        <div class="text-orange-100 text-sm"><?= $order_date ?></div>
                    </div>
                </div>
            </div>

            <!-- ============================================================================
                 ORDER STATUS BANNER - Shows current status and rating availability
                 ============================================================================ -->
            <div class="p-4 <?= $can_rate_order ? 'bg-green-50 border-green-200' : 'bg-orange-50 border-orange-200' ?> border-b">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-<?= $can_rate_order ? 'check-circle text-green-600' : 'clock text-orange-600' ?> text-2xl"></i>
                        <div>
                            <div class="font-semibold <?= $can_rate_order ? 'text-green-800' : 'text-orange-800' ?>">
                                Order Status: <?= ucfirst($order_status) ?>
                            </div>
                            <div class="text-sm <?= $can_rate_order ? 'text-green-600' : 'text-orange-600' ?>">
                                <?= $can_rate_order ? 'You can now rate the products below' : 'Product rating will be available once order is delivered' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================================
                 CUSTOMER & DELIVERY INFORMATION SECTION
                 ============================================================================ -->
            <div class="p-6 grid md:grid-cols-2 gap-8 border-b">
                <!-- CUSTOMER INFO COLUMN -->
                <div>
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Customer Information
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div><span class="font-medium">Name:</span> <?= htmlspecialchars($order['customer_name']) ?></div>
                        <div><span class="font-medium">Email:</span> <?= htmlspecialchars($order['email']) ?></div>
                        <div><span class="font-medium">Mobile:</span> <?= htmlspecialchars($order['mobile']) ?></div>
                    </div>
                </div>

                <!-- DELIVERY INFO COLUMN -->
                <div>
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Delivery Address
                    </h3>
                    <div class="text-sm text-gray-700">
                        <?= nl2br(htmlspecialchars($order['address'])) ?>
                        <div class="mt-1"><span class="font-medium">ZIP Code:</span> <?= htmlspecialchars($order['zipcode']) ?></div>
                    </div>
                </div>
            </div>

            <!-- ============================================================================
                 ORDER ITEMS SECTION WITH RATING FUNCTIONALITY
                 ============================================================================ -->
            <div class="p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Order Details
                </h3>
                <div class="space-y-6">
                    <!-- ============================================================================
                         LOOP THROUGH EACH ORDER ITEM
                         ============================================================================ -->
                    <?php foreach ($order_items as $index => $item):
                        // ============================================================================
                        // PRODUCT ID & CATEGORY RETRIEVAL
                        // ============================================================================
                        // Get product ID from joined products table
                        // $product_id_from_products comes from the LEFT JOIN with products table
                        $product_id = $item['product_id_from_products'] ?? 0;
                        
                        // Get product category codename (e.g., 'furniture', 'electrical')
                        $category = $item['product_category'] ?? 'general';
                        
                        // Convert category codename to display name using category_names array
                        $category_display = $category_names[$category] ?? ucfirst($category);
                        
                        // ============================================================================
                        // FETCH RATING DATA FOR THIS PRODUCT
                        // ============================================================================
                        // Only retrieve ratings if product exists in catalog (product_id > 0)
                        if ($product_id > 0) {
                            // Get the user's current rating for this product (if any)
                            $user_rating = getUserRating($conn, $user_id, $product_id);
                            
                            // Get average rating and total number of ratings from all users
                            $rating_data = getAverageRating($conn, $product_id);
                            $avg_rating = $rating_data['avg_rating'];
                            $total_ratings = $rating_data['total_ratings'];
                        } else {
                            // If product not found, set all ratings to 0
                            $user_rating = 0;
                            $avg_rating = 0;
                            $total_ratings = 0;
                        }
                    ?>
                        <!-- ============================================================================
                             INDIVIDUAL ORDER ITEM CARD - Card container for each product
                             ============================================================================ -->
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <!-- ============================================================================
                                 PRODUCT DETAILS GRID - Name, specs, quantity, price in 5-column layout
                                 ============================================================================ -->
                            <div class="grid md:grid-cols-5 gap-4 items-start mb-4">
                                <!-- ============================================================================
                                     COLUMN 1-2: PRODUCT NAME (2 columns wide)
                                     ============================================================================ -->
                                <!-- Shows the product name from order_items table -->
                                <div class="md:col-span-2">
                                    <div class="font-medium text-gray-900 text-lg"><?= htmlspecialchars($item['product_name']) ?></div>
                                </div>

                                <!-- ============================================================================
                                     COLUMN 3: PRODUCT SPECIFICATIONS (Type, Color, Size, Origin)
                                     ============================================================================ -->
                                <!-- Display product variants and specifications if available -->
                                <div class="text-gray-600 text-sm">
                                    <div class="space-y-1">
                                        <!-- ============================================================================
                                             TYPE SPECIFICATION - Product type/variant from order
                                             ============================================================================ -->
                                        <!-- Shows product type only if type_name is not empty in order_items -->
                                        <?php if (!empty($item['type_name'])): ?>
                                            <div><span class="font-medium">Type:</span> <?= htmlspecialchars($item['type_name']) ?></div>
                                        <?php endif; ?>
                                        
                                        <!-- ============================================================================
                                             COLOR SPECIFICATION - Product color/shade variant
                                             ============================================================================ -->
                                        <!-- Shows color only if variant_color is not empty in order_items -->
                                        <?php if (!empty($item['variant_color'])): ?>
                                            <div><span class="font-medium">Color:</span> <?= htmlspecialchars($item['variant_color']) ?></div>
                                        <?php endif; ?>
                                        
                                        <!-- ============================================================================
                                             SIZE SPECIFICATION - Product dimensions/size variant
                                             ============================================================================ -->
                                        <!-- Shows size only if size field exists and is not empty/whitespace -->
                                        <?php if (!empty($item['size']) && trim($item['size']) !== ''): ?>
                                            <div><span class="font-medium">Size:</span> <?= htmlspecialchars($item['size']) ?></div>
                                        <?php endif; ?>
                                        
                                        <!-- ============================================================================
                                             ORIGIN SPECIFICATION - Local vs International product
                                             ============================================================================ -->
                                        <!-- Shows product origin with color coding (blue=local, red=international) -->
                                        <?php if (!empty($item['origin'])): ?>
                                            <?php
                                            // Check if origin contains "local" (case-insensitive)
                                            $is_local = stripos($item['origin'], 'local') !== false;
                                            // Set color: blue for local, red for international
                                            $origin_class = $is_local ? 'text-blue-600' : 'text-red-600';
                                            ?>
                                            <div class="<?= $origin_class ?> font-medium">
                                                <span class="text-gray-600">Origin:</span> <?= htmlspecialchars($item['origin']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- ============================================================================
                                     COLUMN 4: QUANTITY DISPLAY - How many units ordered
                                     ============================================================================ -->
                                <!-- Center-aligned quantity display -->
                                <div class="text-center">
                                    <div class="text-sm text-gray-600">Quantity</div>
                                    <!-- Show quantity number in large bold text -->
                                    <div class="text-xl font-bold"><?= $item['quantity'] ?></div>
                                </div>

                                <!-- ============================================================================
                                     COLUMN 5: PRICE BREAKDOWN - Unit price and line total
                                     ============================================================================ -->
                                <!-- Right-aligned price details -->
                                <div class="text-right">
                                    <!-- Label for unit price -->
                                    <div class="text-sm text-gray-600">Unit Price</div>
                                    <!-- Show unit price (price per item) formatted to 2 decimal places -->
                                    <div class="text-lg">₱<?= number_format($item['price'], 2) ?></div>
                                    <!-- Separator line between unit price and subtotal -->
                                    <hr class="my-1">
                                    <!-- Show line total (unit price × quantity) in green highlight -->
                                    <div class="text-xl font-bold text-green-700">₱<?= number_format($item['subtotal'], 2) ?></div>
                                </div>
                            </div>

                            <!-- ============================================================================
                                 RATING SECTION - Product rating interface (only shown for valid products)
                                 ============================================================================ -->
                            <!-- This section is hidden when printing and only shown for products in catalog -->
                            <?php if ($product_id > 0): ?>
                                <div class="rating-section p-4 rounded-lg no-print">
                                    <!-- ============================================================================
                                         RATING HEADER & AVERAGE RATING DISPLAY
                                         ============================================================================ -->
                                    <div class="flex items-center justify-between mb-3">
                                        <!-- Left side: Rating title and instructions -->
                                        <div>
                                            <!-- Section title with star icon -->
                                            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                                                <i class="fas fa-star text-yellow-500"></i>
                                                Rate this product
                                            </h4>
                                            <!-- Instructions based on order status -->
                                            <p class="text-sm text-gray-600">
                                                <?= $can_rate_order ? 'Share your experience with this product (rate only once)' : 'Rating available when order is delivered' ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Right side: Average rating statistics -->
                                        <div class="text-right text-sm text-gray-600">
                                            <!-- Show average rating (e.g., 4.5/5) -->
                                            <div class="font-semibold">Average: <?= $avg_rating ?>/5</div>
                                            <!-- Show total number of ratings from all customers -->
                                            <div class="text-xs"><?= $total_ratings ?> rating<?= $total_ratings != 1 ? 's' : '' ?></div>
                                        </div>
                                    </div>

                                    <!-- ============================================================================
                                         STAR RATING & STATUS DISPLAY
                                         ============================================================================ -->
                                    <div class="flex flex-col gap-4 w-full">
                                        <!-- STAR RATING BUTTONS CONTAINER -->
                                        <!-- Data attributes store product info and current rating state -->
                                        <div class="rate-stars flex items-center gap-1"
                                            data-product-id="<?= $product_id ?>"
                                            data-category="<?= htmlspecialchars($category) ?>"
                                            data-current-rating="<?= $user_rating ?>"
                                            data-can-rate="<?= $can_rate_order ? '1' : '0' ?>"
                                            data-already-rated="<?= $user_rating > 0 ? '1' : '0' ?>">
                                            
                                            <!-- ============================================================================
                                                 RENDER 5 STAR BUTTONS (1-5 stars)
                                                 ============================================================================ -->
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <!-- Individual star button for each rating value (1-5) -->
                                                <button type="button" 
                                                    class="star-button" 
                                                    data-rating="<?= $i ?>"
                                                    <?= !$can_rate_order || ($user_rating > 0) ? 'disabled' : '' ?>>
                                                    
                                                    <!-- ============================================================================
                                                         FILLED vs EMPTY STAR ICON
                                                         ============================================================================ -->
                                                    <!-- Show filled star (fas) if rating includes this star -->
                                                    <?php if ($i <= $user_rating): ?>
                                                        <i class="fas fa-star text-yellow-400 text-2xl"></i>
                                                    <!-- Show empty star (far) if rating doesn't include this star -->
                                                    <?php else: ?>
                                                        <!-- Add hover effect only if order can be rated and user hasn't rated yet -->
                                                        <i class="far fa-star text-gray-400 <?= $can_rate_order && $user_rating === 0 ? 'hover:text-yellow-400' : '' ?> text-2xl"></i>
                                                    <?php endif; ?>
                                                </button>
                                            <?php endfor; ?>

                                            <!-- ============================================================================
                                                 RATING STATUS TEXT - Shows different messages based on rating state
                                                 ============================================================================ -->
                                            
                                            <!-- STATUS 1: ALREADY RATED - Show checkmark and rating value -->
                                            <?php if ($user_rating > 0): ?>
                                                <span class="ml-2 text-sm text-green-600 font-medium">
                                                    <i class="fas fa-check-circle"></i>
                                                    You rated: <?= $user_rating ?>/5
                                                </span>
                                            
                                            <!-- STATUS 2: CAN RATE - Show "Click to rate" prompt -->
                                            <?php elseif ($can_rate_order): ?>
                                                <span class="ml-2 text-sm text-gray-500">
                                                    Click to rate
                                                </span>
                                            
                                            <!-- STATUS 3: CANNOT RATE - Show locked message (order not delivered yet) -->
                                            <?php else: ?>
                                                <span class="ml-2 text-sm text-orange-600">
                                                    <i class="fas fa-lock"></i>
                                                    Locked until delivered
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- ============================================================================
                                             COMMENT TEXTAREA - Optional feedback from customer
                                             ============================================================================ -->
                                        <!-- Text area for user to leave a comment about the product -->
                                        <!-- Only shown if order can be rated (delivered/completed status) -->
                                        <?php if ($can_rate_order): ?>
                                            <div class="w-full">
                                                <!-- Comment input field with max 500 characters -->
                                                <textarea class="rating-comment w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                                                    placeholder="Share your thoughts about this product (optional - max 500 characters)"
                                                    maxlength="500"
                                                    rows="3"
                                                    data-product-id="<?= $product_id ?>"
                                                    <?= $user_rating > 0 ? 'disabled' : '' ?>></textarea>
                                                
                                                <!-- Character counter for comment -->
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <span class="char-count">0</span>/500 characters
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            
                            <!-- If product not found in catalog, show error message -->
                            <?php else: ?>
                                <!-- ============================================================================
                                     PRODUCT NOT FOUND WARNING - Shows if product_id is invalid
                                     ============================================================================ -->
                                <div class="bg-yellow-50 border border-yellow-200 rounded p-3 no-print">
                                    <div class="flex items-center text-yellow-700">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <span class="text-sm">Rating not available - Product not found in catalog</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ============================================================================
                 ORDER SUMMARY - Subtotal, VAT, Delivery, Total
                 ============================================================================ -->
            <div class="p-6 bg-gray-50 border-t">
                <div class="max-w-sm ml-auto space-y-3">
                    <!-- SUBTOTAL LINE -->
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-medium">₱<?= number_format($subtotal, 2) ?></span>
                    </div>
                    
                    <!-- VAT (12%) LINE -->
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">VAT (12%):</span>
                        <span class="font-medium">₱<?= number_format($vat_amount, 2) ?></span>
                    </div>
                    
                    <!-- DELIVERY FEE LINE -->
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Delivery Fee:</span>
                        <span class="font-medium">₱<?= number_format($delivery_fee, 2) ?></span>
                    </div>
                    
                    <!-- SEPARATOR -->
                    <hr class="border-gray-300">
                    
                    <!-- TOTAL LINE (Highlighted) -->
                    <div class="flex justify-between items-center text-lg font-bold">
                        <span>Total:</span>
                        <span class="text-green-700">₱<?= number_format($final_total, 2) ?></span>
                    </div>
                    
                    <!-- MISMATCH WARNING (if total doesn't match) -->
                    <?php if ($has_total_mismatch): ?>
                        <div class="text-xs text-gray-500 text-right">
                            (Calculated: ₱<?= number_format($total_with_vat_and_delivery, 2) ?>)
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================================================
                 PAYMENT METHOD SECTION
                 ============================================================================ -->
            <div class="p-6 border-t">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <span class="font-medium">Payment Method:</span>
                    <span class="text-gray-700"><?= htmlspecialchars($order['mode_payment']) ?></span>
                </div>
            </div>

            <!-- ============================================================================
                 IMPORTANT NOTES SECTION
                 ============================================================================ -->
            <div class="p-6 bg-yellow-50 border-t border-yellow-200">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-yellow-800 mb-2">Important Notes:</h4>
                        <ul class="text-sm text-yellow-700 space-y-1">
                            <li>• We will review your order and contact you within 24 hours</li>
                            <li>• Final total includes 12% VAT and delivery fees</li>
                            <li>• Please keep this receipt for your records</li>
                            <li>• Product ratings will be enabled once your order is delivered</li>
                            <li>• You can rate each product only once - choose your rating carefully</li>
                            <li>• Your ratings help us improve our products and services</li>
                            <li>• For questions, contact us with reference: <strong><?= htmlspecialchars($order['reference_no']) ?></strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================================================
                 RECEIPT FOOTER
                 ============================================================================ -->
            <div class="p-6 text-center text-gray-500 text-sm border-t">
                <p>Thank you for choosing our service!</p>
                <p class="mt-1">Generated on <?= date('F j, Y g:i A') ?></p>
            </div>
        </div>

        <!-- ============================================================================
             ACTION BUTTONS - Export, Print, Back
             ============================================================================ -->
        <div class="mt-6 flex gap-4 justify-center no-print">
            <a href="checkout-export_receipt_excel-page-12-A-1.php?order_id=<?= $order_id ?>" 
               class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                <i class="fas fa-file-excel"></i>
                Export to Excel
            </a>

            <a href="order_history" 
               class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Back to Orders
            </a>
        </div>
        
    </div>

    <!-- ============================================================================
         NOTIFICATION ELEMENT - Toast messages for success/error/info
         ============================================================================ -->
    <div id="notification" class="fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full opacity-0 z-50">
        <div class="flex items-center gap-2">
            <span id="notification-icon"></span>
            <span id="notification-message"></span>
        </div>
    </div>

    <!-- ============================================================================
         JAVASCRIPT - Rating system and notification handling
         ============================================================================ -->
    <script>
        // ============================================================================
        // RATING SYSTEM - Initialize star rating for each product
        // ============================================================================
        // Loop through all rate-stars containers on the page
        document.querySelectorAll('.rate-stars').forEach(function(starsContainer) {
            // ============================================================================
            // EXTRACT DATA ATTRIBUTES
            // ============================================================================
            const productId = parseInt(starsContainer.dataset.productId);
            const canRate = starsContainer.dataset.canRate === '1';
            const alreadyRated = starsContainer.dataset.alreadyRated === '1'; // NEW: Check if already rated
            const currentRating = parseInt(starsContainer.dataset.currentRating) || 0;
            const stars = starsContainer.querySelectorAll('.star-button');

            // ============================================================================
            // VALIDATION - Skip if product invalid or cannot rate
            // ============================================================================
            if (!productId || productId <= 0 || !canRate) return;

            // ============================================================================
            // STAR CLICK EVENT HANDLER - User clicks on star to submit rating
            // ============================================================================
            stars.forEach(function(star) {
                star.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // ============================================================================
                    // PREVENT RATING IF ALREADY RATED - One rating per product
                    // ============================================================================
                    if (alreadyRated) {
                        showNotification('You have already rated this product. One rating per product is allowed.', 'error');
                        return;
                    }

                    const rating = parseInt(star.dataset.rating);
                    
                    // ============================================================================
                    // GET COMMENT FROM TEXTAREA - If user provided feedback
                    // ============================================================================
                    // Find the comment textarea for this product
                    const commentTextarea = starsContainer.parentElement.querySelector('.rating-comment');
                    const comment = commentTextarea ? commentTextarea.value.trim() : '';

                    // Show loading state notification
                    showNotification('Submitting your rating...', 'info');

                    // ============================================================================
                    // SEND RATING & COMMENT TO SERVER VIA AJAX
                    // ============================================================================
                    fetch('../rate/rate_product.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                rating: rating,
                                comment: comment  // Include comment in request
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // ============================================================================
                                // RATING SUCCESSFUL - Update UI and disable further ratings
                                // ============================================================================
                                // Update stars display to show selected rating
                                updateStarsDisplay(stars, rating);
                                starsContainer.dataset.currentRating = rating;
                                starsContainer.dataset.alreadyRated = '1'; // Mark as rated

                                // Disable all star buttons to prevent re-rating
                                stars.forEach(function(btn) {
                                    btn.disabled = true;
                                });

                                // ============================================================================
                                // DISABLE COMMENT TEXTAREA - Cannot edit after rating
                                // ============================================================================
                                // Disable the comment textarea after successful rating
                                if (commentTextarea) {
                                    commentTextarea.disabled = true;
                                    commentTextarea.classList.add('bg-gray-100', 'opacity-60');
                                }

                                // Update rating text
                                const ratingText = starsContainer.querySelector('span');
                                if (ratingText) {
                                    ratingText.innerHTML = '<i class="fas fa-check-circle"></i> You rated: ' + rating + '/5';
                                    ratingText.className = 'ml-2 text-sm text-green-600 font-medium';
                                }

                                showNotification('Rating submitted successfully! Thank you for your feedback.', 'success');
                            } else {
                                showNotification(data.message || 'Failed to submit rating', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Rating error:', error);
                            showNotification('Failed to submit rating. Please try again.', 'error');
                        });
                });

                // ============================================================================
                // HOVER EFFECTS - Show star preview on hover (only if not rated)
                // ============================================================================
                star.addEventListener('mouseenter', function() {
                    // Skip hover effect if already rated
                    if (alreadyRated) return;
                    
                    const rating = parseInt(star.dataset.rating);
                    highlightStars(stars, rating);
                });
            });

            // ============================================================================
            // RESET HOVER - Restore original rating on mouse leave
            // ============================================================================
            starsContainer.addEventListener('mouseleave', function() {
                // Skip if already rated
                if (alreadyRated) return;
                
                const current = parseInt(starsContainer.dataset.currentRating) || 0;
                updateStarsDisplay(stars, current);
            });
        });

        // ============================================================================
        // CHARACTER COUNTER FOR COMMENT TEXTAREA - Update character count display
        // ============================================================================
        // Loop through all comment textareas and add character counter
        document.querySelectorAll('.rating-comment').forEach(function(textarea) {
            // ============================================================================
            // UPDATE CHARACTER COUNT ON INPUT
            // ============================================================================
            // Get the character counter span for this textarea
            const charCount = textarea.parentElement.querySelector('.char-count');
            
            // Update character count on every keystroke
            textarea.addEventListener('input', function() {
                // Update the display with current character count
                charCount.textContent = this.value.length;
            });
        });

        // ============================================================================
        // HELPER FUNCTION: highlightStars - Show star preview during hover
        // ============================================================================
        function highlightStars(stars, rating) {
            stars.forEach(function(star, index) {
                const starRating = index + 1;
                const icon = star.querySelector('i');

                // Fill stars up to hovered rating
                if (starRating <= rating) {
                    icon.className = 'fas fa-star text-yellow-400 text-2xl';
                } else {
                    icon.className = 'far fa-star text-gray-400 hover:text-yellow-400 text-2xl';
                }
            });
        }

        // ============================================================================
        // HELPER FUNCTION: updateStarsDisplay - Update visual state of stars
        // ============================================================================
        function updateStarsDisplay(stars, rating) {
            stars.forEach(function(star, index) {
                const starRating = index + 1;
                const icon = star.querySelector('i');

                // Show filled or empty stars based on rating
                if (starRating <= rating) {
                    icon.className = 'fas fa-star text-yellow-400 text-2xl';
                } else {
                    icon.className = 'far fa-star text-gray-400 text-2xl';
                }
            });
        }

        // ============================================================================
        // HELPER FUNCTION: showNotification - Display toast notification
        // ============================================================================
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            const messageEl = document.getElementById('notification-message');
            const iconEl = document.getElementById('notification-icon');

            // Set notification message text
            messageEl.textContent = message;

            // ============================================================================
            // SET NOTIFICATION STYLING BASED ON TYPE
            // ============================================================================
            let bgColor, textColor, icon;
            switch (type) {
                case 'success':
                    bgColor = 'bg-green-500';
                    textColor = 'text-white';
                    icon = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    bgColor = 'bg-red-500';
                    textColor = 'text-white';
                    icon = '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'info':
                    bgColor = 'bg-blue-500';
                    textColor = 'text-white';
                    icon = '<i class="fas fa-info-circle"></i>';
                    break;
                default:
                    bgColor = 'bg-gray-500';
                    textColor = 'text-white';
                    icon = '<i class="fas fa-bell"></i>';
            }

            // Apply styling classes
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50 ${bgColor} ${textColor}`;
            iconEl.innerHTML = icon;

            // Show notification (slide in from right)
            notification.classList.remove('translate-x-full', 'opacity-0');
            notification.classList.add('translate-x-0', 'opacity-100');

            // Hide notification after 3 seconds (slide out to right)
            setTimeout(() => {
                notification.classList.add('translate-x-full', 'opacity-0');
                notification.classList.remove('translate-x-0', 'opacity-100');
            }, 3000);
        }
    </script>
</body>

</html>