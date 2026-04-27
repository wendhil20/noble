<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

// ✅ Restore session from remember_token
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

// Check if viewing specific inspiration
$viewing_inspiration = false;
$inspiration = null;

if (isset($_GET['id'])) {
    $inspiration_id = intval($_GET['id']);
    $query = "SELECT ai.*, c.name as category_name FROM admin_inspiration ai 
              LEFT JOIN categories c ON ai.category_id = c.id 
              WHERE ai.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $inspiration_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $inspiration = $result->fetch_assoc();
        $viewing_inspiration = true;
    }
    $stmt->close();
}

// Fetch featured inspirations (status = 'on')
$featured_inspirations = [];
$query = "SELECT id, name, type, category_id, main_image FROM admin_inspiration WHERE status = 'on' ORDER BY updated_at DESC LIMIT 2";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $featured_inspirations = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch all inspirations for list
$inspirations = [];
if (!$viewing_inspiration) {
    $query = "SELECT id, name, type, category_id, main_image FROM admin_inspiration ORDER BY created_at DESC";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $inspirations = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $viewing_inspiration ? htmlspecialchars($inspiration['name']) : 'Inspirations'; ?></title>
    <style>
        .swiper-image {
            display: none;
            opacity: 0;
        }

        .swiper-image.active {
            display: block;
            animation: fadeInImage 0.5s ease-out forwards;
        }

        @keyframes fadeInImage {
            from {
                opacity: 0;
                transform: scale(0.98);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .gradient-text {
            background: linear-gradient(135deg, #3d2f26 0%, #6b5545 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(61, 47, 38, 0.15);
        }

        .button-hover {
            position: relative;
            overflow: hidden;
        }

        .button-hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: left 0.3s ease;
        }

        .button-hover:hover::before {
            left: 100%;
        }

        .hero-title {
            animation: slideInDown 0.8s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .image-container {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #f5f1e8 0%, #ede6de 100%);
        }

        .meta-badge {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, rgba(61, 47, 38, 0.05) 0%, rgba(107, 85, 69, 0.05) 100%);
            border-left: 3px solid #8b7355;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #3d2f26;
        }
    </style>
</head>

<body class="bg-white min-h-screen" style="color: #3d2f26">
    <?php include ROOT_PATH . '/user/navbar/top.php' ?>

    <?php if ($viewing_inspiration && $inspiration): ?>
        <!-- DETAIL VIEW -->
        <div class="py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Back Button with Icon Animation -->
                <a href="?page=inspirations" class="inline-flex items-center gap-2 text-bronze-900 font-semibold text-sm uppercase tracking-widest mb-12 group hover:gap-3 transition-all duration-300">
                    <i class="fas fa-arrow-left text-bronze-700 group-hover:-translate-x-1 transition-transform"></i>
                    Back to Collections
                </a>

                <!-- HERO SECTION - Stunning Title and Image -->
                <?php if ($inspiration['main_image']): ?>
                    <div class="mb-12">
                        <!-- Image with Overlay -->
                        <div class="relative h-96 md:h-[600px] image-container rounded-2xl overflow-hidden shadow-2xl hero-title">
                            <img src="../../uploads/<?php echo htmlspecialchars($inspiration['main_image']); ?>"
                                alt="<?php echo htmlspecialchars($inspiration['name']); ?>"
                                class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/40"></div>
                        </div>

                        <!-- Title Below Image - Large & Elegant -->
                        <div class="mt-8 mb-12">
                            <h1 class="font-display text-6xl md:text-7xl lg:text-8xl font-bold leading-tight text-bronze-900 mb-6 hero-title">
                                <?php echo htmlspecialchars($inspiration['name']); ?>
                            </h1>

                            <!-- Meta Information - Type & Category -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                                <div class="glass-effect p-6 rounded-lg">
                                    <span class="meta-badge">Type</span>
                                    <p class="text-2xl font-bold text-bronze-900 mt-4 tracking-wide"><?php echo htmlspecialchars($inspiration['type']); ?></p>
                                </div>
                                <div class="glass-effect p-6 rounded-lg">
                                    <span class="meta-badge">Category</span>
                                    <p class="text-2xl font-bold text-bronze-900 mt-4 tracking-wide"><?php echo htmlspecialchars($inspiration['category_name']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Fallback -->
                    <div class="mb-16">
                        <h1 class="font-display text-7xl md:text-8xl font-bold text-bronze-900 mb-8 hero-title">
                            <?php echo htmlspecialchars($inspiration['name']); ?>
                        </h1>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="glass-effect p-8 rounded-lg">
                                <span class="meta-badge">Type</span>
                                <p class="text-2xl font-bold text-bronze-900 mt-4"><?php echo htmlspecialchars($inspiration['type']); ?></p>
                            </div>
                            <div class="glass-effect p-8 rounded-lg">
                                <span class="meta-badge">Category</span>
                                <p class="text-2xl font-bold text-bronze-900 mt-4"><?php echo htmlspecialchars($inspiration['category_name']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                $image_1_array = json_decode($inspiration['image_1'], true) ?? [];
                $image_2_array = json_decode($inspiration['image_2'], true) ?? [];
                $image_3_array = json_decode($inspiration['image_3'], true) ?? [];
                $description_1 = $inspiration['description_image_1'] ?? '';
                $description_2 = $inspiration['description_image_2'] ?? '';
                $description_3 = $inspiration['description_image_3'] ?? '';
                ?>

                <!-- CONTENT SECTIONS -->
                <!-- Section 1 -->
                <?php if (!empty($image_1_array) || $description_1): ?>
                    <div class="py-16 border-t border-bronze-200">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                            <div class="space-y-6">
                                <h2 class="font-display text-4xl font-bold text-bronze-900">Explore</h2>
                                <p class="text-lg text-stone-700 leading-relaxed whitespace-pre-wrap wrap-break-word font-light">
                                    <?php echo nl2br(htmlspecialchars($description_1)); ?>
                                </p>
                            </div>

                            <div>
                                <div class="relative image-container rounded-xl overflow-hidden shadow-lg" id="gallery_1">
                                    <?php foreach ($image_1_array as $idx => $img): ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>"
                                            class="swiper-image <?php echo $idx === 0 ? 'active' : ''; ?> w-full h-96 lg:h-[500px] object-cover">
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($image_1_array) > 1): ?>
                                    <div class="flex justify-between items-center mt-6 gap-3">
                                        <button onclick="swiperPrev('gallery_1')" class="button-hover flex-1 bg-bronze-900 text-white py-3 px-6 rounded-lg font-semibold text-sm transition-all hover:bg-bronze-800 hover:shadow-lg">
                                            <i class="fas fa-chevron-left mr-2"></i>Previous
                                        </button>
                                        <span class="bg-bronze-100 text-bronze-900 py-3 px-6 rounded-lg font-bold text-sm tracking-wide border border-bronze-300">
                                            <span id="counter-1">1</span> / <?php echo count($image_1_array); ?>
                                        </span>
                                        <button onclick="swiperNext('gallery_1')" class="button-hover flex-1 bg-bronze-900 text-white py-3 px-6 rounded-lg font-semibold text-sm transition-all hover:bg-bronze-800 hover:shadow-lg">
                                            Next<i class="fas fa-chevron-right ml-2"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Section 2 -->
                <?php if (!empty($image_2_array) || $description_2): ?>
                    <div class="py-16 border-t border-bronze-200">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                            <div class="order-2 lg:order-1">
                                <div class="relative image-container rounded-xl overflow-hidden shadow-lg" id="gallery_2">
                                    <?php foreach ($image_2_array as $idx => $img): ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>"
                                            class="swiper-image <?php echo $idx === 0 ? 'active' : ''; ?> w-full h-96 lg:h-[500px] object-cover">
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($image_2_array) > 1): ?>
                                    <div class="flex justify-between items-center mt-6 gap-3">
                                        <button onclick="swiperPrev('gallery_2')" class="button-hover flex-1 bg-bronze-900 text-white py-3 px-6 rounded-lg font-semibold text-sm transition-all hover:bg-bronze-800 hover:shadow-lg">
                                            <i class="fas fa-chevron-left mr-2"></i>Previous
                                        </button>
                                        <span class="bg-bronze-100 text-bronze-900 py-3 px-6 rounded-lg font-bold text-sm tracking-wide border border-bronze-300">
                                            <span id="counter-2">1</span> / <?php echo count($image_2_array); ?>
                                        </span>
                                        <button onclick="swiperNext('gallery_2')" class="button-hover flex-1 bg-bronze-900 text-white py-3 px-6 rounded-lg font-semibold text-sm transition-all hover:bg-bronze-800 hover:shadow-lg">
                                            Next<i class="fas fa-chevron-right ml-2"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="order-1 lg:order-2 space-y-6">
                                <h2 class="font-display text-4xl font-bold text-bronze-900">Details</h2>
                                <p class="text-lg text-stone-700 leading-relaxed whitespace-pre-wrap wrap-break-word font-light">
                                    <?php echo nl2br(htmlspecialchars($description_2)); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Section 3 -->
                <?php if (!empty($image_3_array) || $description_3): ?>
                    <div class="py-16 border-t border-bronze-200">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                            <div class="space-y-6">
                                <h2 class="font-display text-4xl font-bold text-bronze-900">Inspiration</h2>
                                <p class="text-lg text-stone-700 leading-relaxed whitespace-pre-wrap wrap-break-word font-light">
                                    <?php echo nl2br(htmlspecialchars($description_3)); ?>
                                </p>
                            </div>

                            <div>
                                <div class="relative image-container rounded-xl overflow-hidden shadow-lg" id="gallery_3">
                                    <?php foreach ($image_3_array as $idx => $img): ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>"
                                            class="swiper-image <?php echo $idx === 0 ? 'active' : ''; ?> w-full h-96 lg:h-[500px] object-cover">
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($image_3_array) > 1): ?>
                                    <div class="flex justify-between items-center mt-6 gap-3">
                                        <button onclick="swiperPrev('gallery_3')" class="button-hover flex-1 bg-bronze-900 text-white py-3 px-6 rounded-lg font-semibold text-sm transition-all hover:bg-bronze-800 hover:shadow-lg">
                                            <i class="fas fa-chevron-left mr-2"></i>Previous
                                        </button>
                                        <span class="bg-bronze-100 text-bronze-900 py-3 px-6 rounded-lg font-bold text-sm tracking-wide border border-bronze-300">
                                            <span id="counter-3">1</span> / <?php echo count($image_3_array); ?>
                                        </span>
                                        <button onclick="swiperNext('gallery_3')" class="button-hover flex-1 bg-bronze-900 text-white py-3 px-6 rounded-lg font-semibold text-sm transition-all hover:bg-bronze-800 hover:shadow-lg">
                                            Next<i class="fas fa-chevron-right ml-2"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- LIST VIEW -->
        <div class="py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-20">
                    <h1 class="font-display text-7xl md:text-8xl font-bold text-bronze-900 mb-6 hero-title">
                        Inspirations
                    </h1>
                    <p class="text-xl text-stone-600 font-light max-w-2xl">
                        Curated collection of design ideas and creative concepts to spark your imagination
                    </p>
                    <div class="h-1 w-20 bg-black mt-6 rounded-full"></div>
                </div>

                <!-- FEATURED SECTION -->
                <?php if (!empty($featured_inspirations)): ?>
                    <div class="mb-24">
                        <div class="mb-8">
                            <h2 class="font-display text-5xl md:text-6xl font-bold text-bronze-900">FEATURED</h2>
                            <div class="h-1 w-16 bg-black mt-4 rounded-full"></div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <?php foreach ($featured_inspirations as $featured): ?>
                                <a href="?id=<?php echo $featured['id']; ?>" class="card-hover group">
                                    <div class="overflow-hidden rounded-2xl">
                                        <!-- Image Container -->
                                        <div class="relative h-96 md:h-[500px] image-container overflow-hidden">
                                            <?php if ($featured['main_image']): ?>
                                                <img src="../../uploads/<?php echo htmlspecialchars($featured['main_image']); ?>"
                                                    alt="<?php echo htmlspecialchars($featured['name']); ?>"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end">
                                                    <div class="p-8">
                                                        <span class="text-white font-semibold text-lg uppercase tracking-wider flex items-center gap-2">
                                                            Discover <i class="fas fa-arrow-right"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center bg-black">
                                                    <i class="fas fa-image text-bronze-300 text-6xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Content -->
                                        <div class="p-8 bg-white border-t-4 border-bronze-700">
                                            <h3 class="font-display text-4xl md:text-5xl text-bronze-900 font-bold mb-3 group-hover:text-bronze-700 transition-colors">
                                                <?php echo htmlspecialchars($featured['name']); ?>
                                            </h3>
                                            <p class="text-base text-stone-600 font-medium uppercase tracking-wide"><?php echo htmlspecialchars($featured['type']); ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ALL INSPIRATIONS SECTION -->
                <div class="mb-12">
                    <h2 class="font-display text-5xl md:text-6xl font-bold text-bronze-900">ALL INSPIRATIONS</h2>
                    <div class="h-1 w-16 bg-black mt-4 rounded-full"></div>
                </div>

                <?php if (empty($inspirations)): ?>
                    <div class="text-center py-24">
                        <i class="fas fa-image text-bronze-200 text-8xl mb-8 block"></i>
                        <h3 class="font-display text-4xl text-bronze-900 font-bold mb-4">No inspirations yet</h3>
                        <p class="text-lg text-stone-600">Check back soon for new amazing content</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach ($inspirations as $insp): ?>
                            <a href="?id=<?php echo $insp['id']; ?>" class="card-hover group">
                                <div class="overflow-hidden rounded-xl bg-white  hover:border-bronze-400 transition-all">
                                    <!-- Image -->
                                    <div class="relative h-72 image-container overflow-hidden">
                                        <?php if ($insp['main_image']): ?>
                                            <img src="../../uploads/<?php echo htmlspecialchars($insp['main_image']); ?>"
                                                alt="<?php echo htmlspecialchars($insp['name']); ?>"
                                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                                <span class="text-white font-bold text-sm uppercase tracking-widest flex items-center gap-2">
                                                    <i class="fas fa-arrow-right"></i>View
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-black">
                                                <i class="fas fa-image text-bronze-300 text-5xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Content -->
                                    <div class="p-5 border-t-3 border-bronze-700">
                                        <h3 class="font-display text-2xl text-bronze-900 font-bold group-hover:text-bronze-700 transition-colors line-clamp-2">
                                            <?php echo htmlspecialchars($insp['name']); ?>
                                        </h3>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php include '../navbar/footer.php' ?>

    <script>
        function swiperNext(galleryId) {
            const gallery = document.getElementById(galleryId);
            if (!gallery) return;

            const images = gallery.querySelectorAll('.swiper-image');
            const activeImg = gallery.querySelector('.swiper-image.active');
            const currentIndex = Array.from(images).indexOf(activeImg);
            const nextIndex = (currentIndex + 1) % images.length;

            activeImg.classList.remove('active');
            images[nextIndex].classList.add('active');

            updateCounter(galleryId, nextIndex, images.length);
        }

        function swiperPrev(galleryId) {
            const gallery = document.getElementById(galleryId);
            if (!gallery) return;

            const images = gallery.querySelectorAll('.swiper-image');
            const activeImg = gallery.querySelector('.swiper-image.active');
            const currentIndex = Array.from(images).indexOf(activeImg);
            const prevIndex = (currentIndex - 1 + images.length) % images.length;

            activeImg.classList.remove('active');
            images[prevIndex].classList.add('active');

            updateCounter(galleryId, prevIndex, images.length);
        }

        function updateCounter(galleryId, currentIndex, totalImages) {
            const counterId = 'counter-' + galleryId.split('_')[1];
            const counter = document.getElementById(counterId);
            if (counter) {
                counter.textContent = currentIndex + 1;
            }
        }
    </script>
</body>

</html>