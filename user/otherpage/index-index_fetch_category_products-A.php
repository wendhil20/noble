<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Include the handler for smart price functions
include 'index-recent_views_handler-page-14.php';

// Session restoration from remember_token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $_COOKIE['remember_token']);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION = array_merge($_SESSION, [
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email'] ?? '',
            'user_mobile' => $user['mobile'] ?? ''
        ]);

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

// Get and sanitize category
$category = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : '';

// Validate category
$allowed_categories = ['doors', 'aircon', 'bathroomfixtures', 'tiles'];
if (!in_array($category, $allowed_categories)) {
    echo '<div class="text-center text-gray-500 p-4">Invalid category</div>';
    exit;
}

// 🔥 UPDATED QUERY - WITH SMART PRICE RANGE
$query = "
    SELECT 
        p.*, 
        p.view_count,
        p.unique_view_count,
        p.price as base_price,
        
        -- 🔥 SEPARATE SIZE & COLOR PRICES
        COALESCE(MIN(pv.price), 0) as min_size_price,
        COALESCE(MAX(pv.price), 0) as max_size_price,
        COALESCE(MIN(pc.price), 0) as min_color_price,
        COALESCE(MAX(pc.price), 0) as max_color_price,
        
        -- Base price (fallback)
        COALESCE(
            NULLIF(MIN(pv.price), 0),
            NULLIF(MIN(pc.price), 0),
            p.price
        ) as price,
        
        -- Markup & Discount
        COALESCE(MIN(pv.percent), 0) as percent,
        COALESCE(MAX(pv.discount), 0) as discount,
        
        -- Variant details
        v.origin,
        v.status,
        
        -- Sold count
        COALESCE(SUM(si.quantity), 0) AS total_sold,
        
        -- Rating
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS rating_count
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN product_colors pc ON p.id = pc.product_id
    LEFT JOIN sold_items si ON si.product_id = p.id
    LEFT JOIN product_ratings r ON r.product_id = p.id
    WHERE p.codename = ?
    GROUP BY p.id
    ORDER BY p.view_count DESC, p.id DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $category);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="text-center text-gray-500 p-8">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2-2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <p class="text-lg font-medium">No products found</p>
            <p class="text-sm">Check back later for new items</p>
          </div>';
    exit;
}

// Display products
while ($row = mysqli_fetch_assoc($result)):
    // 🔥 USE SMART PRICE DISPLAY
    $priceData = calculateSmartPriceDisplay($row);
    
    // Get discount info
    $discount = (float)($row['discount'] ?? 0);
    $product_id = (int)$row['id'];
    
    // Get ratings
    $avg_rating = (float)($row['avg_rating'] ?? 0);
    $total_raters = (int)($row['rating_count'] ?? 0);
    
    $full = floor($avg_rating);
    $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
    $empty = 5 - $full - $half;
    
    // Get view count & sold count
    $view_count = (int)($row['view_count'] ?? 0);
    $total_sold = (int)($row['total_sold'] ?? 0);
    
    // Get description
    $description = $row['description'] ?? '';
?>

<div class="mb-3 border rounded-lg overflow-hidden hover:shadow-md transition-all duration-300 bg-white">
    <a href="index-product_view-page-4-AA?id=<?= $product_id ?>" class="block">
        <!-- Product Image -->
        <div class="relative h-32 bg-gray-50">
            <?php if (!empty($row['main_image'])): ?>
                <img src="../../<?= htmlspecialchars($row['main_image']) ?>" 
                     alt="<?= htmlspecialchars($row['product_name']) ?>"
                     class="w-full h-full object-contain p-2 hover:scale-105 transition-transform duration-300">
            <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-gray-300">
                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                    </svg>
                </div>
            <?php endif; ?>
            
            <!-- Discount Badge -->
            <?php if ($discount > 0): ?>
                <div class="absolute top-2 right-2 bg-red-500 text-white px-2 py-0.5 rounded text-xs font-bold shadow-md">
                    -<?= number_format($discount, 0) ?>%
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Info -->
        <div class="p-3">
            <!-- Product Name -->
            <h3 class="text-sm font-medium text-gray-800 mb-1 line-clamp-2 hover:text-orange-600 transition-colors min-h-[40px]">
                <?= htmlspecialchars($row['product_name']) ?>
            </h3>
            
            <!-- Description -->
            <?php if (!empty($description)): ?>
                <p class="text-xs text-gray-500 mb-2 line-clamp-2">
                    <?= htmlspecialchars($description) ?>
                </p>
            <?php endif; ?>
            
            <!-- Rating -->
            <div class="flex items-center justify-between mb-2">
                <?php if ($total_raters > 0): ?>
                    <div class="flex items-center space-x-1">
                        <div class="flex text-yellow-400 text-xs">
                            <?php
                            for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                            if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                            for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                            ?>
                        </div>
                        <span class="text-xs text-gray-500 font-medium"><?= $avg_rating ?></span>
                    </div>
                    <span class="text-xs text-gray-400">(<?= $total_raters ?>)</span>
                <?php else: ?>
                    <div class="flex items-center space-x-1">
                        <div class="flex text-gray-300 text-xs">
                            <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                        </div>
                        <span class="text-xs text-gray-400">No rating</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 🔥 SMART PRICE DISPLAY -->
            <div class="mb-3">
                <?php if ($discount > 0): ?>
                    <div class="flex items-center gap-1 flex-wrap">
                        <span class="text-sm font-bold text-orange-600"><?= $priceData['display_price'] ?></span>
                        <span class="text-xs font-semibold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">
                            -<?= number_format($discount, 0) ?>%
                        </span>
                    </div>
                <?php else: ?>
                    <span class="text-sm font-bold text-gray-800"><?= $priceData['display_price'] ?></span>
                <?php endif; ?>
            </div>
            
            <!-- 🔥 ACTION BAR - View Button + Stats (Side by Side) -->
            <div class="flex items-center justify-between gap-2 border-t border-gray-100 pt-2 mt-2">
                <!-- View Button -->
                <form action="index-product_view-page-4-AA" method="GET" class="flex-shrink-0" onclick="event.stopPropagation()">
                    <input type="hidden" name="id" value="<?= $product_id ?>">
                    <button type="submit" 
                        class="flex items-center gap-1.5 bg-black text-white py-1.5 px-3 rounded text-xs hover:bg-gray-800 transition-colors duration-300">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <span>View</span>
                    </button>
                </form>
                
                <!-- Stats Container -->
                <div class="flex items-center gap-1.5 text-[10px]">
                    <!-- Viewing Count -->
                    <?php if ($view_count > 0): ?>
                        <div class="flex items-center gap-1 bg-blue-50 text-blue-600 px-2 py-1 rounded">
                           viewing
                            <span class="font-medium"><?= formatViewCount($view_count) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Sold Count -->
                    <?php if ($total_sold > 0): ?>
                        <div class="flex items-center gap-1 bg-green-50 text-green-600 px-2 py-1 rounded">
                          sold
                            <span class="font-medium"><?= number_format($total_sold) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </a>
</div>

<?php endwhile; ?>

<?php $stmt->close(); ?>