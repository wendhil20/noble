<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NobleHome Depot - Your Premium Furniture Destination</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.19/bundled/lenis.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="min-h-screen flex items-center justify-center py-4 sm:py-8 lg:py-12 px-4 bg-white font-roboto overflow-x-hidden">
    <div class="max-w-sm sm:max-w-lg md:max-w-2xl lg:max-w-4xl xl:max-w-5xl mx-auto w-full">

        <!-- Header Section -->
        <div class="text-center mb-8 sm:mb-12 md:mb-16 lg:mb-20 opacity-0 animate-[fadeIn_1.5s_ease-out_forwards]">
            <div class="flex flex-col md:flex-row items-center justify-center gap-4 md:gap-6 mb-6">
                <!-- Logo Image -->
                <div class="w-20 h-20 sm:w-24 sm:h-24 md:w-28 md:h-28 lg:w-32 lg:h-32 xl:w-36 xl:h-36 rounded-2xl overflow-hidden transition-transform duration-300 hover:scale-105">
                    <img src="user/img/logo.png" alt="NobleHome Logo" class="w-full h-full object-contain">
                </div>
                <div class="text-center md:text-left">
                    <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl text-orange-400 leading-tight">
                        NobleHome Depot
                    </h1>
                </div>
            </div>
            <p class="text-lg sm:text-xl md:text-2xl lg:text-3xl text-black font-medium">Your Premium Furniture Marketplace</p>
        </div>

        <!-- Main Content Cards -->
        <div class="space-y-4 sm:space-y-6 lg:space-y-8 mb-8 sm:mb-12 lg:mb-16">
            
            <!-- Card 1 -->
            <div class="bg-white/80 backdrop-blur-sm  sm:rounded-2xl p-6 sm:p-8 lg:p-10 opacity-0 animate-[slideInLeft_1.2s_ease-out_0.3s_forwards] hover:shadow-xl transition-shadow duration-300">
                <div class="text-center md:text-left">
                    <h3 class="text-2xl sm:text-3xl md:text-4xl font-bold text-black mb-4">Who We Are</h3>
                    <p class="text-base sm:text-lg md:text-xl lg:text-2xl text-black leading-relaxed">
                        NobleHome Depot is a trusted online furniture marketplace specializing in high-quality furniture pieces for your home. We've become known throughout the Philippines for our commitment to quality and customer satisfaction.
                    </p>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="bg-white/80 backdrop-blur-sm  sm:rounded-2xl p-6 sm:p-8 lg:p-10 opacity-0 animate-[slideInRight_1.2s_ease-out_0.9s_forwards] hover:shadow-xl transition-shadow duration-300">
                <div class="text-center md:text-right">
                    <h3 class="text-2xl sm:text-3xl md:text-4xl font-bold text-black mb-4">Why Choose Us</h3>
                    <p class="text-base sm:text-lg md:text-xl lg:text-2xl text-black leading-relaxed">
                        We offer premium furniture that's still affordable. We have a secure payment system through bank transfers, professional delivery service, and 24/7 customer support. All our products are carefully curated for quality assurance.
                    </p>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="bg-white/80 backdrop-blur-sm   sm:rounded-2xl p-6 sm:p-8 lg:p-10 opacity-0 animate-[slideInLeft_1.2s_ease-out_1.6s_forwards] hover:shadow-xl transition-shadow duration-300">
                <div class="text-center md:text-left">
                    <h3 class="text-2xl sm:text-3xl md:text-4xl font-bold text-black mb-4">Our Mission</h3>
                    <p class="text-base sm:text-lg md:text-xl lg:text-2xl text-black leading-relaxed">
                        We want every Filipino family to have access to beautiful, durable, and affordable furniture for their dream home. We're not just about sales, but building long-term relationships with our customers.
                    </p>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="text-center opacity-0 animate-[fadeIn_1.5s_ease-out_1.6s_forwards]">
            <div class="bg-white/80 backdrop-blur-sm  rounded-xl sm:rounded-2xl p-8 sm:p-10 lg:p-14">
                <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-orange-400 mb-6">
                    Ready to Transform Your Home?
                </h2>
                <p class="text-base sm:text-lg md:text-xl lg:text-2xl text-black max-w-xs sm:max-w-lg lg:max-w-2xl mx-auto mb-8">
                    Join us on the journey towards creating the perfect living space for your family. Explore our wide collection of premium furniture today!
                </p>
                <button id="enterBtn" class="w-full sm:w-auto px-8 sm:px-12 lg:px-16 py-3 sm:py-4 text-lg sm:text-xl lg:text-2xl bg-orange-400 text-white font-bold rounded-xl hover:bg-orange-500 transform hover:scale-105 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                    Next
                </button>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center mt-8 sm:mt-12 lg:mt-16 opacity-0 animate-[fadeIn_1.5s_ease-out_1.6s_forwards]">
            <p class="text-gray-500 text-sm sm:text-base">© 2025 NobleHome Depot. All rights reserved.</p>
        </footer>

    </div>

    <style>
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        @keyframes slideInLeft {
            from { 
                opacity: 0;
                transform: translateX(-100px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }
        @keyframes slideInRight {
            from { 
                opacity: 0;
                transform: translateX(100px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>

    <script>

            // Initialize Lenis
        const lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            direction: 'vertical',
            smooth: true
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);
        // Check if user has already entered
        window.addEventListener('load', function() {
            const hasEntered = sessionStorage.getItem('noblehome_entered');
            const timestamp = sessionStorage.getItem('enter_timestamp');
            
            if (hasEntered === 'true' && timestamp) {
                const hoursPassed = (Date.now() - parseInt(timestamp)) / (1000 * 60 * 60);
                if (hoursPassed < 24) {
                    document.body.style.display = 'none';
                    window.location.href = 'user/otherpage/index';
                    return;
                }
            }
        });

        // Enter button handler
        document.getElementById('enterBtn').addEventListener('click', function() {
            this.innerHTML = 'Entering...';
            this.disabled = true;
            
            sessionStorage.setItem('noblehome_entered', 'true');
            sessionStorage.setItem('enter_timestamp', Date.now().toString());
            
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        });
    </script>
</body>
</html>