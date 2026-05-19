<?php

include ROOT_PATH . '/connection/connect.php';

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


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noble Home Corp - About Us</title>
  
    <style>
        .slide-section {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            opacity: 0;
            transition: opacity 0.8s ease;
            pointer-events: none;
        }

        .slide-section.active-slide {
            opacity: 1;
            pointer-events: auto;
            z-index: 10;
        }

        .bg-overlay {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5));
        }

        .nav-button {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            z-index: 40;
            background: rgba(249, 115, 22, 0.9);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .nav-button:hover {
            background: rgba(249, 115, 22, 1);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 10px 30px rgba(249, 115, 22, 0.5);
        }

        .nav-button.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .nav-button-left {
            left: 30px;
        }

        .nav-button-right {
            right: 30px;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .nav-button {
                width: 45px;
                height: 45px;
            }

            .nav-button-left {
                left: 15px;
            }

            .nav-button-right {
                right: 15px;
            }

            .nav-button i {
                font-size: 1.25rem;
            }

            /* Prevent zoom on double tap */
            * {
                touch-action: manipulation;
            }
        }

        @media (max-width: 640px) {
            .nav-button {
                width: 40px;
                height: 40px;
            }

            .nav-button-left {
                left: 10px;
            }

            .nav-button-right {
                right: 10px;
            }

            .nav-button i {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body class="overflow-x-hidden">
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <!-- Navigation Buttons -->
    <button id="prevBtn" class="nav-button nav-button-left">
        <i class="fas fa-chevron-left text-2xl"></i>
    </button>
    <button id="nextBtn" class="nav-button nav-button-right">
        <i class="fas fa-chevron-right text-2xl"></i>
    </button>
    <!-- Scrollable Content -->
    <div class="relative h-[600vh]">

        <!-- Slide 1: Hero -->
        <section class="slide-section active-slide flex items-center justify-center p-4 sm:p-6 md:p-8" data-slide="0">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img src="<?= BASE_URL; ?>/user/img/saleandexplore/a.png" alt="Hero Background" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/50"></div>
            </div>

            <!-- Content Card -->
            <div class="relative z-10 rounded-3xl p-6 sm:p-10 md:p-16 max-w-5xl w-full">
                <div class="text-center">
                    <p class="text-base sm:text-lg md:text-xl text-white mb-4 sm:mb-6" data-aos="fade-down">Building Excellence, that is</p>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-7xl font-light text-white leading-tight uppercase" data-aos="fade-up" data-aos-delay="300">
                        About us NobleHome<span class="block text-orange-500">depot.</span>
                        our <span class="block text-orange-500">mission.</span>
                        our <span class="block text-orange-500">vision.</span>
                    </h1>
                    <div class="mt-8 sm:mt-10 md:mt-12 animate-bounce">
                        <i class="fas fa-chevron-down text-2xl sm:text-3xl md:text-4xl text-white"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Slide 2: Introduction -->
        <section class="slide-section flex items-center justify-center p-4 sm:p-6 md:p-8" data-slide="1">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?w=1920" alt="Construction Background" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/70"></div>
            </div>

            <!-- Content Card -->
            <div class="relative z-10 p-6 sm:p-10 md:p-16 max-w-6xl w-full">
                <div class="grid md:grid-cols-2 gap-6 sm:gap-8 md:gap-12 items-center">
                    <div data-aos="fade-right" data-aos-delay="200">
                        <span class="inline-block px-4 sm:px-5 md:px-6 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-full text-xs sm:text-sm uppercase tracking-wider mb-4 sm:mb-6">
                            Who We Are
                        </span>
                        <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-light text-white leading-tight">
                            <strong class="text-white uppercase underline decoration-2 underline-offset-4">NobleHome Depot</strong> is a trusted supplier of <strong class="text-white uppercase underline underline-offset-4">high-quality</strong> construction materials across the Philippines.
                        </h2>
                    </div>
                    <div class="space-y-4 sm:space-y-6">
                        <p class="text-base sm:text-lg md:text-xl text-white leading-relaxed" data-aos="fade-up" data-aos-delay="400">
                            We serve builders, contractors, and developers with a comprehensive range of products including AAC blocks, fiber cement boards, tiles, aluminum windows, and more.
                        </p>
                        <p class="text-base sm:text-lg md:text-xl text-white leading-relaxed" data-aos="fade-up" data-aos-delay="600">
                            Our commitment is to deliver reliable solutions for all types of construction projects, ensuring quality and excellence in every product.
                        </p>
                    </div>
                </div>
            </div>
        </section>


        <!-- Slide 3: Mission -->
        <section class="slide-section flex items-center justify-center p-4 sm:p-6 md:p-8" data-slide="2">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1920" alt="Modern Home Background" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/70"></div>
            </div>

            <!-- Content Card -->
            <div class="relative z-10 backdrop-blur-sm rounded-3xl p-6 sm:p-10 md:p-16 max-w-6xl w-full shadow-2xl" data-aos="fade-up">
                <div class="grid md:grid-cols-5 gap-6 sm:gap-8 md:gap-12 items-center">
                    <!-- Video Section - Hidden on Mobile, Visible on Desktop -->
                    <div class="hidden md:block md:col-span-2" data-aos="fade-up" data-aos-delay="200">
                        <video autoplay muted loop playsinline class="w-full rounded-2xl shadow-xl">
                            <source src="<?= BASE_URL; ?>/video/g.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>

                    <!-- Button Section - Visible on Mobile Only -->
                    <div class="md:hidden flex justify-center order-2" data-aos="fade-up" data-aos-delay="200">
                        <button id="openVideoModal" class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-8 py-4 rounded-2xl text-base font-semibold shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105 flex items-center gap-3">
                            <i class="fas fa-play-circle text-2xl"></i>
                            Watch
                        </button>
                    </div>

                    <div class="md:col-span-3 order-1 md:order-2" data-aos="fade-up" data-aos-delay="300">
                        <span class="inline-block px-4 sm:px-5 md:px-6 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-full text-xs sm:text-sm  uppercase tracking-wider mb-4 sm:mb-6">
                            Our Mission
                        </span>
                        <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-light text-white leading-tight mb-4 sm:mb-6 uppercase">
                            Transforming homes into <span class="bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent">timeless, elegant spaces</span>
                        </h2>
                        <p class="text-base sm:text-lg md:text-xl text-white leading-relaxed mb-3 sm:mb-4">
                            At NobleHome Construction Corporation, our mission is to provide high-quality, innovative furnishing solutions that transform homes into timeless, elegant spaces.
                        </p>
                        <p class="text-base sm:text-lg md:text-xl text-white leading-relaxed">
                            We are committed to delivering exceptional value, superior customer service, and a diverse range of products that meet the unique needs of every home.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <!-- Video Modal -->
        <div id="videoModal" class="fixed inset-0 bg-black/90 z-[200] hidden items-center justify-center p-4">
            <div class="relative w-full max-w-lg sm:max-w-xl md:max-w-2xl">
                <button id="closeVideoModal" class="absolute -top-10 sm:-top-12 right-0 text-white text-2xl sm:text-3xl md:text-4xl hover:text-orange-500 transition-colors">
                    <i class="fas fa-times-circle"></i>
                </button>
                <div class="bg-black rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl">
                    <video id="modalVideo" controls playsinline class="w-full">
                        <source src="../../video/g.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>

        <!-- Slide 4: Vision -->
        <section class="slide-section flex items-center justify-center p-4 sm:p-6 md:p-8" data-slide="3">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=1920" alt="Vision Background" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/70"></div>
            </div>

            <!-- Content Card -->
            <div class="relative z-10 bg-white/95 backdrop-blur-lg rounded-3xl p-6 sm:p-10 md:p-16 max-w-6xl w-full shadow-2xl">
                <div class="text-center mb-8 sm:mb-10 md:mb-12" data-aos="fade-down">
                    <span class="inline-block px-4 sm:px-5 md:px-6 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-full text-xs sm:text-sm font-semibold uppercase tracking-wider mb-4 sm:mb-6">
                        Our Vision
                    </span>
                    <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-light text-gray-900 leading-tight max-w-4xl mx-auto px-4">
                        To be the <strong class="font-semibold bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent">leading provider</strong> of premium furnishing supplies in the Philippines
                    </h2>
                </div>
                <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-6 sm:p-8 rounded-2xl transition-all duration-300 hover:border-2 hover:border-orange-500 hover:-translate-y-2 hover:shadow-xl border-2 border-transparent" data-aos="fade-up" data-aos-delay="200">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mb-3 sm:mb-4">
                            <i class="fas fa-home text-white text-lg sm:text-xl md:text-2xl"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2">One-Stop Destination</h3>
                        <p class="text-sm sm:text-base text-gray-600">Complete solutions for style, quality, and affordability in one place.</p>
                    </div>
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-6 sm:p-8 rounded-2xl transition-all duration-300 hover:border-2 hover:border-orange-500 hover:-translate-y-2 hover:shadow-xl border-2 border-transparent" data-aos="fade-up" data-aos-delay="400">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mb-3 sm:mb-4">
                            <i class="fas fa-users text-white text-lg sm:text-xl md:text-2xl"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2">Empower Homeowners</h3>
                        <p class="text-sm sm:text-base text-gray-600">Helping every homeowner create spaces that reflect their unique taste.</p>
                    </div>
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-6 sm:p-8 rounded-2xl transition-all duration-300 hover:border-2 hover:border-orange-500 hover:-translate-y-2 hover:shadow-xl border-2 border-transparent sm:col-span-2 md:col-span-1" data-aos="fade-up" data-aos-delay="600">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mb-3 sm:mb-4">
                            <i class="fas fa-star text-white text-lg sm:text-xl md:text-2xl"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2">Elevate Living</h3>
                        <p class="text-sm sm:text-base text-gray-600">Inspiring designs that enhance everyday living experiences.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Slide 5: Values -->
        <section class="slide-section flex items-center justify-center p-4 sm:p-6 md:p-8" data-slide="4">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1600607687644-aac4c3eac7f4?w=1920" alt="Values Background" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-900/80 to-indigo-800/80"></div>
            </div>

            <!-- Content Card -->
            <div class="relative z-10 bg-white/95 backdrop-blur-lg rounded-3xl p-6 sm:p-10 md:p-16 max-w-6xl w-full shadow-2xl">
                <div class="text-center mb-8 sm:mb-10 md:mb-12" data-aos="fade-down">
                    <span class="inline-block px-4 sm:px-5 md:px-6 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-full text-xs sm:text-sm font-semibold uppercase tracking-wider mb-4 sm:mb-6">
                        Core Values
                    </span>
                    <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-light text-gray-900 leading-tight max-w-3xl mx-auto px-4">
                        The <strong class="font-semibold bg-gradient-to-r from-orange-500 to-orange-600 bg-clip-text text-transparent">principles</strong> that guide everything we do
                    </h2>
                </div>
                <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-8 sm:gap-10 md:gap-12">
                    <div class="text-center" data-aos="flip-left" data-aos-delay="200">
                        <div class="w-16 h-16 sm:w-18 sm:h-18 md:w-20 md:h-20 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mx-auto mb-4 sm:mb-6">
                            <i class="fas fa-shield-alt text-white text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-2 sm:mb-3">Trust</h3>
                        <p class="text-sm sm:text-base md:text-lg text-gray-600 leading-relaxed">
                            Building lasting relationships through transparency and reliability in every interaction.
                        </p>
                    </div>
                    <div class="text-center" data-aos="flip-left" data-aos-delay="400">
                        <div class="w-16 h-16 sm:w-18 sm:h-18 md:w-20 md:h-20 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mx-auto mb-4 sm:mb-6">
                            <i class="fas fa-gem text-white text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-2 sm:mb-3">Excellence</h3>
                        <p class="text-sm sm:text-base md:text-lg text-gray-600 leading-relaxed">
                            Committed to the highest standards in every product and service we deliver.
                        </p>
                    </div>
                    <div class="text-center sm:col-span-2 md:col-span-1" data-aos="flip-left" data-aos-delay="600">
                        <div class="w-16 h-16 sm:w-18 sm:h-18 md:w-20 md:h-20 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center mx-auto mb-4 sm:mb-6">
                            <i class="fas fa-handshake text-white text-2xl sm:text-3xl"></i>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-2 sm:mb-3">Partnership</h3>
                        <p class="text-sm sm:text-base md:text-lg text-gray-600 leading-relaxed">
                            Collaborating with clients to achieve their construction goals and dreams.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Slide 6: CTA -->
        <section class="slide-section flex items-center justify-center p-4 sm:p-6 md:p-8" data-slide="5">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1600585154526-990dced4db0d?w=1920" alt="CTA Background" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-br from-red-900/80 to-orange-800/80"></div>
            </div>

            <!-- Content Card -->
            <div class="relative z-10 bg-white/95 backdrop-blur-lg rounded-3xl p-6 sm:p-10 md:p-16 max-w-5xl w-full shadow-2xl text-center" data-aos="zoom-in">
                <span class="inline-block px-4 sm:px-5 md:px-6 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-full text-xs sm:text-sm font-semibold uppercase tracking-wider mb-4 sm:mb-6" data-aos="fade-down" data-aos-delay="200">
                    Let's Build Together
                </span>
                <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-light text-gray-900 leading-tight mb-4 sm:mb-6 px-4" data-aos="fade-up" data-aos-delay="400">
                    Ready to build your <span class="block text-orange-500 font-semibold">dream project?</span>
                </h2>
                <p class="text-lg sm:text-xl md:text-2xl text-gray-600 mb-8 sm:mb-10 md:mb-12 max-w-2xl mx-auto px-4" data-aos="fade-up" data-aos-delay="600">
                    Let's create something extraordinary together. Get in touch with our team today.
                </p>
                <button class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-8 sm:px-12 md:px-16 py-4 sm:py-5 rounded-full text-base sm:text-lg md:text-xl font-semibold hover:shadow-2xl transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="800">
                    <i class="fas fa-envelope mr-2 sm:mr-3"></i> Contact Us Now
                </button>
            </div>
        </section>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: false,
            mirror: false,
            offset: 0,
            easing: 'ease-out'
        });

        let currentSlide = 0;
        const totalSlides = 6;
        const slides = document.querySelectorAll('.slide-section');
        let isScrolling = false;

        // Handle scroll events
        let scrollTimeout;
        window.addEventListener('wheel', (e) => {
            if (isScrolling) return;

            e.preventDefault();

            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                if (e.deltaY > 0) {
                    nextSlide();
                } else {
                    prevSlide();
                }
            }, 50);
        }, {
            passive: false
        });

        // Handle touch events for mobile
        let touchStartY = 0;
        let touchEndY = 0;

        window.addEventListener('touchstart', (e) => {
            touchStartY = e.changedTouches[0].screenY;
        }, {
            passive: true
        });

        window.addEventListener('touchend', (e) => {
            if (isScrolling) return;

            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        }, {
            passive: true
        });

        function handleSwipe() {
            const swipeThreshold = 50;
            if (touchStartY - touchEndY > swipeThreshold) {
                nextSlide();
            } else if (touchEndY - touchStartY > swipeThreshold) {
                prevSlide();
            }
        }

        // Handle keyboard navigation
        window.addEventListener('keydown', (e) => {
            if (isScrolling) return;

            if (e.key === 'ArrowDown' || e.key === 'PageDown' || e.key === ' ') {
                e.preventDefault();
                nextSlide();
            } else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
                e.preventDefault();
                prevSlide();
            }
        });

        function nextSlide() {
            if (currentSlide < totalSlides - 1) {
                currentSlide++;
                updateSlide(currentSlide);
            }
        }

        function prevSlide() {
            if (currentSlide > 0) {
                currentSlide--;
                updateSlide(currentSlide);
            }
        }

        // Navigation button handlers
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        prevBtn.addEventListener('click', () => {
            prevSlide();
        });

        nextBtn.addEventListener('click', () => {
            nextSlide();
        });

        function updateNavigationButtons() {
            if (currentSlide === 0) {
                prevBtn.classList.add('disabled');
            } else {
                prevBtn.classList.remove('disabled');
            }

            if (currentSlide === totalSlides - 1) {
                nextBtn.classList.add('disabled');
            } else {
                nextBtn.classList.remove('disabled');
            }
        }

        function updateSlide(slideIndex) {
            isScrolling = true;

            slides.forEach((slide) => {
                slide.classList.remove('active-slide');

                slide.querySelectorAll('[data-aos]').forEach(el => {
                    el.classList.remove('aos-animate');
                });
            });

            slides[slideIndex].classList.add('active-slide');

            updateNavigationButtons();

            setTimeout(() => {
                const activeSlide = slides[slideIndex];

                activeSlide.querySelectorAll('[data-aos]').forEach(el => {
                    void el.offsetHeight;
                    el.classList.add('aos-animate');
                });

                isScrolling = false;
            }, 100);
        }

        // Initialize navigation buttons on load
        updateNavigationButtons();

        // Video Modal Handlers
        const videoModal = document.getElementById('videoModal');
        const openVideoBtn = document.getElementById('openVideoModal');
        const closeVideoBtn = document.getElementById('closeVideoModal');
        const modalVideo = document.getElementById('modalVideo');

        if (openVideoBtn) {
            openVideoBtn.addEventListener('click', () => {
                videoModal.classList.remove('hidden');
                videoModal.classList.add('flex');
                modalVideo.play();
            });
        }

        if (closeVideoBtn) {
            closeVideoBtn.addEventListener('click', () => {
                videoModal.classList.add('hidden');
                videoModal.classList.remove('flex');
                modalVideo.pause();
                modalVideo.currentTime = 0;
            });
        }

        // Close modal when clicking outside
        videoModal.addEventListener('click', (e) => {
            if (e.target === videoModal) {
                videoModal.classList.add('hidden');
                videoModal.classList.remove('flex');
                modalVideo.pause();
                modalVideo.currentTime = 0;
            }
        });
    </script>

</body>

</html>