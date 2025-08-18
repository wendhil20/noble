<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Variant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        bounceGentle: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-orange-400 via-orange-400 to-orange-400">
    <?php include '../navbar/top.php'; ?>
    
    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
    </div>
    
    <div class="flex-1 flex items-center justify-center px-4 py-12 relative z-10">
        <div class="w-full max-w-md animate-slide-up">
            <!-- Step indicator -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-sm rounded-full px-4 py-2 text-white">
                    <div class="w-6 h-6 bg-white text-purple-600 rounded-full flex items-center justify-center text-sm font-bold">1</div>
                    <span class="text-sm text-black font-medium">Enter New Product Name</span>
                </div>
            </div>
            
            <!-- Main form card -->
            <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl p-8 border border-white/20">
                <!-- Icon -->
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-gradient-to-r from-orange-500 to-yellow-500 rounded-full flex items-center justify-center mx-auto animate-bounce-gentle">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                
                <!-- Title -->
                <h2 class="text-2xl font-bold text-gray-800 text-center mb-2">New Product</h2>
                <p class="text-gray-600 text-center mb-8 text-sm">Enter a name for your variant to get started</p>
                
                <!-- Form -->
                <form action="start_upload.php" method="POST" class="space-y-6">
                    <div class="relative">
                        <label for="variant_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Variant Name
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="variant_name"
                                name="variant_name" 
                                placeholder="e.g., Marine, Forest, Urban..." 
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 bg-gray-50 hover:bg-white"
                            >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-orange-600 to-orange-600 hover:from-orange-700 hover:to-orange-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                    >
                        <span class="flex items-center justify-center space-x-2">
                            <span>Continue</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </span>
                    </button>
                </form>
                
                <!-- Help text -->
                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-500">
                        💡 Choose a descriptive name that helps you identify this variant later
                    </p>
                </div>
            </div>
            
            <!-- Progress dots -->
            <div class="flex justify-center space-x-2 mt-8">
                <div class="w-2 h-2 bg-white rounded-full"></div>
                <div class="w-2 h-2 bg-white/30 rounded-full"></div>
                <div class="w-2 h-2 bg-white/30 rounded-full"></div>
            </div>
        </div>
    </div>
</body>
</html>