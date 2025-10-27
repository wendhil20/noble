<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

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

// Query products by category
$query = "
    SELECT 
        p.*, 
        v.origin,
        v.discount,
        v.percent,
        v.status,
        v.price
    FROM products p
    LEFT JOIN product_variants v ON v.product_id = p.id
    WHERE p.codename = ?
    GROUP BY p.id
    ORDER BY p.id DESC
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
    $base = (float)($row['price'] ?? 0);
    $percent = (float)($row['percent'] ?? 0);
    $discount = (float)($row['discount'] ?? 0);
    $priceWithMarkup = $base + ($base * $percent / 100);
    $finalPrice = $priceWithMarkup - ($priceWithMarkup * $discount / 100);

    // Get ratings
    $product_id = (int)$row['id'];
    $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
    $rating_q->bind_param("i", $product_id);
    $rating_q->execute();
    $rating_result = $rating_q->get_result()->fetch_assoc();
    $avg_rating = $rating_result['avg_rating'] ?? 0;
    $total_raters = $rating_result['total_raters'] ?? 0;
    $rating_q->close();
    
    $full = floor($avg_rating);
    $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
    $empty = 5 - $full - $half;
    
    // Get description
    $description = $row['description'] ?? '';
?>

<div class="mb-3 border rounded-lg overflow-hidden hover:shadow-md transition-all duration-300">
    <div class="relative">
        <!-- Product Image -->
        <div class="relative h-32">
            <?php if (!empty($row['main_image'])): ?>
                <img src="../../<?= htmlspecialchars($row['main_image']) ?>" 
                     alt="<?= htmlspecialchars($row['product_name']) ?>"
                     class="w-full h-full object-contain p-1">
            <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-gray-300">
                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                    </svg>
                </div>
            <?php endif; ?>
            
            <!-- Discount Badge -->
            <?php if ($discount > 0): ?>
                <div class="absolute top-1 right-1 bg-red-500 text-white px-1.5 py-0.5 rounded text-xs font-bold">
                    -<?= number_format($discount, 0) ?>%
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Info -->
        <div class="p-2">
            <!-- Product Name -->
            <h3 class="text-sm font-medium text-gray-800 mb-1 line-clamp-1 hover:text-orange-600 transition">
                <?= htmlspecialchars($row['product_name']) ?>
            </h3>
            
            <!-- Description -->
            <?php if (!empty($description)): ?>
                <p class="text-xs text-gray-600 mb-2 line-clamp-2">
                    <?= htmlspecialchars($description) ?>
                </p>
            <?php endif; ?>
            
            <!-- Rating -->
            <div class="flex items-center mb-1">
                <?php if ($total_raters > 0): ?>
                    <div class="flex text-yellow-400 text-xs mr-1">
                        <?php
                        for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                        if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                        for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                        ?>
                    </div>
                    <span class="text-xs text-gray-500"><?= $avg_rating ?></span>
                <?php else: ?>
                    <div class="flex text-gray-300 text-xs">
                        <?php for ($i = 0; $i < 5; $i++) echo '<i class="far fa-star"></i>'; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Price -->
            <div class="mb-2">
                <?php if ($discount > 0): ?>
                    <div class="flex items-center space-x-1">
                        <span class="text-sm font-bold text-orange-600">₱<?= number_format($finalPrice, 2) ?></span>
                        <span class="text-xs text-gray-400 line-through">₱<?= number_format($priceWithMarkup, 2) ?></span>
                    </div>
                <?php else: ?>
                    <span class="text-sm font-bold text-gray-800">₱<?= number_format($finalPrice, 2) ?></span>
                <?php endif; ?>
            </div>
            
            <!-- View Button -->
            <form action="index-product_view-page-4-AA" method="GET" class="w-full">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="w-full bg-black text-white py-1.5 px-2 rounded text-xs hover:bg-gray-800 transition-colors duration-300 flex items-center justify-center space-x-1">
                      <i class="fa-solid fa-bag-shopping"></i>
                    <span>View</span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php endwhile; ?>

<?php $stmt->close(); ?>