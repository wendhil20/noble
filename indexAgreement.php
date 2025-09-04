<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NobleHome Depot - Your Premium Furniture Destination</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            overflow-x: hidden;
        }

        .fade-in-left {
            opacity: 0;
            transform: translateX(-100px);
            animation: fadeInLeft 1.2s ease-out forwards;
        }

        .fade-in-right {
            opacity: 0;
            transform: translateX(100px);
            animation: fadeInRight 1.2s ease-out forwards;
        }

        .fade-in-center {
            opacity: 0;
            transform: translateY(50px);
            animation: fadeInCenter 1.5s ease-out forwards;
        }

        @keyframes fadeInLeft {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInCenter {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .delay-1 {
            animation-delay: 0.3s;
        }

        .delay-2 {
            animation-delay: 0.9s;
        }

        .delay-3 {
            animation-delay: 1.6s;
        }

        .gradient-text {
            background: linear-gradient(135deg, #f59e0b, #d97706, #92400e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Logo Container - Responsive Layout */
        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            border-radius: 1rem;
            overflow: hidden;
            
            transition: transform 0.3s ease;
        }

        .logo-wrapper:hover {
            transform: scale(1.05);
        }

        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }

        /* Typography Responsive Sizes */
        .main-title {
            font-size: 2.5rem;
            line-height: 1.1;
            text-align: center;
            font-weight: 800;
        }

        .subtitle {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .card-content {
            text-align: center;
        }

        .card-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .card-text {
            font-size: 1rem;
            line-height: 1.6;
        }

        .cta-title {
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }

        .cta-text {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .cta-button {
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            width: 100%;
            max-width: 280px;
        }

        /* Small Mobile (480px and up) */
        @media (min-width: 480px) {
            .logo-wrapper {
                width: 90px;
                height: 90px;
            }

            .main-title {
                font-size: 3rem;
            }

            .subtitle {
                font-size: 1.3rem;
            }

            .card-title {
                font-size: 1.6rem;
            }

            .card-text {
                font-size: 1.1rem;
            }

            .cta-title {
                font-size: 2.2rem;
            }

            .cta-button {
                font-size: 1.2rem;
                width: auto;
                padding: 1rem 3rem;
            }
        }

        /* Tablet Portrait (768px and up) */
        @media (min-width: 768px) {
            .logo-container {
                flex-direction: row;
                gap: 1.5rem;
            }

            .logo-wrapper {
                width: 100px;
                height: 100px;
            }

            .main-title {
                font-size: 4rem;
                text-align: left;
            }

            .subtitle {
                font-size: 1.5rem;
                margin-bottom: 3rem;
            }

            .card-content {
                text-align: left;
            }

            .card-title {
                font-size: 1.8rem;
                margin-bottom: 1.2rem;
            }

            .card-text {
                font-size: 1.2rem;
                line-height: 1.7;
            }

            .card-content-right {
                text-align: right;
            }

            .cta-title {
                font-size: 2.8rem;
                margin-bottom: 1.8rem;
            }

            .cta-text {
                font-size: 1.3rem;
                margin-bottom: 2.5rem;
            }

            .cta-button {
                padding: 1rem 3rem;
                font-size: 1.3rem;
            }
        }

        /* Tablet Landscape / Small Desktop (1024px and up) */
        @media (min-width: 1024px) {
            .logo-wrapper {
                width: 120px;
                height: 120px;
            }

            .main-title {
                font-size: 5rem;
            }

            .subtitle {
                font-size: 1.8rem;
            }

            .card-title {
                font-size: 2rem;
                margin-bottom: 1.5rem;
            }

            .card-text {
                font-size: 1.3rem;
            }

            .cta-title {
                font-size: 3.2rem;
                margin-bottom: 2rem;
            }

            .cta-text {
                font-size: 1.4rem;
            }

            .cta-button {
                padding: 1.2rem 3.5rem;
                font-size: 1.4rem;
            }
        }

        /* Desktop (1280px and up) */
        @media (min-width: 1280px) {
            .logo-wrapper {
                width: 140px;
                height: 140px;
            }

            .main-title {
                font-size: 6rem;
            }

            .subtitle {
                font-size: 2rem;
            }

            .card-title {
                font-size: 2.2rem;
            }

            .card-text {
                font-size: 1.4rem;
            }

            .cta-title {
                font-size: 3.5rem;
            }

            .cta-text {
                font-size: 1.5rem;
            }
        }

        /* Large Desktop (1536px and up) */
        @media (min-width: 1536px) {
            .logo-wrapper {
                width: 160px;
                height: 160px;
            }

            .main-title {
                font-size: 6.5rem;
            }

            .cta-title {
                font-size: 4rem;
            }
        }

        /* Reduce motion for users who prefer it */
        @media (prefers-reduced-motion: reduce) {
            .fade-in-left,
            .fade-in-right,
            .fade-in-center {
                animation: none;
                opacity: 1;
                transform: none;
            }

            .cta-button:hover,
            .logo-wrapper:hover {
                transform: none;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .glass-card {
                background: rgba(0, 0, 0, 0.8);
                border: 2px solid #ffffff;
            }

            .gradient-text {
                background: #f59e0b;
                -webkit-background-clip: initial;
                -webkit-text-fill-color: initial;
                background-clip: initial;
                color: #f59e0b;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #111827;
                color: #f9fafb;
            }

            .glass-card {
                background: rgba(0, 0, 0, 0.3);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .card-text,
            .cta-text,
            .card-title {
                color: #f9fafb;
            }
        }

        /* Landscape orientation adjustments for mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .logo-wrapper {
                width: 60px;
                height: 60px;
            }

            .main-title {
                font-size: 2rem;
            }

            .subtitle {
                font-size: 1rem;
                margin-bottom: 1rem;
            }

            .glass-card {
                padding: 1rem;
            }

            .cta-title {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            .cta-text {
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }
        }

        /* Center alignment for very small screens */
        @media (max-width: 479px) {
            .logo-container {
                text-align: center;
            }
            
            .main-title {
                text-align: center;
            }
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center py-4 sm:py-8 lg:py-12 px-4">
    <div class="max-w-sm sm:max-w-lg md:max-w-2xl lg:max-w-4xl xl:max-w-5xl mx-auto w-full">

        <!-- Header Section -->
        <div class="text-center mb-8 sm:mb-12 md:mb-16 lg:mb-25 fade-in-center">
            <div class="logo-container">
                <!-- Logo Image -->
                <div class="logo-wrapper ">
                    <img src="user/img/logo.png" alt="NobleHome Logo" class="logo-img">
                </div>
                <div class="text-center md:text-left">
                    <h1 class="main-title font-bold text-orange-400">NobleHome Depot</h1>
                </div>
            </div>
            <p class="subtitle text-black">Your Premium Furniture Marketplace</p>
        </div>

        <!-- Main Content Cards - Alternating Layout -->
        <div class="space-y-4 sm:space-y-6 lg:space-y-8 mb-8 sm:mb-12 lg:mb-16">
            <!-- Card 1 - From Left -->
            <div class="glass-card rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 fade-in-left delay-1">
                <div class="flex items-center">
                    <div class="card-content w-full">
                        <h3 class="card-title font-bold text-black">Who We Are</h3>
                        <p class="card-text text-black leading-relaxed">
                            NobleHome Depot is a trusted online furniture marketplace specializing in high-quality furniture pieces for your home. We've become known throughout the Philippines for our commitment to quality and customer satisfaction.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Card 2 - From Right -->
            <div class="glass-card rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 fade-in-right delay-2">
                <div class="flex items-center">
                    <div class="card-content card-content-right w-full md:text-right">
                        <h3 class="card-title font-bold text-black">Why Choose Us</h3>
                        <p class="card-text text-black leading-relaxed">
                            We offer premium furniture that's still affordable. We have a secure payment system through bank transfers, professional delivery service, and 24/7 customer support. All our products are carefully curated for quality assurance.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Card 3 - From Left -->
            <div class="glass-card rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 fade-in-left delay-3">
                <div class="flex items-center">
                    <div class="card-content w-full">
                        <h3 class="card-title font-bold text-black">Our Mission</h3>
                        <p class="card-text text-black leading-relaxed">
                            We want every Filipino family to have access to beautiful, durable, and affordable furniture for their dream home. We're not just about sales, but building long-term relationships with our customers.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="text-center fade-in-center delay-3">
            <div class="glass-card rounded-xl sm:rounded-2xl p-6 sm:p-8 lg:p-12">
                <h2 class="cta-title font-bold text-orange-400">Ready to Transform Your Home?</h2>
                <p class="cta-text text-black max-w-xs sm:max-w-lg lg:max-w-2xl mx-auto">
                    Join us on the journey towards creating the perfect living space for your family. Explore our wide collection of premium furniture today!
                </p>
                <button id="enterBtn" class="cta-button bg-orange-400 text-white font-bold rounded-xl hover:bg-orange-500 transform hover:scale-105 transition-all duration-300 mx-auto block">
                   Next
                </button>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center mt-8 sm:mt-12 lg:mt-16 fade-in-center delay-3">
            <p class="text-gray-400 text-sm sm:text-base">© 2025 NobleHome Depot. All rights reserved.</p>
        </footer>

    </div>

   <script>
        // Check if user has already entered before
        window.addEventListener('load', function() {
            const hasEntered = localStorage.getItem('noblehome_entered');
            if (hasEntered === 'true') {
                // User has entered before, redirect immediately
                document.body.style.display = 'none';
                window.location.href = 'user/otherpage/index';
                return;
            }
        });

        // Enter button click handler
        document.getElementById('enterBtn').addEventListener('click', function() {
            this.innerHTML = 'Entering...';
            this.disabled = true;
            
            // Mark that user has entered the site
            localStorage.setItem('noblehome_entered', 'true');
            localStorage.setItem('enter_timestamp', Date.now().toString());
            
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        });

        // Optional: Add reset function (you can call this if needed)
        function resetLandingPage() {
            localStorage.removeItem('noblehome_entered');
            localStorage.removeItem('enter_timestamp');
            location.reload();
        }

       // Optional: Auto-reset after certain period (uncomment if needed)
        window.addEventListener('load', function() {
            const timestamp = localStorage.getItem('enter_timestamp');
            if (timestamp) {
                const daysPassed = (Date.now() - parseInt(timestamp)) / (1000 * 60 * 60 * 24);
                if (daysPassed > 7) { // Reset after 7 days
                    localStorage.removeItem('noblehome_entered');
                    localStorage.removeItem('enter_timestamp');
                }
            }
        });
    </script>
</body>

</html>