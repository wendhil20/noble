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
    </style>
</head>

<body class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-4xl mx-auto">

        <!-- Header Section -->
        <div class="text-center mb-25 fade-in-center">
            <div class="flex justify-center items-center mb-6">
                <!-- Logo Image -->
                <div class="w-20 h-20 rounded-2xl flex items-center justify-center mr-4  overflow-hidden">
                    <img src="user/img/logo.png" alt="NobleHome Logo" class="w-full h-full object-cover">
                </div>
                <h1 class="text-6xl font-bold text-orange-400">NobleHome Depot</h1>
            </div>
            <p class="text-2xl text-black mb-8">Your Premium Furniture Marketplace</p>
        </div>


        <!-- Main Content Cards - Alternating Layout -->
        <div class="space-y-8 mb-10">
            <!-- Card 1 - From Left -->
            <div class="glass-card rounded-2xl p-8  fade-in-left delay-1">
                <div class="flex items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-black mb-4">Who We Are</h3>
                        <p class="text-black text-lg leading-relaxed">
                            NobleHome Depot is a trusted online furniture marketplace specializing in high-quality furniture pieces for your home. We've become known throughout the Philippines for our commitment to quality and customer satisfaction.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Card 2 - From Right -->
            <div class="glass-card rounded-2xl p-8 fade-in-right delay-2">
                <div class="flex items-center">

                    <div class="order-1 text-right">
                        <h3 class="text-2xl font-bold text-black mb-4">Why Choose Us</h3>
                        <p class="text-black text-lg leading-relaxed">
                            We offer premium furniture that's still affordable. We have a secure payment system through bank transfers, professional delivery service, and 24/7 customer support. All our products are carefully curated for quality assurance.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Card 3 - From Left -->
            <div class="glass-card rounded-2xl p-8 fade-in-left delay-3">
                <div class="flex items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-black mb-4">Our Mission</h3>
                        <p class="text-black text-lg leading-relaxed">
                            We want every Filipino family to have access to beautiful, durable, and affordable furniture for their dream home. We're not just about sales, but building long-term relationships with our customers.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="text-center fade-in-center delay-3">
            <div class="glass-card rounded-2xl p-12 ">
                <h2 class="text-4xl font-bold text-orange-400 mb-6">Ready to Transform Your Home?</h2>
                <p class="text-xl text-black mb-8 max-w-2xl mx-auto">
                    Join us on the journey towards creating the perfect living space for your family. Explore our wide collection of premium furniture today!
                </p>
                <button id="enterBtn" class="px-12 py-4 bg-orange-400 text-white text-xl font-bold rounded-xl hover:from-amber-600 hover:to-orange-700 transform hover:scale-105 transition-all duration-300">
                   Next
                </button>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center mt-16 fade-in-center delay-3">
            <p class="text-gray-400">© 2025 NobleHome Depot. All rights reserved.</p>
            
        </footer>

    </div>

    <script>
        // Manual redirect button
        document.getElementById('enterBtn').addEventListener('click', function() {
            this.innerHTML = 'Entering...';
            this.disabled = true;
            setTimeout(() => {
                window.location.href = 'user/otherpage/index';
            }, 1000);
        });
    </script>
</body>

</html>