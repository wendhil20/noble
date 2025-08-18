<?php 
include '../../connection/connect.php';  
$variants = mysqli_query($conn, "SELECT * FROM variants"); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Variant - Image Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-in-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'scale-in': 'scaleIn 0.3s ease-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                        'pulse-slow': 'pulseSlow 3s ease-in-out infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(30px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        scaleIn: {
                            '0%': { transform: 'scale(0.9)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        },
                        bounceGentle: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        },
                        pulseSlow: {
                            '0%, 100%': { opacity: '0.6' },
                            '50%': { opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.9);
        }
        .gradient-border {
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #f97316, #ea580c, #dc2626) border-box;
            border: 2px solid transparent;
        }
        .variant-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .variant-card:hover {
            transform: translateY(-8px) scale(1.02);
        }
    </style>
</head>
<body class="min-h-screen  relative overflow-x-hidden">

    <!-- Background Decorations -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-gradient-to-br from-orange-300 to-orange-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse-slow"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-gradient-to-br from-orange-300 to-orange-400 rounded-full mix-blend-multiply filter blur-xl opacity-25 animate-pulse-slow" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-gradient-to-br from-orange-300 to-blue-400 rounded-full mix-blend-multiply filter blur-xl opacity-15 animate-pulse-slow" style="animation-delay: 2s;"></div>
    </div>

    <!-- Include Navbar -->
    <?php include '../navbar/top.php'; ?>

    <!-- Main Content -->
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
        
        <!-- Header Section -->
        <div class="text-center space-y-6 animate-fade-in">
            <!-- Status Badge -->
            <div class="inline-flex items-center space-x-3 bg-white/80 backdrop-blur-sm rounded-full px-6 py-3 shadow-lg border border-blue-200">
                <div class="w-3 h-3 bg-blue-500 rounded-full animate-bounce-gentle"></div>
                <span class="text-sm font-semibold text-blue-700 uppercase tracking-wide">Variant Selection</span>
            </div>
            
            <!-- Main Title -->
            <div class="space-y-4">
                <h1 class="text-5xl sm:text-7xl font-black text-transparent bg-clip-text bg-gradient-to-r from-orange-600 via-orange-600 to-pink-600 leading-tight">
                    Choose Products
                </h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
                    Select a product variant to manage its images and gallery content
                </p>
            </div>

            <!-- Feature Icons -->
            <div class="flex justify-center items-center space-x-8 pt-4">
                <div class="flex items-center space-x-2 text-gray-500">
                    <div class="w-8 h-8 bg-gradient-to-r from-orange-400 to-orange-500 rounded-lg flex items-center justify-center shadow-lg">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium">Upload</span>
                </div>
                <div class="flex items-center space-x-2 text-gray-500">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-400 to-blue-500 rounded-lg flex items-center justify-center shadow-lg">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium">Edit</span>
                </div>
                <div class="flex items-center space-x-2 text-gray-500">
                    <div class="w-8 h-8 bg-gradient-to-r from-purple-400 to-purple-500 rounded-lg flex items-center justify-center shadow-lg">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium">Search</span>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="max-w-md mx-auto animate-slide-up">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" 
                       id="variantSearch" 
                       placeholder="Search variants..." 
                       class="block w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-2xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 text-lg shadow-xl hover:shadow-2xl">
            </div>
        </div>

        <!-- Variants Grid -->
        <div class="space-y-6">
            <!-- Results Counter -->
            <div class="text-center">
                <div class="inline-flex items-center space-x-2 bg-white/60 backdrop-blur-sm rounded-full px-4 py-2 shadow-lg">
                    <span id="variantCount" class="text-sm font-medium text-gray-600">
                        <?= mysqli_num_rows($variants) ?> variant(s) available
                    </span>
                </div>
            </div>

            <!-- No Results Message -->
            <div id="noVariants" class="hidden text-center py-16">
                <div class="max-w-md mx-auto">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-3">No variants found</h3>
                    <p class="text-gray-500">Try adjusting your search terms to find the variant you're looking for.</p>
                </div>
            </div>

            <!-- Variants Cards -->
            <div id="variantsGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php $index = 0; while ($variant = mysqli_fetch_assoc($variants)) : ?>
                    <a href="manage.php?variant_id=<?= $variant['id'] ?>" 
                       class="variant-card group block glass-effect rounded-2xl p-6 shadow-xl hover:shadow-2xl border border-white/30 hover:border-blue-300 animate-scale-in" 
                       style="animation-delay: <?= $index * 0.1 ?>s"
                       data-name="<?= htmlspecialchars(strtolower($variant['name'])) ?>">
                        
                        <!-- Card Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                            </div>
                            
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <svg class="w-5 h-5 text-blue-500 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Variant Name -->
                        <div class="space-y-2">
                            <h3 class="text-xl font-bold text-gray-800 group-hover:text-blue-600 transition-colors duration-300 leading-tight">
                                <?= htmlspecialchars($variant['name']) ?>
                            </h3>
                            <p class="text-sm text-gray-500 font-medium">
                                Click to manage images
                            </p>
                        </div>

                        <!-- Card Footer -->
                        <div class="mt-6 pt-4 border-t border-gray-200/50">
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span class="flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Gallery</span>
                                </span>
                                <span class="text-blue-500 font-medium group-hover:text-blue-600">
                                    ID: <?= $variant['id'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Hover Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 to-purple-500/5 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                    </a>
                <?php $index++; endwhile; ?>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="text-center pt-8 space-y-4">
            <div class="inline-flex items-center space-x-2 text-gray-500 bg-white/60 backdrop-blur-sm rounded-full px-4 py-2 shadow-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-medium">Select any variant to start managing images</span>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('variantSearch');
            const variantCards = document.querySelectorAll('.variant-card');
            const noVariants = document.getElementById('noVariants');
            const variantsGrid = document.getElementById('variantsGrid');
            const variantCount = document.getElementById('variantCount');
            
            let searchTimeout;

            // Search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                variantCards.forEach((card, index) => {
                    const name = card.getAttribute('data-name');
                    const isVisible = name.includes(searchTerm);
                    
                    if (isVisible) {
                        card.style.display = 'block';
                        card.style.animationDelay = (visibleCount * 0.1) + 's';
                        card.classList.add('animate-scale-in');
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                        card.classList.remove('animate-scale-in');
                    }
                });

                // Update counter
                variantCount.textContent = `${visibleCount} variant(s) ${searchTerm ? 'found' : 'available'}`;
                
                // Show/hide no results message
                if (visibleCount === 0 && searchTerm !== '') {
                    noVariants.classList.remove('hidden');
                    variantsGrid.classList.add('opacity-0');
                } else {
                    noVariants.classList.add('hidden');
                    variantsGrid.classList.remove('opacity-0');
                }
            }

            // Real-time search with debouncing
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 300);
            });

            // Enhanced search with keyboard shortcuts
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    performSearch();
                } else if (e.key === 'Escape') {
                    searchInput.value = '';
                    performSearch();
                    searchInput.blur();
                }
            });

            // Global search shortcut (Ctrl/Cmd + K)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });

            // Add loading animation to variant cards on click
            variantCards.forEach(card => {
                card.addEventListener('click', function() {
                    const icon = card.querySelector('svg');
                    if (icon) {
                        icon.classList.add('animate-spin');
                    }
                });
            });
        });
    </script>

</body>
</html>
