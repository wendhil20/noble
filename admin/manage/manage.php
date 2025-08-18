<?php
include '../../connection/connect.php';

// Validate variant_id
if (!isset($_GET['variant_id']) || !is_numeric($_GET['variant_id'])) {
    echo "<p style='color:red;'>Error: Variant ID is missing or invalid.</p>";
    exit;
}

$variant_id = (int) $_GET['variant_id'];

// Fetch variant name (optional for display)
$variant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM variants WHERE id = $variant_id"));
$variant_name = $variant ? $variant['name'] : "Unknown";

// Fetch images for that variant
$images = mysqli_query($conn, "SELECT * FROM images WHERE variant_id = $variant_id");
if (!$images) {
    echo "<p style='color:red;'>Failed to fetch images.</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Images for <?= htmlspecialchars($variant_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'scale-in': 'scaleIn 0.2s ease-out'
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
                        scaleIn: {
                            '0%': { transform: 'scale(0.95)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
        .gradient-border {
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(45deg, #f97316, #ea580c) border-box;
            border: 2px solid transparent;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-orange-50 via-white to-orange-100">
    <?php include '../navbar/top.php'; ?>
    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-orange-200 rounded-full mix-blend-multiply filter blur-xl opacity-30 animate-pulse"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-orange-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        
        <!-- Header Section -->
        <div class="text-center space-y-4 animate-fade-in">
            <div class="inline-flex items-center space-x-3 bg-white/80 backdrop-blur-sm rounded-full px-6 py-3 shadow-lg border border-orange-200">
                <div class="w-3 h-3 bg-orange-500 rounded-full animate-pulse"></div>
                <span class="text-sm font-medium text-orange-700 uppercase tracking-wide">Image Management</span>
            </div>
            
            <h1 class="text-5xl sm:text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-orange-600 via-orange-500 to-red-500 leading-tight">
                Manage Images
            </h1>
            
            <div class="flex items-center justify-center space-x-2 text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                <span class="text-lg">Variant: <span class="font-semibold text-orange-600"><?= htmlspecialchars($variant_name) ?></span></span>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="glass-effect rounded-2xl shadow-2xl p-8 animate-slide-up border border-white/20">
            <div class="flex items-center space-x-3 mb-6">
                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Add New Image</h2>
            </div>

            <form action="managecrud/upload.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="variant_id" value="<?= $variant_id ?>">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 text-sm font-semibold text-gray-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Image File</span>
                        </label>
                        <div class="relative">
                            <input type="file" name="image" accept="image/*" required 
                                   class="w-full border-2 border-dashed border-gray-300 rounded-xl px-4 py-6 text-center cursor-pointer hover:border-orange-400 hover:bg-orange-50 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 text-sm font-semibold text-gray-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                            </svg>
                            <span>Description</span>
                        </label>
                        <textarea name="description" required rows="4"
                                  class="w-full border-2 border-gray-300 rounded-xl p-4 focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-300 resize-none"
                                  placeholder="Enter a detailed description of the image..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" 
                            class="group relative px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <span class="flex items-center space-x-2">
                            <svg class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <span>Upload Image</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Images Grid -->
        <div class="space-y-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                <h2 class="text-3xl font-bold text-gray-800 flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span>Image Gallery</span>
                </h2>
                
                <div class="flex items-center space-x-4">
                    <!-- Search Bar -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search images by description..." 
                               class="block w-full pl-10 pr-12 py-3 border-2 border-gray-300 rounded-xl bg-white/80 backdrop-blur-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 text-sm shadow-lg hover:shadow-xl"
                               style="min-width: 300px;">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" 
                                    id="clearSearch" 
                                    class="text-gray-400 hover:text-gray-600 transition-colors duration-200 hidden">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Results Counter -->
                    <div class="flex items-center space-x-2">
                        <div id="resultsCounter" class="text-sm text-gray-500 bg-gray-100 px-3 py-2 rounded-full shadow-sm">
                            <span id="visibleCount"><?= mysqli_num_rows($images) ?></span> of <span id="totalCount"><?= mysqli_num_rows($images) ?></span> image(s)
                        </div>
                        <button id="resetFilters" 
                                class="hidden text-sm text-blue-600 hover:text-blue-800 font-medium px-3 py-2 rounded-full hover:bg-blue-50 transition-all duration-200">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- No Results Message -->
            <div id="noResults" class="hidden text-center py-12">
                <div class="max-w-md mx-auto">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">No images found</h3>
                    <p class="text-gray-500 text-sm">Try adjusting your search terms or clear the search to see all images.</p>
                </div>
            </div>

            <div id="imageGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php $index = 0; while ($row = mysqli_fetch_assoc($images)) : ?>
                    <div class="image-card group glass-effect rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-all duration-500 animate-scale-in border border-white/20 hover:border-orange-200" 
                         style="animation-delay: <?= $index * 0.1 ?>s"
                         data-description="<?= htmlspecialchars(strtolower($row['description'])) ?>"
                         data-id="<?= $row['id'] ?>">
                        
                        <!-- Image Container -->
                        <div class="relative mb-6 overflow-hidden rounded-xl bg-gradient-to-br from-gray-100 to-gray-200">
                            <img src="data:image/jpeg;base64,<?= base64_encode($row['image_data']) ?>" 
                                 class="w-full h-48 object-contain transition-transform duration-500 group-hover:scale-110" 
                                 alt="Product Image">
                            <div class="absolute inset-0 bg-black opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        </div>

                        <!-- Content -->
                        <div class="space-y-4">
                            <!-- Update Form -->
                            <form action="managecrud/update.php" method="POST" class="space-y-3">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                
                                <div class="space-y-2">
                                    <label class="flex items-center space-x-2 text-sm font-semibold text-gray-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        <span>Description</span>
                                    </label>
                                    <textarea name="description" rows="3" required
                                              class="w-full border-2 border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 resize-none"><?= htmlspecialchars($row['description']) ?></textarea>
                                </div>
                                
                                <button type="submit" 
                                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg hover:from-blue-700 hover:to-blue-800 transform hover:scale-105 transition-all duration-300 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <span class="flex items-center justify-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        <span>Update Description</span>
                                    </span>
                                </button>
                            </form>

                            <!-- Delete Form -->
                            <form action="managecrud/delete.php" method="POST" 
                                  onsubmit="return confirm('Are you sure you want to delete this image? This action cannot be undone.')" 
                                  class="pt-2 border-t border-gray-200">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" 
                                        class="group w-full text-red-600 hover:text-white hover:bg-red-600 font-medium py-2 px-4 rounded-lg border-2 border-red-200 hover:border-red-600 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                    <span class="flex items-center justify-center space-x-2">
                                        <svg class="w-4 h-4 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        <span>Delete Image</span>
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php $index++; endwhile; ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex justify-center pt-8">
            <a href="variant_selected.php" 
               class="group inline-flex items-center space-x-3 bg-white/80 backdrop-blur-sm text-gray-700 font-semibold py-3 px-6 rounded-full shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 border border-gray-200 hover:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span>Back to Variant List</span>
            </a>
        </div>
    </div>

    <script>
        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearSearch = document.getElementById('clearSearch');
            const resetFilters = document.getElementById('resetFilters');
            const imageCards = document.querySelectorAll('.image-card');
            const noResults = document.getElementById('noResults');
            const imageGrid = document.getElementById('imageGrid');
            const visibleCount = document.getElementById('visibleCount');
            const totalCount = document.getElementById('totalCount');
            
            let searchTimeout;

            // Search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                let visibleCards = 0;

                imageCards.forEach((card, index) => {
                    const description = card.getAttribute('data-description');
                    const isVisible = description.includes(searchTerm);
                    
                    if (isVisible) {
                        card.style.display = 'block';
                        card.style.animationDelay = (visibleCards * 0.1) + 's';
                        card.classList.add('animate-scale-in');
                        visibleCards++;
                    } else {
                        card.style.display = 'none';
                        card.classList.remove('animate-scale-in');
                    }
                });

                // Update counter
                visibleCount.textContent = visibleCards;
                
                // Show/hide no results message
                if (visibleCards === 0 && searchTerm !== '') {
                    noResults.classList.remove('hidden');
                    imageGrid.classList.add('hidden');
                } else {
                    noResults.classList.add('hidden');
                    imageGrid.classList.remove('hidden');
                }

                // Show/hide clear button and reset filters
                if (searchTerm !== '') {
                    clearSearch.classList.remove('hidden');
                    resetFilters.classList.remove('hidden');
                } else {
                    clearSearch.classList.add('hidden');
                    resetFilters.classList.add('hidden');
                }
            }

            // Real-time search with debouncing
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 300);
            });

            // Clear search
            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                performSearch();
                searchInput.focus();
            });

            // Reset filters
            resetFilters.addEventListener('click', function() {
                searchInput.value = '';
                performSearch();
            });

            // Enhanced search with Enter key
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

            // File input enhancement
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name;
                    if (fileName) {
                        e.target.style.color = '#059669';
                        e.target.style.borderColor = '#059669';
                    }
                });
            }

            // Form submission loading state
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.disabled = true;
                        const originalContent = submitBtn.innerHTML;
                        submitBtn.innerHTML = `
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        `;
                        
                        // Reset button after 3 seconds if form doesn't submit
                        setTimeout(() => {
                            if (submitBtn.disabled) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalContent;
                            }
                        }, 3000);
                    }
                });
            });

            // Add search shortcut (Ctrl/Cmd + K)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
        });
    </script>

</body>
</html>