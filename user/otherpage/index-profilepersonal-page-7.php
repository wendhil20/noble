<?php
// index-profilepersonal-page-7.php

include ROOT_PATH . '/connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/googlecallback');
    exit;
}

// ✅ Restore session from remember_token (normal account or Google)
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
        $_SESSION['user_email'] = $user['email'];

        // Check if the account is Google-based (optional flag or logic)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
    header('Location: ../googlecallback.php');
    exit;
}

// ✅ Retrieve user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? null;
$user_picture = $_SESSION['user_picture'] ?? null;

// Pagination settings
$orders_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $orders_per_page;

// Count total orders
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $orders_per_page);
$count_stmt->close();

// Count qualifying orders (₱20,000+, Completed status only)
$tier_stmt = $conn->prepare("
    SELECT COUNT(*) as qualifying_orders 
    FROM orders 
    WHERE user_id = ? 
    AND total >= 20000 
    AND status = 'Completed'
");
$tier_stmt->bind_param("i", $user_id);
$tier_stmt->execute();
$tier_result = $tier_stmt->get_result();
$qualifying_orders = $tier_result->fetch_assoc()['qualifying_orders'];
$tier_stmt->close();

// Calculate tier information
function calculateUserTier($qualifying_orders, $conn)
{
    $tier_info = [
        'tier_name' => 'Member',
        'discount_percent' => 0,
        'progress' => ($qualifying_orders / 25) * 100,
        'next_target' => 'Silver',
        'orders_needed' => max(0, 25 - $qualifying_orders),
        'benefits' => ['Standard Support', 'Regular Promotions'],
        'card_image' => null
    ];

    if ($qualifying_orders >= 75) {
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'platinum' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_name' => 'Platinum',
                'discount_percent' => $card['card_discount'],
                'progress' => 100,
                'next_target' => null,
                'orders_needed' => 0,
                'benefits' => ['Priority Support', 'Maximum Discount', 'Exclusive Access', 'Premium Benefits'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    } elseif ($qualifying_orders >= 50) {
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'gold' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_name' => 'Gold',
                'discount_percent' => $card['card_discount'],
                'progress' => ($qualifying_orders / 75) * 100,
                'next_target' => 'Platinum',
                'orders_needed' => 75 - $qualifying_orders,
                'benefits' => ['Enhanced Support', 'Special Discounts', 'Priority Access', 'Exclusive Offers'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    } elseif ($qualifying_orders >= 25) {
        $stmt = $conn->prepare("SELECT * FROM tiercard WHERE LOWER(card_name) = 'silver' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($card = $result->fetch_assoc()) {
            $tier_info = array_merge($tier_info, [
                'tier_name' => 'Silver',
                'discount_percent' => $card['card_discount'],
                'progress' => ($qualifying_orders / 50) * 100,
                'next_target' => 'Gold',
                'orders_needed' => 50 - $qualifying_orders,
                'benefits' => ['Priority Support', 'Member Discounts', 'Special Offers'],
                'card_image' => $card['card_image']
            ]);
        }
        $stmt->close();
    }

    return $tier_info;
}

$user_tier = calculateUserTier($qualifying_orders, $conn);

// Fetch orders with pagination
$stmt = $conn->prepare("
    SELECT 
        id, reference_no, customer_name, email, mobile, address,
        mode_payment, total, status, created_at
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $orders_per_page, $offset);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

function getStatusBadge($status)
{
    $status = strtolower($status ?? 'pending');
    $badges = [
        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'processing' => 'bg-blue-100 text-blue-800 border-blue-300',
        'confirmed' => 'bg-green-100 text-green-800 border-green-300',
        'shipped' => 'bg-purple-100 text-purple-800 border-purple-300',
        'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'cancelled' => 'bg-red-100 text-red-800 border-red-300'
    ];

    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
    return "<span class='px-3 py-1 rounded-lg text-sm font-medium border {$class}'>" . ucfirst($status) . "</span>";
}


// Get user's billing addresses
$billing_addresses = [];
if ($user_id) {
    $stmt = $conn->prepare("
        SELECT * FROM billing_addresses 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $billing_addresses[] = $row;
    }
    $stmt->close();
}


$query = mysqli_query($conn, "SELECT is_verified FROM user_details WHERE user_id = '$user_id'");
$row = mysqli_fetch_assoc($query);

$is_verified = null; // default value
if ($row && isset($row['is_verified'])) {
    $is_verified = $row['is_verified'];
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT is_verified FROM user_details WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$user = $result->fetch_assoc();
$stmt->close();

// Safe access
$is_verified = $user['is_verified'] ?? null;


// Get recently viewed products for this user
$recent_products = getRecentlyViewedByUser($conn, $user_id, 10);
$recent_count = $recent_products->num_rows;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <title>Order History - Noble Admin</title>
    <style>
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            transition: width 1s ease-out;
        }

        .order-row:hover {
            background-color: #f8fafc;
        }

        .tier-qualifying {
            border-left: 4px solid #059669;
        }

        .high-value {
            border-left: 4px solid #d97706;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen " style="font-family: 'Montserrat', sans-serif; color: #2f1200">
   

    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="professional-card rounded-xl p-4 sm:p-6 lg:p-8 mb-4 sm:mb-6 lg:mb-8 animate-fade-in">
            <div class="flex flex-col lg:flex-row gap-4 sm:gap-6 lg:gap-8">

                <!-- Profile Information -->
                <div class="flex-1">
                    <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6">
                        <!-- Professional Avatar -->
                        <div class="relative mx-auto sm:mx-0">
                            <?php if ($user_picture): ?>
                                <div class="w-20 h-20 sm:w-24 sm:h-24 lg:w-28 lg:h-28 rounded-xl overflow-hidden border-2 border-gray-200 shadow-md">
                                    <img src="<?= htmlspecialchars($user_picture); ?>" alt="Profile Picture" class="w-full h-full object-cover">
                                </div>
                            <?php else: ?>
                                <div class="w-20 h-20 sm:w-24 sm:h-24 lg:w-28 lg:h-28 bg-blue-500 rounded-xl flex items-center justify-center text-white text-xl sm:text-2xl lg:text-3xl font-bold shadow-md">
                                    <?= strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($is_verified) && $is_verified == 1): ?>
                                <div class="absolute -bottom-1 -right-1 sm:-bottom-2 sm:-right-2 w-6 h-6 sm:w-8 sm:h-8 bg-success rounded-full border-2 sm:border-4 border-green-500 bg-green-500 flex items-center justify-center shadow-sm">
                                    <i class="fas fa-check text-white text-xs sm:text-sm"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- User Details -->
                        <div class="flex-1 text-center sm:text-left w-full">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-3">
                                <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 font-inter"><?= htmlspecialchars($user_name); ?></h2>
                                <?php if (!empty($is_verified) && $is_verified == 1): ?>
                                    <span class="px-2 sm:px-3 py-1 bg-green-100 text-green-800 text-xs sm:text-sm font-medium rounded-full border border-green-200 whitespace-nowrap mx-auto sm:mx-0 w-fit">
                                        <i class="fas fa-shield-alt mr-1"></i>Verified Account
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 sm:px-3 py-1 bg-red-100 text-red-800 text-xs sm:text-sm font-medium rounded-full border border-red-200 whitespace-nowrap mx-auto sm:mx-0 w-fit">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Pending Verification
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-2 text-gray-600 mb-4 sm:mb-6">
                                <div class="flex items-center justify-center sm:justify-start gap-3">
                                    <i class="fas fa-envelope text-gray-400 w-4 shrink-0"></i>
                                    <span class="font-medium text-sm sm:text-base break-all"><?= htmlspecialchars($user_email); ?></span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="mb-4 sm:mb-6">
                                <?php if ($is_verified == 1): ?>
                                    <button disabled class="w-full sm:w-auto flex items-center justify-center gap-3 px-4 sm:px-6 py-2 sm:py-3 bg-green-50 text-green-700 font-semibold rounded-lg border border-green-200 cursor-not-allowed text-sm sm:text-base">
                                        <i class="fas fa-check-circle"></i>
                                        Account Verified
                                    </button>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/form" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-4 sm:px-6 py-2 sm:py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all duration-300 shadow-md hover:shadow-lg text-sm sm:text-base">
                                        <i class="fas fa-user-cog"></i>
                                        Upgrade to Verification
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Feedback Form -->
                            <div class="mt-4 sm:mt-6 p-3 sm:p-4 border rounded-lg bg-gray-50">
                                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3">Comment on this Website</h3>
                                <form action="<?= BASE_URL ?>/profilerate" method="POST" class="space-y-3">
                                    <input type="hidden" name="user_id" value="<?= $user_id; ?>">

                                    <!-- Rating -->
                                    <div class="flex items-center justify-center sm:justify-start gap-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label>
                                                <input type="radio" name="rating" value="<?= $i ?>" required class="hidden peer">
                                                <i class="fas fa-star text-gray-300 peer-checked:text-yellow-500 cursor-pointer text-lg sm:text-xl"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>

                                    <!-- Comment -->
                                    <textarea name="comment" rows="3" class="w-full border rounded-lg p-2 text-sm sm:text-base" placeholder="Write your feedback..."></textarea>

                                    <!-- Submit -->
                                    <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm sm:text-base font-medium">
                                        Submit Feedback
                                    </button>
                                </form>

                                <?php
                                // Kunin yung huling feedback na ginawa ng naka-login user para sa profile na ito
                                $current_feedback = $conn->query("
                                          SELECT * FROM user_feedback 
                                          WHERE user_id = $user_id AND author_id = {$_SESSION['user_id']}
                               ORDER BY created_at DESC LIMIT 1
                                     ");
                                ?>

                                <?php if ($current_feedback && $current_feedback->num_rows > 0):
                                    $fb = $current_feedback->fetch_assoc(); ?>

                                    <details class="mt-4 border rounded-lg bg-white shadow-sm">
                                        <summary class="cursor-pointer px-3 sm:px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-100 rounded-t-lg">
                                            Your Latest Review
                                        </summary>
                                        <div class="p-3 sm:p-4 border-t">
                                            <!-- Rating -->
                                            <div class="flex items-center gap-1 mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-sm sm:text-base <?= $i <= $fb['rating'] ? 'text-yellow-500' : 'text-gray-300' ?>"></i>
                                                <?php endfor; ?>
                                            </div>

                                            <!-- Comment -->
                                            <p class="text-gray-700 text-sm mb-2"><?= htmlspecialchars($fb['comment']); ?></p>
                                            <span class="text-xs text-gray-500">Submitted on <?= date('M j, Y g:i A', strtotime($fb['created_at'])); ?></span>
                                        </div>
                                    </details>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="w-full lg:w-96 xl:w-80 2xl:w-96">
                    <!-- Addresses Card -->
                    <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4 shadow-sm mb-3 sm:mb-4">
                        <div class="flex items-center justify-between mb-3 sm:mb-4">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                                    <i class="fas fa-map-marker-alt text-green-600 text-sm sm:text-base"></i>
                                </div>
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-gray-500">Delivery Addresses</p>
                                    <p class="text-sm sm:text-base font-bold text-gray-900"><?= count($billing_addresses) ?> Saved</p>
                                </div>
                            </div>
                            <button onclick="openBillingModal()" class="px-2 sm:px-3 py-1 sm:py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-xs sm:text-sm font-medium whitespace-nowrap">
                               <i class="fa-solid fa-street-view"></i>
                            </button>
                        </div>

                        <button onclick="window.location.href='<?= BASE_URL ?>/address'" class="w-full px-3 sm:px-4 py-2 bg-black text-white rounded-lg hover:bg-blue-700 transition-colors text-xs sm:text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>Add New Address
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Billing Addresses Modal -->
      <div id="billingModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
            <div class="bg-white rounded-xl p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-slide-up shadow-2xl">
                <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-map-marker-alt text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 font-inter">Delivery Addresses</h3>
                            <p class="text-sm text-gray-600"><?= count($billing_addresses) ?> saved addresses</p>
                        </div>
                    </div>
                    <button onclick="closeBillingModal()" class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center hover:bg-gray-200 transition-colors">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>

                <!-- Add New Address Button -->
                <div class="mb-6">
                    <button onclick="window.location.href='<?= BASE_URL ?>/address'" class="w-full px-6 py-3 bg-black text-white rounded-lg hover:bg-blue-700 transition-all duration-300 flex items-center justify-center gap-2 font-medium">
                        <i class="fas fa-plus"></i>
                        Add New Address
                    </button>
                </div>

                <!-- Addresses Content -->
                <div class="space-y-4">
                    <?php if (empty($billing_addresses)): ?>
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-map-marker-alt text-2xl text-gray-400"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">No Addresses Saved</h4>
                            <p class="text-gray-600 mb-6">Add your first delivery address for faster checkout</p>
                            <button onclick="window.location.href='<?= BASE_URL ?>/address'" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                <i class="fas fa-plus"></i>
                                Add Address
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($billing_addresses as $index => $address): ?>
                                <div class="border border-gray-200 rounded-lg p-6 hover:border-gray-300 hover:shadow-md transition-all duration-300 bg-white">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-user text-green-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900"><?= htmlspecialchars($address['full_name']) ?></h4>
                                                <?php if ($index === 0): ?>
                                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full font-medium border border-green-200">Primary Address</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-2">
                                            <button onclick="editAddress(<?= $address['id'] ?>)"
                                                class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="Edit Address">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteAddress(<?= $address['id'] ?>, '<?= htmlspecialchars($address['full_name']) ?>')"
                                                class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete Address">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Contact Info -->
                                    <div class="space-y-3 mb-4">
                                        <div class="flex items-center gap-3 text-sm text-gray-600">
                                            <i class="fas fa-phone text-gray-400 w-4"></i>
                                            <span><?= htmlspecialchars($address['phone']) ?></span>
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-map-marker-alt text-gray-500 mt-1  shrink-0"></i>
                                            <div>
                                                <p class="text-sm text-gray-700 font-medium mb-1"><?= htmlspecialchars($address['address']) ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']) ?>
                                                </p>
                                            </div>
                                        </div>

                                        <?php if (!empty($address['notes'])): ?>
                                            <div class="mt-3 pt-3 border-t border-gray-200">
                                                <div class="flex items-start gap-2">
                                                    <i class="fas fa-sticky-note text-gray-400 text-xs mt-0.5"></i>
                                                    <p class="text-xs text-gray-600 italic">
                                                        <?= htmlspecialchars($address['notes']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <?php
    // ====================================
    // GET RECENTLY VIEWED BY USER (for order_history.php)
    // ====================================
function getRecentlyViewedByUser($conn, $user_id, $limit = 10)
{
    $sql = "SELECT 
            p.*,
            p.descrip6,
            p.descrip7,
            p.view_count,
            p.unique_view_count,
        SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT v.origin), ',', 1) as origin,
        SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT v.discount), ',', 1) as discount,
        SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT v.percent), ',', 1) as percent,
        SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT v.status), ',', 1) as status,
            
            -- 🔥 SIZE PRICES (variant prices only)
            COALESCE(MIN(pv.price), 0) as min_size_price,  
            COALESCE(MAX(pv.price), 0) as max_size_price,  
            
            -- 🔥 COLOR PRICES (color prices only - kept separate)
            COALESCE(MIN(pc.price), 0) as min_color_price,
            COALESCE(MAX(pc.price), 0) as max_color_price,
            
            COUNT(DISTINCT pc.id) as color_count,
            AVG(r.rating) AS avg_rating,
            COUNT(DISTINCT r.id) AS rating_count,
            COALESCE((
                SELECT SUM(quantity) FROM sold_items WHERE product_id = p.id
            ), 0) AS total_sold,
            
            MAX(rv.viewed_at) as last_viewed
        FROM recent_views rv
        INNER JOIN products p ON rv.product_id = p.id
        LEFT JOIN product_variants v ON v.product_id = p.id
        LEFT JOIN product_variants pv ON p.id = pv.product_id
        LEFT JOIN product_colors pc ON p.id = pc.product_id
        LEFT JOIN product_ratings r ON r.product_id = p.id
        LEFT JOIN sold_items si ON si.product_id = p.id
        WHERE rv.user_id = ?
        GROUP BY p.id
        ORDER BY MAX(rv.viewed_at) DESC
        LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();

    return $stmt->get_result();
}

    // ====================================
    // RENDER PRODUCT CARD FOR ORDER HISTORY
    // ====================================
    function renderProductCardOrderHistory($row, $conn)
    {
        $product_id = (int)$row['id'];

        // 🔥 Calculate smart price using your existing formula
        $priceData = [
            'min_size_price' => $row['min_size_price'],
            'max_size_price' => $row['max_size_price'],
            'min_color_price' => $row['min_color_price'],
            'max_color_price' => $row['max_color_price'],
            'price' => $row['price'],
            'discount' => $row['discount'],
            'percent' => $row['percent']
        ];

        $min_size = floatval($row['min_size_price'] ?? 0);
        $max_size = floatval($row['max_size_price'] ?? 0);
        $min_color = floatval($row['min_color_price'] ?? 0);
        $max_color = floatval($row['max_color_price'] ?? 0);

        // 🔥 SIMPLE FORMULA: size_price + color_price (no extraction needed)
        // min_size_price & max_size_price are ALREADY just variant prices
        // min_color_price & max_color_price are ALREADY just color prices
        $min_final = $min_size + $min_color;
        $max_final = $max_size + $max_color;

        // Ensure no negative values
        if ($min_final < 0) $min_final = 0;
        if ($max_final < 0) $max_final = 0;

        if ($min_final != $max_final) {
            $displayPrice = '₱' . number_format($min_final, 2) . ' - ₱' . number_format($max_final, 2);
        } else {
            $displayPrice = '₱' . number_format($min_final, 2);
        }

        // Get rating
        $avg_rating = floatval($row['avg_rating'] ?? 0);
        $total_raters = (int)($row['rating_count'] ?? 0);
        $total_sold = (int)($row['total_sold'] ?? 0);

        $full = floor($avg_rating);
        $half = ($avg_rating - $full >= 0.5) ? 1 : 0;
        $empty = 5 - $full - $half;

        $view_count = formatViewCount($row['view_count'] ?? 0);
    ?>
        <a href="<?= BASE_URL ?>/productview?id=<?= $product_id ?>" class="group">
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col h-full">

                <!-- Image Container -->
                <div class="relative bg-gray-50 overflow-hidden" style="aspect-ratio: 1/1;">
                    <?php if (!empty($row['main_image'])): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($row['main_image']) ?>" loading="lazy" alt="<?= htmlspecialchars($row['product_name']) ?>" class="w-full h-full object-contain p-2 group-hover:scale-110 transition-transform duration-300" />
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gray-100">
                            <i class="fas fa-image text-gray-300 text-3xl"></i>
                        </div>
                    <?php endif; ?>

                    <!-- Recently Viewed Badge -->
                    <div class="absolute top-2 right-2 bg-white/95 backdrop-blur-sm px-2 py-1 rounded-full text-xs font-semibold text-red-700 shadow-sm border border-blue-100 flex items-center gap-1">
                        <i class="fas fa-clock text-xs"></i>Viewed
                    </div>
                </div>

                <!-- Content -->
                <div class="p-3 flex flex-col grow">
                    <!-- Product Name -->
                    <h3 class="text-sm font-semibold text-black line-clamp-2 mb-2 group-hover:text-blue-600 transition-colors">
                        <?= htmlspecialchars($row['product_name']) ?>
                    </h3>

                    <!-- Rating -->
                    <div class="flex items-center gap-1.5 mb-2">
                        <?php if ($total_raters > 0): ?>
                            <div class="flex text-yellow-400 text-xs">
                                <?php for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>'; ?>
                                <?php if ($half) echo '<i class="fas fa-star-half-alt"></i>'; ?>
                                <?php for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>'; ?>
                            </div>
                            <span class="text-xs text-gray-500 font-medium">(<?= $total_raters ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">No ratings yet</span>
                        <?php endif; ?>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-2 mb-2 flex-wrap">
                        <p class="text-sm font-bold text-gray-900"><?= $displayPrice ?></p>

                    </div>

                    <!-- View & Sold Stats -->
                    <div class="text-xs text-gray-500 font-medium border-t border-gray-100 pt-2 mt-auto">
                        <?php if ($view_count): ?>
                            <span><?= $view_count ?> viewing</span>
                        <?php endif; ?>

                        <?php if ($total_sold > 0): ?>
                            <?php if ($view_count): ?> | <?php endif; ?>
                            <span><?= number_format($total_sold) ?> sold</span>
                        <?php endif; ?>
                    </div>

                    <!-- View Button -->
                    <button class="mt-3 text-start underline text-black text-xs font-bold  ">
                        Quick view
                    </button>
                </div>
            </div>
        </a>
    <?php
    }

    // Helper function
    function formatViewCount($count)
    {
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        } elseif ($count >= 1000) {
            return round($count / 1000, 1) . 'K';
        }
        return number_format($count);
    }
    ?>


    <?php if ($recent_count > 0): ?>
        <div class="mt-10 sm:mt-12 mb-8">
            <div class="max-w-7xl mx-auto px-4">
                <!-- Section Header -->
                <div class="flex items-center justify-between mb-6 sm:mb-8 pb-4 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-8 bg-black rounded-full"></div>
                        <div>
                            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Your Recently Viewed</h2>
                            <p class="text-sm text-gray-500 mt-1">Products you've looked at recently</p>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/shop" class="text-sm text-black hover:text-blue-700 font-semibold whitespace-nowrap">
                        See more <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
                    <?php
                    $recent_products->data_seek(0);
                    while ($row = $recent_products->fetch_assoc()):
                    ?>
                        <div>
                            <?php renderProductCardOrderHistory($row, $conn); ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>

    <script>
        const BASE_URL = `<?= BASE_URL ?>`;
        function editAddress(addressId) {
            // Redirect to edit page with address ID
            window.location.href = `${BASE_URL}/address?edit=${addressId}`;
        }

        function deleteAddress(addressId, fullName) {
    if (!confirm(`Are you sure you want to delete the address for "${fullName}"?\n\nThis action cannot be undone.`)) {
        return; // ✅ gamitin return, hindi mag-proceed
    }

    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.innerHTML = '<div class="w-4 h-4 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>';
    button.disabled = true;

    fetch(`${BASE_URL}/address`, {  // ✅ siguraduhing tama ang URL
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            address_id: addressId
        })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // ✅ Alisin ang card sa DOM agad, hindi na mag-reload
            const card = button.closest('.border.border-gray-200.rounded-lg');
            if (card) {
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    // Update count
                    const countEl = document.querySelector('#billingModal .text-sm.text-gray-600');
                    if (countEl) {
                        const current = parseInt(countEl.textContent) || 0;
                        countEl.textContent = `${Math.max(0, current - 1)} saved addresses`;
                    }
                }, 300);
            }
        } else {
            alert(data.message || 'Failed to delete address');
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

        // Billing Modal Functions
        function openBillingModal() {
            document.getElementById('billingModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeBillingModal() {
            document.getElementById('billingModal').classList.add('hidden');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside
        document.getElementById('billingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBillingModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBillingModal();
            }
        });
    </script>
</body>

</html>