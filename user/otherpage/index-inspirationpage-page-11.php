<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        .swiper-image {
            display: none;
        }

        .swiper-image.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-white text-black min-h-screen">
    <?php include '../navbar/top.php' ?>

    <?php if ($viewing_inspiration && $inspiration): ?>
        <!-- DETAIL VIEW -->
        <div class="py-5 px-2 sm:px-4 lg:px-6">
            <div class="w-full mx-auto">
                <!-- Back Button -->
                <a href="?page=inspirations" class="inline-flex items-center gap-2 text-black font-bold text-sm uppercase tracking-wider border-b-2 border-black pb-1 mb-6 transition-transform hover:-translate-x-1">
                    <i class="fas fa-arrow-left"></i>
                    Back to List
                </a>

                <!-- Header with Image Background -->
                <?php if ($inspiration['main_image']): ?>
                    <div class="relative mb-5 ">
                        <!-- Background Image Container -->
                        <div class="relative h-96 md:h-[700px] overflow-hidden group">
                            <!-- Background Image -->
                            <img src="../../uploads/<?php echo htmlspecialchars($inspiration['main_image']); ?>"
                                alt="Main Image"
                                class="w-full h-full object-contain">

                            <!-- Dark Overlay -->
                            <div class="absolute inset-0 bg-black/20"></div>

                            <!-- Content Overlay - Title Only -->
                            <div class="absolute inset-0 p-8 md:p-20 flex flex-col justify-start">
                                <!-- Title -->
                                <h1 class="text-6xl md:text-8xl text-white tracking-tight leading-tight drop-shadow-lg font-bold">
                                    <?php echo htmlspecialchars($inspiration['name']); ?>
                                </h1>
                            </div>
                        </div>

                        <!-- Type & Category Cards - Overlapping at Bottom -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-8 md:px-20 relative z-10 -mt-8 md:-mt-12">
                            <div class="bg-white text-black p-3 border-2 border-black rounded-sm ">
                                <label class="text-xs font-bold uppercase tracking-widest opacity-70 block mb-3">Type</label>
                                <p class="text-3xl font-bold tracking-tight uppercase"><?php echo htmlspecialchars($inspiration['type']); ?></p>
                            </div>
                            <div class="bg-white text-black p-3 border-2 border-black rounded-sm ">
                                <label class="text-xs font-bold uppercase tracking-widest opacity-70 block mb-3">Category</label>
                                <p class="text-3xl font-bold tracking-tight uppercase"><?php echo htmlspecialchars($inspiration['category_name']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Fallback if no image -->
                    <div class="bg-black text-white p-12 md:p-20 mb-20 border-4 border-black">
                        <h1 class="text-6xl md:text-8xl font-black mb-8 tracking-tight leading-tight"><?php echo htmlspecialchars($inspiration['name']); ?></h1>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mt-12">
                            <div class="bg-white text-black p-8 border-4 border-white">
                                <label class="text-xs font-bold uppercase tracking-widest opacity-70 block mb-3">Type</label>
                                <p class="text-3xl font-bold tracking-tight"><?php echo htmlspecialchars($inspiration['type']); ?></p>
                            </div>
                            <div class="bg-white text-black p-8 border-4 border-white">
                                <label class="text-xs font-bold uppercase tracking-widest opacity-70 block mb-3">Category</label>
                                <p class="text-3xl font-bold tracking-tight"><?php echo htmlspecialchars($inspiration['category_name']); ?></p>
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

                <!-- Section 1 -->
                <?php if (!empty($image_1_array) || $description_1): ?>
                    <div class="p-10 md:p-16 mb-16">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
                            <div>
                                <p class="text-gray-800 text-lg leading-relaxed whitespace-pre-wrap break-words"><?php echo nl2br(htmlspecialchars($description_1)); ?></p>
                            </div>

                            <div>
                                <div class="relative overflow-hidden bg-gray-100" id="gallery_1">
                                    <?php foreach ($image_1_array as $idx => $img): ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>"
                                            class="swiper-image <?php echo $idx === 0 ? 'active' : ''; ?> w-full h-96 lg:h-[500px] object-cover">
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($image_1_array) > 1): ?>
                                    <div class="flex justify-between items-center mt-6 gap-4">
                                        <button onclick="swiperPrev('gallery_1')" class="flex-1 bg-black text-white py-4 px-8 border-2 border-black text-sm font-bold uppercase tracking-wider transition-colors hover:bg-white hover:text-black">
                                            <i class="fas fa-chevron-left mr-2"></i>Prev
                                        </button>
                                        <span class="bg-white text-black border-2 border-black py-3 px-8 font-bold text-sm tracking-wider">1 / <?php echo count($image_1_array); ?></span>
                                        <button onclick="swiperNext('gallery_1')" class="flex-1 bg-black text-white py-4 px-8 border-2 border-black text-sm font-bold uppercase tracking-wider transition-colors hover:bg-white hover:text-black">
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
                    <div class="p-10 md:p-16 mb-16">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
                            <div class="order-2 lg:order-1">
                                <div class="relative overflow-hidden bg-gray-100" id="gallery_2">
                                    <?php foreach ($image_2_array as $idx => $img): ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>"
                                            class="swiper-image <?php echo $idx === 0 ? 'active' : ''; ?> w-full h-96 lg:h-[500px] object-contain">
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($image_2_array) > 1): ?>
                                    <div class="flex justify-between items-center mt-6 gap-4">
                                        <button onclick="swiperPrev('gallery_2')" class="flex-1 bg-black text-white py-4 px-8 border-2 border-black text-sm font-bold uppercase tracking-wider transition-colors hover:bg-white hover:text-black">
                                            <i class="fas fa-chevron-left mr-2"></i>Prev
                                        </button>
                                        <span class="bg-white text-black border-2 border-black py-3 px-8 font-bold text-sm tracking-wider">1 / <?php echo count($image_2_array); ?></span>
                                        <button onclick="swiperNext('gallery_2')" class="flex-1 bg-black text-white py-4 px-8 border-2 border-black text-sm font-bold uppercase tracking-wider transition-colors hover:bg-white hover:text-black">
                                            Next<i class="fas fa-chevron-right ml-2"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="order-1 lg:order-2">
                                <p class="text-gray-800 text-lg leading-relaxed whitespace-pre-wrap break-words"><?php echo nl2br(htmlspecialchars($description_2)); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Section 3 -->
                <?php if (!empty($image_3_array) || $description_3): ?>
                    <div class="p-10 md:p-16 mb-16">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
                            <div>
                                <p class="text-gray-800 text-lg leading-relaxed whitespace-pre-wrap break-words"><?php echo nl2br(htmlspecialchars($description_3)); ?></p>
                            </div>

                            <div>
                                <div class="relative overflow-hidden bg-gray-100" id="gallery_3">
                                    <?php foreach ($image_3_array as $idx => $img): ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($img['filename']); ?>"
                                            class="swiper-image <?php echo $idx === 0 ? 'active' : ''; ?> w-full h-96 lg:h-[500px] object-contain">
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($image_3_array) > 1): ?>
                                    <div class="flex justify-between items-center mt-6 gap-4">
                                        <button onclick="swiperPrev('gallery_3')" class="flex-1 bg-black text-white py-4 px-8 border-2 border-black text-sm font-bold uppercase tracking-wider transition-colors hover:bg-white hover:text-black">
                                            <i class="fas fa-chevron-left mr-2"></i>Prev
                                        </button>
                                        <span class="bg-white text-black border-2 border-black py-3 px-8 font-bold text-sm tracking-wider">1 / <?php echo count($image_3_array); ?></span>
                                        <button onclick="swiperNext('gallery_3')" class="flex-1 bg-black text-white py-4 px-8 border-2 border-black text-sm font-bold uppercase tracking-wider transition-colors hover:bg-white hover:text-black">
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
        <div class="py-3 px-2 sm:px-4 lg:px-6">
            <div class="w-full mx-auto">
                <div class="mb-20 border-b-4 border-black pb-10">
                    <h1 class="text-5xl font-semibold mb-4 tracking-tight leading-none">Inspirations</h1>
                    <p class="text-gray-700 text-xl font-medium">Curated design ideas and creative concepts</p>
                </div>

                <!-- FEATURED SECTION -->
                <div class="mb-15">
                    <h2 class="text-4xl mb-5 tracking-tight leading-none">FEATURED</h2>
                    <?php if (!empty($featured_inspirations)): ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <?php foreach ($featured_inspirations as $featured): ?>
                                <a href="?id=<?php echo $featured['id']; ?>" class="group bg-white overflow-hidden transition-all duration-300 hover:-translate-y-4">
                                    <!-- Image -->
                                    <div class="relative h-96 md:h-[500px] overflow-hidden">
                                        <?php if ($featured['main_image']): ?>
                                            <img src="../../uploads/<?php echo htmlspecialchars($featured['main_image']); ?>"
                                                alt="<?php echo htmlspecialchars($featured['name']); ?>"
                                                class="w-full h-full object-contain transition duration-300 ">
                                            <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                                <span class="text-white font-bold text-sm uppercase tracking-widest">View Details →</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                                <i class="fas fa-image text-gray-400 text-6xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Content -->
                                    <div class="p-8 border-t-2 border-black">
                                        <h3 class="text-4xl md:text-5xl tracking-tight group-hover:underline mb-2 uppercase">
                                            <?php echo htmlspecialchars($featured['name']); ?>
                                        </h3>
                                        <p class="text-lg font-semibold text-gray-700 uppercase"><?php echo htmlspecialchars($featured['type']); ?> Learn more</p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-100 border-4 border-dashed border-gray-400 p-16 text-center">
                            <p class="text-gray-600 text-xl font-semibold">No featured items yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                
                <!-- ALL INSPIRATIONS -->
                <div class="m-4">
                    <h2 class="text-3xl md:text-4xl font-bold tracking-tight">All Inspirations</h2>
                </div>

                <?php if (empty($inspirations)): ?>
                    <div class="bg-gray-50 border-4 border-dashed border-gray-300 p-20 text-center">
                        <i class="fas fa-image text-gray-400 text-7xl mb-8 block"></i>
                        <p class="text-gray-800 text-3xl font-bold mb-3">No inspirations available</p>
                        <p class="text-gray-600 text-lg">Check back soon for new content</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                        <?php foreach ($inspirations as $insp): ?>
                            <a href="?id=<?php echo $insp['id']; ?>" class="group bg-white border-2 border-gray-200 overflow-hidden transition-all duration-300 hover:-translate-y-2 hover:border-black hover:shadow-xl">
                                <!-- Image -->
                                <div class="relative h-80 overflow-hidden">
                                    <?php if ($insp['main_image']): ?>
                                        <img src="../../uploads/<?php echo htmlspecialchars($insp['main_image']); ?>"
                                            alt="<?php echo htmlspecialchars($insp['name']); ?>"
                                            class="w-full h-full object-contain transition duration-300 group-hover:scale-105">
                                        <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                            <span class="text-white font-bold text-sm uppercase tracking-widest">View Details →</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                            <i class="fas fa-image text-gray-400 text-6xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Content -->
                                <div class="p-3 border-t-2 border-black">
                                   
                                    <h3 class="text-2xl  text-gray-900 tracking-tight group-hover:underline">
                                        <?php echo htmlspecialchars($insp['name']); ?>
                                    </h3>
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

            updateCounter(gallery, nextIndex);
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

            updateCounter(gallery, prevIndex);
        }

        function updateCounter(gallery, currentIndex) {
            const parent = gallery.parentElement;
            if (!parent) return;

            const counter = parent.querySelector('.bg-white.text-black.border-2');
            if (counter) {
                const images = gallery.querySelectorAll('.swiper-image');
                counter.textContent = `${currentIndex + 1} / ${images.length}`;
            }
        }
    </script>
</body>

</html>