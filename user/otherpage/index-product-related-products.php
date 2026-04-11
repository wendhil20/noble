<!-- Mobile Trigger Button (Bottom Right) -->
<button id="relatedProductsTrigger"
    class="lg:hidden fixed bottom-20 right-4 z-80 bg-black text-white px-4 py-2 text-sm rounded-full shadow-md hover:shadow-lg transition-all active:scale-95 flex items-center gap-1">
    <i class="fas fa-th-large text-sm"></i>
    <span>Related (<?= $related_products->num_rows ?>)</span>
</button>

<!-- Overlay for mobile sidebar -->
<div id="relatedOverlay"
    class="fixed inset-0 bg-black bg-opacity-50 z-110 hidden lg:hidden transition-opacity duration-300"></div>

<!-- Related Products Section - Bottom Sheet on Mobile, Hidden on Desktop -->
<section id="relatedProductsContainer" class="fixed bottom-0 left-0 right-0
       transform translate-y-full
       transition-transform duration-300 ease-out
       z-111 bg-white 
       shadow-2xl rounded-t-3xl 
       max-h-[80vh] overflow-hidden flex flex-col
       lg:hidden">

    <!-- Header -->
    <div class="sticky top-0 bg-black text-white px-4 py-3 flex items-center justify-between z-20 shadow-md"
        style="font-family: 'Montserrat', sans-serif;">
        <div>
            <h2 class="text-base">Related Products</h2>
            <p class="text-xs text-white">Similar items you may like</p>
        </div>
        <button id="closeRelatedProducts" class="text-white hover:bg-white/20 p-2 rounded-full transition-colors">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- Products Grid (Mobile) -->
    <div class="overflow-y-auto flex-1 p-3 bg-gray-50">
        <div class="grid grid-cols-2 gap-3">
            <?php
            $related_products->data_seek(0);
            while ($row = $related_products->fetch_assoc()):
                // 🔥 USE SMART PRICE DISPLAY FUNCTION (same as Document 4)
                $priceData = calculateSmartPriceDisplay($row);
                $discount = (float) ($row['discount'] ?? 0);

                // Get total sold count
                $total_sold = (int) ($row['total_sold'] ?? 0);
                $view_count = (int) ($row['view_count'] ?? 0);
                ?>
                <!-- Buong card ay clickable na -->
                <a href="index-product_view-page-4-AA.php?id=<?= $row['id'] ?>"
                    class="group block bg-white hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 overflow-hidden hover:border-orange-300 cursor-pointer rounded-lg">

                    <!-- Product Image -->
                    <div class="relative overflow-hidden bg-gray-50" style="height: 140px;">
                        <?php if ($row['main_image']): ?>
                            <img src="../../<?= $row['main_image'] ?>" loading="lazy"
                                class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-300"
                                alt="<?= htmlspecialchars($row['product_name']) ?>">
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-xs">No Image</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Product Information -->
                    <div class="p-2.5">
                        <h3 class=" text-xs mb-1.5 line-clamp-2 leading-tight font-semibold"
                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                            <?= htmlspecialchars($row['product_name']) ?>
                        </h3>

                        <div class="mb-2" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                            <p class=" text-xs line-clamp-1 mb-1">
                                <?= htmlspecialchars($row['description']) ?>
                            </p>
                            <?php if (!empty($row['descrip6'])): ?>
                                <p class=" text-xs line-clamp-1">
                                    • <?= htmlspecialchars($row['descrip6']) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- 🔥 SMART PRICE DISPLAY (exactly like Document 4) -->
                        <div class="flex items-baseline gap-1 flex-wrap mb-2"
                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                            <?php if ($discount > 0): ?>
                                <p class="text-[11px] font-bold "><?= $priceData['display_price'] ?></p>
                                <span
                                    class="text-[8px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">-<?= number_format($discount, 0) ?>%</span>
                            <?php else: ?>
                                <p class="text-[11px] font-bold"><?= $priceData['display_price'] ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- View count + Sold count (styled like Document 4) -->
                        <div class="flex items-center gap-2 text-[9px] mb-2"
                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                            <?php if ($view_count > 0): ?>
                                <div class="flex items-center gap-1 bg-blue-50 px-2 py-1 rounded">
                                    viewing
                                    <span class="font-medium"><?= formatViewCount($view_count) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($total_sold > 0): ?>
                                <div class="flex items-center gap-1  px-2 py-1 rounded">
                                    sold
                                    <span class="font-medium"><?= number_format($total_sold) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- DESKTOP RELATED PRODUCTS CAROUSEL - Shows above product specifications -->
<section class="hidden lg:block mt-8 px-4 lg:px-0 max-w-7xl mx-auto">
    <div class="bg-white rounded-xl overflow-hidden shadow-sm">

        <!-- Header -->
        <div class="px-6 py-6 border-b border-gray-200">
            <div class="flex items-center justify-between"
                style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                <div>
                    <h2 class="text-2xl font-bold mb-1">Related Products</h2>
                    <p class="text-sm ">Similar items you might like</p>
                </div>
            </div>
        </div>

        <!-- Carousel Container -->
        <div class="p-6 bg-gray-50">
            <div class="relative">
                <!-- Swiper Container -->
                <div class="swiper relatedProductsSwiper">
                    <div class="swiper-wrapper">
                        <?php
                        $related_products->data_seek(0);
                        while ($row = $related_products->fetch_assoc()):
                            // 🔥 USE SMART PRICE DISPLAY FUNCTION (same as Document 4)
                            $priceData = calculateSmartPriceDisplay($row);
                            $discount = (float) ($row['discount'] ?? 0);

                            // Get total sold count
                            $total_sold = (int) ($row['total_sold'] ?? 0);
                            $view_count = (int) ($row['view_count'] ?? 0);
                            ?>
                            <div class="swiper-slide">
                                <!-- Buong card ay clickable na -->
                                <a href="index-product_view-page-4-AA.php?id=<?= $row['id'] ?>"
                                    class="group block bg-white rounded-lg overflow-hidden hover:shadow-xl transition-all duration-300 h-full flex-col cursor-pointer">

                                    <!-- Product Image -->
                                    <div class="relative overflow-hidden bg-gray-100" style="height: 200px;">
                                        <?php if ($row['main_image']): ?>
                                            <img src="../../<?= $row['main_image'] ?>" loading="lazy"
                                                class="w-full h-full object-contain p-4 group-hover:scale-110 transition-transform duration-300"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>">
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                                <i class="fas fa-image text-3xl mb-2"></i>
                                                <span class="text-sm">No Image</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Info -->
                                    <div class="p-4 flex-1 flex flex-col">
                                        <!-- Product Name -->
                                        <h3 class=" font-semibold text-[15px] mb-2 line-clamp-2 leading-tight"
                                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>

                                        <!-- Product Description -->
                                        <div class="mb-3 flex-1">
                                            <p class=" text-xs line-clamp-2 mb-1 text-[13px]"
                                                style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </p>
                                            <?php if (!empty($row['descrip6'])): ?>
                                                <p class=" text-xs line-clamp-1">
                                                    <?= htmlspecialchars($row['descrip6']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- 🔥 SMART PRICE DISPLAY (exactly like Document 4) -->
                                        <div class="flex items-baseline gap-1 flex-wrap mb-3"
                                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                            <?php if ($discount > 0): ?>
                                                <p class="text-[13px] font-bold "><?= $priceData['display_price'] ?></p>
                                                <span
                                                    class="text-[8px] font-semibold text-red-600 bg-red-50 px-1 py-0.5 rounded">-<?= number_format($discount, 0) ?>%</span>
                                            <?php else: ?>
                                                <p class="text-[13px] font-bold "><?= $priceData['display_price'] ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- View count + Sold count (styled like Document 4) -->
                                        <div class="flex items-center gap-2 text-[9px] text-gray-500 mb-3"
                                            style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                                            <?php if ($view_count > 0): ?>
                                                <div class="flex items-center gap-1 bg-blue-50 px-2 py-1 rounded">
                                                    viewing
                                                    <span class="font-medium"><?= formatViewCount($view_count) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($total_sold > 0): ?>
                                                <div class="flex items-center gap-1 bg-green-50 px-2 py-1 rounded">
                                                    sold
                                                    <span class="font-medium"><?= number_format($total_sold) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <button
                    class="relatedProducts-prev absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 z-10 w-10 h-10 bg-orange-500 hover:bg-orange-600 text-white rounded-full flex items-center justify-center transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-chevron-left text-sm"></i>
                </button>

                <button
                    class="relatedProducts-next absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 z-10 w-10 h-10 bg-orange-500 hover:bg-orange-600 text-white rounded-full flex items-center justify-center transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-chevron-right text-sm"></i>
                </button>
            </div>
        </div>
    </div>
</section>

<style>
    /* Mobile Sidebar Styles */
    #relatedProductsContainer.sidebar-open {
        transform: translateY(0);
    }

    #relatedProductsContainer {
        -webkit-overflow-scrolling: touch;
    }

    #relatedProductsContainer::-webkit-scrollbar {
        width: 6px;
    }

    #relatedProductsContainer::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    #relatedProductsContainer::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 3px;
    }

    #relatedProductsContainer::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }

    body.related-sidebar-open {
        overflow: hidden;
    }

    /* Desktop Carousel Styles */
    .relatedProductsSwiper {
        overflow: visible;
    }

    .relatedProductsSwiper .swiper-slide {
        height: auto;
    }

    /* Pagination Dots */
    .swiper-pagination-bullets.swiper-pagination-horizontal {
        bottom: 0;
    }

    .swiper-pagination-bullet {
        background-color: #d1d5db;
        opacity: 1;
    }

    .swiper-pagination-bullet-active {
        background-color: #f97316;
    }

    /* Ensure carousel shows properly */
    @media (min-width: 1024px) {
        .relatedProductsSwiper {
            padding: 0;
        }

        .relatedProductsSwiper .swiper-wrapper {
            gap: 20px;
        }
    }
</style>

<script>
    // Mobile Sidebar Controls
    document.addEventListener('DOMContentLoaded', function () {
        const mobileTrigger = document.getElementById('relatedProductsTrigger');
        const closeBtn = document.getElementById('closeRelatedProducts');
        const overlay = document.getElementById('relatedOverlay');
        const container = document.getElementById('relatedProductsContainer');

        function openSidebar() {
            container.classList.add('sidebar-open');
            overlay.classList.remove('hidden');
            document.body.classList.add('related-sidebar-open');

            setTimeout(() => {
                overlay.style.opacity = '1';
            }, 10);
        }

        function closeSidebar() {
            container.classList.remove('sidebar-open');
            overlay.style.opacity = '0';
            document.body.classList.remove('related-sidebar-open');

            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
        }

        if (mobileTrigger) {
            mobileTrigger.addEventListener('click', openSidebar);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Close on swipe down (mobile only)
        let touchStartY = 0;
        let touchEndY = 0;

        if (container) {
            container.addEventListener('touchstart', (e) => {
                touchStartY = e.changedTouches[0].screenY;
            }, {
                passive: true
            });

            container.addEventListener('touchend', (e) => {
                touchEndY = e.changedTouches[0].screenY;
                const scrollTop = container.querySelector('.overflow-y-auto')?.scrollTop || 0;
                if (scrollTop === 0 && touchEndY > touchStartY + 50) {
                    closeSidebar();
                }
            }, {
                passive: true
            });
        }

        // Close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && container.classList.contains('sidebar-open')) {
                closeSidebar();
            }
        });

        // Desktop Carousel (Swiper)
        if (typeof Swiper !== 'undefined') {
            const relatedSwiper = new Swiper('.relatedProductsSwiper', {
                slidesPerView: 'auto',
                spaceBetween: 20,
                loop: false,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                    type: 'bullets',
                },
                navigation: {
                    nextEl: '.relatedProducts-next',
                    prevEl: '.relatedProducts-prev',
                },
                keyboard: {
                    enabled: true,
                },
                grabCursor: true,
                breakpoints: {
                    640: {
                        slidesPerView: 2,
                        spaceBetween: 15,
                    },
                    1024: {
                        slidesPerView: 4,
                        spaceBetween: 20,
                    },
                    1280: {
                        slidesPerView: 4,
                        spaceBetween: 20,
                    },
                },
            });

            // Update button disabled state
            const updateButtonState = () => {
                const prevBtn = document.querySelector('.relatedProducts-prev');
                const nextBtn = document.querySelector('.relatedProducts-next');

                if (prevBtn) {
                    prevBtn.style.opacity = relatedSwiper.isBeginning ? '0.5' : '1';
                    prevBtn.style.pointerEvents = relatedSwiper.isBeginning ? 'none' : 'auto';
                }
                if (nextBtn) {
                    nextBtn.style.opacity = relatedSwiper.isEnd ? '0.5' : '1';
                    nextBtn.style.pointerEvents = relatedSwiper.isEnd ? 'none' : 'auto';
                }
            };

            relatedSwiper.on('slideChange', updateButtonState);
            updateButtonState();
        }
    });
</script>