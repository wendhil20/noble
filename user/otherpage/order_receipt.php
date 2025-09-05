<?php
// order_receipt.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

// Validate order_id and ensure it belongs to the current user
if (!$order_id || !is_numeric($order_id)) {
    header('Location: dashboard.php');
    exit;
}

// Fetch order details
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header('Location: dashboard.php');
    exit;
}

$order = $order_result->fetch_assoc();
$stmt->close();

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

// Function to get user's rating for a specific product
function getUserRating($conn, $user_id, $product_id)
{
    if (!$product_id) return 0;

    $stmt = $conn->prepare("
        SELECT rating FROM product_ratings 
        WHERE user_id = ? AND product_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rating = $result->num_rows > 0 ? $result->fetch_assoc()['rating'] : 0;
    $stmt->close();
    return $rating;
}

// Function to get average rating for a product
function getAverageRating($conn, $product_id)
{
    if (!$product_id) return ['avg_rating' => 0, 'total_ratings' => 0];

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
    return [
        'avg_rating' => round($data['avg_rating'] ?? 0, 1),
        'total_ratings' => $data['total_ratings'] ?? 0
    ];
}

// Calculate totals
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['subtotal'];
}
$vat_rate = 0.12; // 12% VAT
$vat_amount = $subtotal * $vat_rate;
$delivery_fee = $order['delivery_fee'] ?? 0;
$total_with_vat_and_delivery = $subtotal + $vat_amount + $delivery_fee;

// Format date
$order_date = date('F j, Y g:i A', strtotime($order['created_at']));

// Category display names
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
    'aacblock' => 'AAC BLOCKS',
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt - <?= htmlspecialchars($order['reference_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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

        .star-button {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .star-button:hover {
            transform: scale(1.1);
        }

        .rating-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <?php include '../navbar/top.php'; ?>

    <!-- Enhanced Breadcrumb -->
    <nav class="bg-white border-b border-gray-200 px-4 py-3">
        <div class="">
            <div class="flex items-center space-x-2 text-sm">
                <a href="index" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
                    <i class="fas fa-home mr-1"></i>Home
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <a href="order_history" class="text-orange-500 hover:text-orange-700 transition duration-200 flex items-center">
                    Recent History
                </a>
                <i class="fas fa-chevron-right text-gray-400"></i>
                <span class="text-gray-600 font-medium">Receipt</span>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4">
        <!-- Receipt Container -->
        <div class="bg-white shadow-lg print-shadow rounded-lg overflow-hidden mt-4" id="receipt">
            <!-- Receipt Header -->
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

            <!-- Customer & Delivery Info -->
            <div class="p-6 grid md:grid-cols-2 gap-8 border-b">
                <!-- Customer Info -->
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

                <!-- Delivery Info -->
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

            <!-- Order Items with Rating -->
            <div class="p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Order Details
                </h3>
 <div class="max-h-96 overflow-y-auto overflow-x-hidden">
                <div class="space-y-6 ">
                    <?php foreach ($order_items as $index => $item):
                        $product_id = $item['product_id'] ?? 0;
                        $category = $item['product_category'] ?? 'general';
                        $user_rating = getUserRating($conn, $user_id, $product_id);
                        $rating_data = getAverageRating($conn, $product_id);
                        $avg_rating = $rating_data['avg_rating'];
                        $total_ratings = $rating_data['total_ratings'];
                        $category_display = $category_names[$category] ?? ucfirst($category);
                    ?>
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <!-- Product Details -->
                            <div class="grid md:grid-cols-5 gap-4 items-start mb-4">
                                <div class="md:col-span-2">
                                    <div class="font-medium text-gray-900 text-lg"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <?php if (!empty($item['codename'])): ?>
                                        <div class="text-xs text-gray-500 mb-1">Code: <?= htmlspecialchars($item['codename']) ?></div>
                                    <?php endif; ?>
                                    <div class="inline-block px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                        <?= $category_display ?>
                                    </div>
                                    <?php if ($product_id): ?>
                                        <div class="text-xs text-gray-400 mt-1">Product ID: <?= $product_id ?></div>
                                    <?php else: ?>
                                        <div class="text-xs text-red-400 mt-1">⚠️ Product not found in catalog</div>
                                    <?php endif; ?>
                                </div>

                                <div class="text-gray-600 text-sm">
                                    <div class="space-y-1">
                                        <?php if (!empty($item['type_name'])): ?>
                                            <div><span class="font-medium">Type:</span> <?= htmlspecialchars($item['type_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['variant_color'])): ?>
                                            <div><span class="font-medium">Color:</span> <?= htmlspecialchars($item['variant_color']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['size']) && trim($item['size']) !== ''): ?>
                                            <div><span class="font-medium">Size:</span> <?= htmlspecialchars($item['size']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['origin'])): ?>
                                            <?php
                                            $is_local = stripos($item['origin'], 'local') !== false;
                                            $origin_class = $is_local ? 'text-blue-600' : 'text-red-600';
                                            ?>
                                            <div class="<?= $origin_class ?> font-medium">
                                                <span class="text-gray-600">Origin:</span> <?= htmlspecialchars($item['origin']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <div class="text-sm text-gray-600">Quantity</div>
                                    <div class="text-xl font-bold"><?= $item['quantity'] ?></div>
                                </div>

                                <div class="text-right">
                                    <div class="text-sm text-gray-600">Unit Price</div>
                                    <div class="text-lg">₱<?= number_format($item['price'], 2) ?></div>
                                    <hr class="my-1">
                                    <div class="text-xl font-bold text-green-700">₱<?= number_format($item['subtotal'], 2) ?></div>
                                </div>
                            </div>

                            <?php if ($product_id > 0): ?>
                                <?php
                                // Check if order is completed/delivered to allow ratings
                                $order_status = strtolower($order['status'] ?? 'pending');
                                $can_rate = in_array($order_status, ['delivered', 'completed', 'received']);
                                ?>

                                <div class="rating-section p-4 rounded-lg no-print">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                                                <i class="fas fa-star text-yellow-500"></i>
                                                Rate this <?= $category_display ?> item
                                            </h4>
                                            <?php if ($can_rate): ?>
                                                <p class="text-sm text-gray-600">Help others by sharing your experience</p>
                                            <?php else: ?>
                                                <p class="text-sm text-orange-600">Rating will be available once your order is delivered</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right text-sm text-gray-600">
                                            <div>Average: <?= $avg_rating ?>/5</div>
                                            <div><?= $total_ratings ?> ratings</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <?php if ($can_rate): ?>
                                            <!-- Interactive Rating Stars - Only if order is completed -->
                                            <div class="rate-stars flex items-center gap-1"
                                                data-product-id="<?= $product_id ?>"
                                                data-category="<?= htmlspecialchars($category) ?>"
                                                data-current-rating="<?= $user_rating ?>">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <button type="button" class="star-button" data-rating="<?= $i ?>">
                                                        <?php if ($i <= $user_rating): ?>
                                                            <i class="fas fa-star text-yellow-400 text-xl"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star text-gray-400 hover:text-yellow-400 text-xl"></i>
                                                        <?php endif; ?>
                                                    </button>
                                                <?php endfor; ?>

                                                <?php if ($user_rating > 0): ?>
                                                    <span class="ml-2 text-sm text-green-600 font-medium">
                                                        You rated: <?= $user_rating ?>/5
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ml-2 text-sm text-gray-500">
                                                        Click to rate
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <!-- Disabled Stars - Show current rating but no interaction -->
                                            <div class="flex items-center gap-1 opacity-50">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <div>
                                                        <?php if ($i <= $user_rating): ?>
                                                            <i class="fas fa-star text-yellow-400 text-xl"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star text-gray-400 text-xl"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endfor; ?>

                                                <?php if ($user_rating > 0): ?>
                                                    <span class="ml-2 text-sm text-gray-600">
                                                        You rated: <?= $user_rating ?>/5 (Order Status: <?= ucfirst($order_status) ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ml-2 text-sm text-gray-600">
                                                        Rating available when delivered (Current: <?= ucfirst($order_status) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Status Notice -->
                                            <div class="ml-4 px-3 py-2 bg-orange-50 border border-orange-200 rounded-lg">
                                                <div class="flex items-center text-orange-700 text-sm">
                                                    <i class="fas fa-clock mr-2"></i>
                                                    <span>Complete your order to unlock product rating</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded p-3 no-print">
                                    <div class="flex items-center text-yellow-700">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <span class="text-sm">Rating not available </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Additional Details -->
                            <?php if (!empty($item['descrip6']) || !empty($item['descrip7'])): ?>
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    <div class="text-xs text-gray-500 italic">
                                        <?php if (!empty($item['descrip6'])): ?>
                                            <div><?= htmlspecialchars($item['descrip6']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['descrip7'])): ?>
                                            <div><?= htmlspecialchars($item['descrip7']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
  </div>

            <!-- Order Summary -->
            <div class="p-6 bg-gray-50 border-t">
                <div class="max-w-sm ml-auto space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-medium">₱<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">VAT (12%):</span>
                        <span class="font-medium">₱<?= number_format($vat_amount, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Delivery Fee:</span>
                        <span class="font-medium">₱<?= number_format($delivery_fee, 2) ?></span>
                    </div>
                    <hr class="border-gray-300">
                    <div class="flex justify-between items-center text-lg font-bold">
                        <span>Total:</span>
                        <span class="text-green-700">₱<?= number_format($total_with_vat_and_delivery, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="p-6 border-t">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <span class="font-medium">Payment Method:</span>
                    <span class="text-gray-700"><?= htmlspecialchars($order['mode_payment']) ?></span>
                </div>
            </div>

            <!-- Important Notes -->
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
                            <li>• Your ratings help us improve our products and services</li>
                            <li>• For questions, please contact us with your reference number: <strong><?= htmlspecialchars($order['reference_no']) ?></strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 text-center text-gray-500 text-sm border-t">
                <p>Thank you for choosing our service!</p>
                <p class="mt-1">Generated on <?= date('F j, Y g:i A') ?></p>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="notification" class="fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full opacity-0 z-50">
        <div class="flex items-center gap-2">
            <span id="notification-icon"></span>
            <span id="notification-message"></span>
        </div>
    </div>

    <script>
        // Rating System JavaScript
        document.querySelectorAll('.rate-stars').forEach(function(starsContainer) {
            const productId = parseInt(starsContainer.dataset.productId);
            const category = starsContainer.dataset.category;
            const currentRating = parseInt(starsContainer.dataset.currentRating) || 0;
            const stars = starsContainer.querySelectorAll('.star-button');

            // Skip if no valid product ID
            if (!productId) return;

            stars.forEach(function(star, index) {
                star.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rating = parseInt(star.dataset.rating);

                    // Show loading state
                    showNotification('Submitting your rating...', 'info');

                    // Send rating to server
                    fetch('../rate/rate_product.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                rating: rating
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update stars display
                                updateStarsDisplay(stars, rating);
                                starsContainer.dataset.currentRating = rating;

                                // Update rating text
                                const ratingText = starsContainer.querySelector('span');
                                if (ratingText) {
                                    ratingText.textContent = `You rated: ${rating}/5`;
                                    ratingText.className = 'ml-2 text-sm text-green-600 font-medium';
                                }

                                showNotification('Rating submitted successfully!', 'success');
                            } else {
                                showNotification(data.message || 'Failed to submit rating', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Rating error:', error);
                            showNotification('Failed to submit rating. Please try again.', 'error');
                        });
                });

                // Hover effects
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(star.dataset.rating);
                    highlightStars(stars, rating);
                });
            });

            // Reset on mouse leave
            starsContainer.addEventListener('mouseleave', function() {
                const current = parseInt(starsContainer.dataset.currentRating) || 0;
                updateStarsDisplay(stars, current);
            });
        });

        function highlightStars(stars, rating) {
            stars.forEach(function(star, index) {
                const starRating = index + 1;
                const icon = star.querySelector('i');

                if (starRating <= rating) {
                    icon.className = 'fas fa-star text-yellow-400 text-xl';
                } else {
                    icon.className = 'far fa-star text-gray-400 hover:text-yellow-400 text-xl';
                }
            });
        }

        function updateStarsDisplay(stars, rating) {
            stars.forEach(function(star, index) {
                const starRating = index + 1;
                const icon = star.querySelector('i');

                if (starRating <= rating) {
                    icon.className = 'fas fa-star text-yellow-400 text-xl';
                } else {
                    icon.className = 'far fa-star text-gray-400 hover:text-yellow-400 text-xl';
                }
            });
        }

        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            const messageEl = document.getElementById('notification-message');
            const iconEl = document.getElementById('notification-icon');

            // Set message
            messageEl.textContent = message;

            // Set icon and colors based on type
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

            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50 ${bgColor} ${textColor}`;
            iconEl.innerHTML = icon;

            // Show notification
            notification.classList.remove('translate-x-full', 'opacity-0');
            notification.classList.add('translate-x-0', 'opacity-100');

            // Hide after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full', 'opacity-0');
                notification.classList.remove('translate-x-0', 'opacity-100');
            }, 3000);
        }

        function downloadPDF() {
            window.print();
        }
    </script>
</body>

</html>