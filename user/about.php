<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noble Home Corp - About Us</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
          .font-poppins {
    font-family: 'Poppins', sans-serif;
  }

  .font-opensans {
    font-family: 'Open Sans', sans-serif;
  }

        .shadow-subtle {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .border-accent {
            border-color: #e97517;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }
        .text-primary {
            color: #1e40af;
        }
        .text-accent {
            color: #e97517;
        }
        .bg-accent {
            background-color: #e97517;
        }
        .bg-primary {
            background-color: #1e40af;
        }
    </style>


</head>
<body class="bg-gray-50">
    <?php include 'navbar/top.php'; ?>
    <!-- Header Section -->
    <div class="bg-white border-b-4 border-accent">
        <div class="max-w-7xl mx-auto px-6 py-12">
            <h1 class="text-4xl md:text-5xl font-bold text-orange-600 text-center font-poppins">About Us</h1>
                  <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
        </div>
    </div>
    
    <div class=" px-6 py-12">
        <!-- Company Introduction -->
        <div class="bg-white rounded-lg shadow-subtle p-8 mb-12">
            <div class="flex flex-col md:flex-row items-center gap-8">
                <div class="flex-shrink-0">
                    <img src="img/logo/logo.png" alt="Noble Home Corp Logo" class="w-32 h-32 object-contain">
                </div>
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4 font-poppins"><span class="text-orange-500">Noble Home</span> Contruction Corporation </h2>
                    <p class="text-lg text-gray-700 leading-relaxed ">
                        A trusted supplier of high-quality construction materials, serving builders, contractors, and developers across the Philippines. We offer a comprehensive range of products including AAC blocks, fiber cement boards, tiles, aluminum windows, and more—delivering reliable solutions for all types of construction projects.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Mission & Vision Section -->
        <div class="grid md:grid-cols-2 gap-12 items-start mb-12">
            <!-- Left: Image -->
            <div>
                <img src="img/about.png" alt="Mission and Vision" class="w-full h-[530px] object-contain rounded-lg ">
            </div>

            <!-- Right: Mission and Vision -->
            <div class="space-y-8">
                <!-- Mission -->
                <div class="bg-white rounded-lg shadow-subtle p-8 hover-lift">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-bullseye text-white text-xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-orange-500 font-poppins">Our Mission   <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div></h2>
                            
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        At NobleHome Construction Corporation, our mission is to provide high-quality, innovative furnishing solutions that transform homes into timeless, elegant spaces. We are committed to delivering exceptional value, superior customer service, and a diverse range of products that meet the unique needs of every home.
                    </p>
                </div>

                <!-- Vision -->
                <div class="bg-white rounded-lg shadow-subtle p-8 hover-lift">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-accent rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-eye text-white text-xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-orange-500 font-poppins">Our Vision <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div></h2>
                              
                    </div>
                    <p class="text-gray-700 leading-relaxed">
                        To be the leading provider of premium furnishing supplies, offering customers a one-stop destination for style, quality, and affordability. We envision empowering every homeowner to create inspiring spaces that reflect their individual tastes and elevate their everyday living.
                    </p>
                </div>
            </div>
        </div>

        <!-- Core Values Section -->
        <div class="bg-white rounded-lg shadow-subtle p-8">
            <h2 class="text-3xl font-bold text-orange-500 text-center mb-12 font-poppins">
                Our Core Values   <div class="mx-auto w-32 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
            </h2>
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Product Abilities -->
                <div class="text-center hover-lift">
                    <div class="w-20 h-20 bg-blue-50 border-2 border-primary rounded-lg flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-cogs text-2xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">PRODUCT ABILITIES</h3>
                    <p class="text-gray-700 leading-relaxed">
                        We have a warehouse that supplies all of our products and materials. With all of our sister companies Ecotex, Ecopipe, Realflooring, Realiving, Instyle and GrandEast, we can provide all the products in the market.
                    </p>
                </div>
                
                <!-- Design Abilities -->
                <div class="text-center hover-lift">
                    <div class="w-20 h-20 bg-orange-50 border-2 border-accent rounded-lg flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-pencil-ruler text-2xl text-accent"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">DESIGN ABILITIES</h3>
                    <p class="text-gray-700 leading-relaxed">
                        We have expert designers, architects and engineers that collaborate to ensure the precision and accuracy of the design based on industry standards.
                    </p>
                </div>
                
                <!-- Production Abilities -->
                <div class="text-center hover-lift">
                    <div class="w-20 h-20 bg-green-50 border-2 border-green-600 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-industry text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">PRODUCTION ABILITIES</h3>
                    <p class="text-gray-700 leading-relaxed">
                        Using our automated machines CNC saw, CNC drilling, edge banding machine and other production equipment, we can manufacture and produce products on time. Our expert production team ensures quality and standards.
                    </p>
                </div>
            </div>
        </div>
    </div>

   
    <footer class="bg-black pattern-bg text-white py-16 mt-12 relative overflow-hidden">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-500"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <!-- Main Footer Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

                <!-- Enhanced Branding Section -->
                <div class="lg:col-span-2">
                    <div class="flex items-center space-x-4 mb-6">
                        <!-- Logo with glow and pulse -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl glow-effect floating overflow-hidden">
                                <img src="img/logo/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
                            </div>
                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-blue-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Text Branding -->
                        <div>
                            <h2 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">Noble Home</h2>

                        </div>
                    </div>


                    <p class="text-gray-300 leading-relaxed mb-6 max-w-md">
                        Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home awaits with our expert construction and design services.
                    </p>

                    <!-- Contact Info -->
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                    <path d="m18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">noblehomeconst.ph@gmail.com</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                </svg>
                            </div>
                            <span class="text-gray-300">0968 591 6536</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Quick Links
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <nav class="space-y-3">
                        <a href="index" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Home</a>
                        <a href="about" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">About Us</a>
                        <a href="contact" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Contact</a>
                    </nav>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-xl font-bold mb-6 text-white relative">
                        Our Services
                        <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
                    </h3>
                    <ul class="space-y-3 text-gray-300">
                        <li class="hover:text-orange-300 transition-colors cursor-pointer">Appointment</li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                        <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent mb-8"></div>

            <!-- Bottom Section -->
            <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
                <!-- Copyright -->
                <div class="text-center lg:text-left">
                    <p class="text-gray-400 text-sm">
                        © 2025 Noble Home Construction. All rights reserved.
                    </p>
                    <p class="text-gray-500 text-xs mt-1">
                        Licensed & Insured | PCAB License No. 12345
                    </p>
                </div>

                <!-- Enhanced Social Media -->
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400 text-sm mr-2">Follow us:</span>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Facebook">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Instagram">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
                        </svg>
                    </a>

                    <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="LinkedIn">
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg>
                    </a>
                </div>

                <!-- Back to Top Button -->
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
                    class="w-12 h-12 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Background Pattern -->
        <div class="absolute bottom-0 right-0 opacity-5">
            <svg width="200" height="200" viewBox="0 0 200 200" fill="none">
                <path d="M50 50h100v100H50z" stroke="currentColor" stroke-width="2" />
                <path d="M70 70h60v60H70z" stroke="currentColor" stroke-width="1" />
                <path d="M90 90h20v20H90z" stroke="currentColor" stroke-width="1" />
            </svg>
        </div>
    </footer>

</body>
</html>