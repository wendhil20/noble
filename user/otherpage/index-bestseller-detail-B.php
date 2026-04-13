<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

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
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

$is_guest = !isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;

$slug = $_GET['slug'] ?? '';

$stmt = $conn->prepare("SELECT * FROM bestseller WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$bestseller = $result->fetch_assoc();
$sections = $conn->query("SELECT * FROM bestsellertwo WHERE bestseller_id = {$bestseller['id']} ORDER BY id ASC");

// Helper function for view count formatting
function formatViewCount($count) {
    if ($count >= 1000) {
        return number_format($count / 1000, 1) . 'k';
    }
    return number_format($count);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($bestseller['title']) ?> - Noble Hardware</title>
    <style>

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .blob-animation {
            animation: blob 7s infinite;
        }

        @keyframes blob {
            0%, 100% { border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; }
            50% { border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%; }
        }

        .card-shadow {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .card-shadow:hover {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .scale-on-hover {
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .scale-on-hover:hover {
            transform: scale(1.05) rotate(2deg);
        }

        .reveal-animation {
            opacity: 0;
            transform: translateY(30px);
            animation: reveal 0.6s ease forwards;
        }

        @keyframes reveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

    </style>
</head>

<body class="">

    <?php include '../navbar/top.php'; ?>

    <?php
    // Fetch product data with stats
    $product = null;
    $product_stats = [
        'rating' => 0,
        'total_raters' => 0,
        'total_sold' => 0,
        'view_count' => 0
    ];

    if (!empty($bestseller['product_id'])) {
        $product_id = (int)$bestseller['product_id'];
        $product_query = $conn->query("
            SELECT 
                pv.*,
                pt.type_name,
                p.product_name,
                p.main_image,
                p.view_count,
                pc.price AS color_price,
                p.id as product_id
            FROM product_variants pv
            INNER JOIN product_types pt ON pv.type_id = pt.id
            INNER JOIN products p ON pt.product_id = p.id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            WHERE p.id = $product_id
            LIMIT 1
        ");

        if ($product_query && $product_query->num_rows > 0) {
            $product = $product_query->fetch_assoc();
            $product_stats['view_count'] = $product['view_count'] ?? 0;

            // Get rating
            $rating_q = $conn->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_raters FROM product_ratings WHERE product_id = ?");
            $rating_q->bind_param("i", $product_id);
            $rating_q->execute();
            $rating_result = $rating_q->get_result()->fetch_assoc();
            $product_stats['rating'] = $rating_result['avg_rating'] ?? 0;
            $product_stats['total_raters'] = $rating_result['total_raters'] ?? 0;
            $rating_q->close();

            // Get sold count
            $sold_q = $conn->prepare("SELECT SUM(quantity) as total_sold FROM sold_items WHERE product_id = ?");
            $sold_q->bind_param("i", $product_id);
            $sold_q->execute();
            $sold_result = $sold_q->get_result()->fetch_assoc();
            $product_stats['total_sold'] = (int)($sold_result['total_sold'] ?? 0);
            $sold_q->close();
        }
    }

    // Calculate star display
    $full = floor($product_stats['rating']);
    $half = ($product_stats['rating'] - $full >= 0.5) ? 1 : 0;
    $empty = 5 - $full - $half;
    ?>

    <!-- Hero Section with Product Stats -->
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden">

        <!-- Main Content -->
        <div class="relative z-10 container mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                
                <!-- Left: Text Content -->
                <div class="text-center lg:text-left reveal-animation">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 px-6 py-3  text-black rounded-full mb-6 shadow-lg transform hover:scale-105 transition-transform">
                     
                        <span class="font-bold text-sm tracking-wide">TOP BESTSELLER</span>
                       
                    </div>

                    <!-- Title -->
                    <h1 class="text-5xl sm:text-6xl lg:text-7xl font-black mb-6 leading-tight">
                        <span class="text-black"><?= htmlspecialchars($bestseller['title']) ?></span>
                    </h1>

                    <!-- Description -->
                    <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                        <?= htmlspecialchars($bestseller['description']) ?>
                    </p>

                    <!-- Product Stats - Real Data -->
                    <?php if ($product): ?>
                        <div class="flex flex-wrap items-center gap-4 mb-8 justify-center lg:justify-start">
                            <!-- Rating -->
                            <?php if ($product_stats['total_raters'] > 0): ?>
                                <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-full shadow-md">
                                    <div class="flex text-yellow-400 text-sm">
                                        <?php for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>'; ?>
                                        <?php if ($half) echo '<i class="fas fa-star-half-alt"></i>'; ?>
                                        <?php for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>'; ?>
                                    </div>
                                    <span class="font-semibold text-gray-900"><?= number_format($product_stats['rating'], 1) ?></span>
                                    <span class="text-gray-500 text-sm">(<?= number_format($product_stats['total_raters']) ?>)</span>
                                </div>
                            <?php endif; ?>

                            <!-- Sold Count -->
                            <?php if ($product_stats['total_sold'] > 0): ?>
                                <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-full shadow-md">
                                   
                                    <span class="font-semibold text-gray-900"><?= number_format($product_stats['total_sold']) ?></span>
                                    <span class="text-gray-500 text-sm">Sold</span>
                                </div>
                            <?php endif; ?>

                            <!-- View Count -->
                            <?php if ($product_stats['view_count'] > 0): ?>
                                <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-full shadow-md">
                                  
                                    <span class="font-semibold text-gray-900"><?= formatViewCount($product_stats['view_count']) ?></span>
                                    <span class="text-gray-500 text-sm">views</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- CTA Button -->
                    <?php if ($product): ?>
                        <form action="index-product_view-page-4-AA" method="GET">
                            <input type="hidden" name="id" value="<?= (int)$product['product_id'] ?>">
                            <button type="submit"
                                class="group relative inline-flex items-center gap-3 px-10 py-5  text-black font-bold text-lg rounded-2xl overflow-hidden shadow-2xl transform hover:scale-105 transition-all duration-300">
                                <span class="relative z-10 flex items-center gap-3">
                                   
                                    View Product!
                                    <i class="fas fa-arrow-right group-hover:translate-x-2 transition-transform"></i>
                                </span>
                                <div class="absolute inset-0 bg-white/20 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="inline-flex items-center gap-3 px-10 py-5 bg-gray-300 text-gray-500 font-bold text-lg rounded-2xl cursor-not-allowed">
                            <i class="fas fa-times-circle"></i>
                            Currently Unavailable
                        </button>
                    <?php endif; ?>

                </div>

                <!-- Right: Product Image -->
                <div class="reveal-animation" style="animation-delay: 0.2s;">
                    <div class="relative">
                        <div class="absolute inset-0  rounded-full blur-3xl opacity-30"></div>
                        <img src="<?= htmlspecialchars($bestseller['image']) ?>"
                            alt="<?= htmlspecialchars($bestseller['title']) ?>"
                            class="relative z-10 w-full max-w-lg mx-auto drop-shadow-2xl transform hover:scale-105 transition-transform duration-500 ">
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Content Sections with Modern Cards -->
    <section class=" relative">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <?php if ($sections->num_rows > 0): ?>
                <div class="space-y-24">
                <?php $sectionIndex = 0; ?>
                <?php while ($section = $sections->fetch_assoc()):
                    $images = json_decode($section['image'], true) ?: [];
                    $sectionIndex++;
                ?>

                    <div class="bg-white rounded-3xl p-8 lg:p-12  reveal-animation">
                        <div class="grid lg:grid-cols-2 gap-12 items-center">
                            
                            <!-- Content Side -->
                            <div class="order-2 lg:order-1">
                                <?php if ($section['subtitle']): ?>
                                  
                                    <h2 class="text-4xl font-black text-gray-900 mb-6 leading-tight">
                                        <?= htmlspecialchars($section['subtitle']) ?>
                                    </h2>
                                <?php endif; ?>

                                <?php if ($section['content']): ?>
                                    <div class="relative">
                                        <div id="section-content-<?= $sectionIndex ?>"
                                            class="text-gray-600 text-base leading-relaxed overflow-hidden transition-all duration-500"
                                            style="max-height: 180px;">
                                            <?= nl2br(htmlspecialchars($section['content'])) ?>
                                        </div>

                                        <button id="toggle-<?= $sectionIndex ?>"
                                            class="mt-6 inline-flex items-center gap-2 px-6 py-3  text-black font-semibold rounded-full hover:shadow-lg transform hover:-translate-y-1 transition-all group">
                                            <span class="toggle-text">Expand Details</span>
                                            <i class="fas fa-angle-down toggle-icon group-hover:translate-y-1 transition-transform"></i>
                                        </button>
                                    </div>

                                    <script>
                                        (function() {
                                            const content = document.getElementById("section-content-<?= $sectionIndex ?>");
                                            const toggleBtn = document.getElementById("toggle-<?= $sectionIndex ?>");
                                            const toggleText = toggleBtn.querySelector('.toggle-text');
                                            const toggleIcon = toggleBtn.querySelector('.toggle-icon');
                                            let expanded = false;

                                            toggleBtn.addEventListener("click", () => {
                                                if (!expanded) {
                                                    content.style.maxHeight = content.scrollHeight + "px";
                                                    toggleText.textContent = "Show Less";
                                                    toggleIcon.classList.replace('fa-angle-down', 'fa-angle-up');
                                                } else {
                                                    content.style.maxHeight = "180px";
                                                    toggleText.textContent = "Expand Details";
                                                    toggleIcon.classList.replace('fa-angle-up', 'fa-angle-down');
                                                }
                                                expanded = !expanded;
                                            });
                                        })();
                                    </script>
                                <?php endif; ?>
                            </div>

                            <!-- Images Side -->
                            <?php if (!empty($images)): ?>
                                <div class="order-1 lg:order-2">
                                    <div class="grid grid-cols-2 gap-4">
                                        <?php foreach ($images as $img): ?>
                                            <div class="relative group overflow-hidden rounded-2xl p-4 scale-on-hover">
                                                <img src="<?= htmlspecialchars($img) ?>"
                                                    alt="Product Detail"
                                                    class="w-full h-48 object-contain transform group-hover:scale-110 transition-transform duration-500">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-20">
                    <div class="inline-flex items-center justify-center w-24 h-24  rounded-full mb-6">
                        <i class="fas fa-info-circle text-4xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-900 mb-3">More Coming Soon</h3>
                    <p class="text-gray-500 text-lg">Stay tuned for exciting updates!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

   

    <?php include '../navbar/footer.php'; ?>

</body>

</html>