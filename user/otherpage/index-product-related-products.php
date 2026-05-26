<?php
$related_count = $related_products->num_rows;
if ($related_count === 0)
    return;
?>


<!-- ===== MOBILE BOTTOM SHEET ===== -->
<section id="relatedSheet" class="fixed bottom-0 left-0 right-0 z-111 lg:hidden
         bg-white rounded-t-2xl shadow-2xl
         transform translate-y-full transition-transform duration-300 ease-out
         max-h-[80vh] flex flex-col overflow-hidden">

    <!-- Sheet Header -->
    <div class="bg-black text-white px-4 py-3 flex items-center justify-between shrink-0">
        <div>
            <h2 class="text-sm font-semibold">Related Products</h2>
            <p class="text-[11px] text-white/70">Similar items you may like</p>
        </div>
        <button id="closeRelated"
            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>

    <!-- Sheet Product Grid -->
    <div class="overflow-y-auto flex-1 p-3 bg-gray-50">
        <div class="grid grid-cols-2 gap-3">
            <?php
            $related_products->data_seek(0);
            while ($row = $related_products->fetch_assoc()):
                $priceData = calculateSmartPriceDisplay($row);
                $discount = (float) ($row['discount'] ?? 0);
                $total_sold = (int) ($row['total_sold'] ?? 0);
                $view_count = (int) ($row['view_count'] ?? 0);
                ?>
                <a href="<?= BASE_URL ?>/productview?id=<?= $row['id'] ?>" class="group block bg-white rounded-xl overflow-hidden
                 hover:shadow-md hover:-translate-y-0.5
                 transition-all duration-200 border border-gray-100 hover:border-orange-200">

                    <!-- Image -->
                    <div class="relative bg-gray-50 overflow-hidden" style="height:130px;">
                        <?php if ($row['main_image']): ?>
                            <img src="<?= BASE_URL ?>/<?= $row['main_image'] ?>" loading="lazy"
                                class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-300"
                                alt="<?= htmlspecialchars($row['product_name']) ?>">
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-300">
                                <i class="fas fa-image text-2xl mb-1"></i>
                                <span class="text-[10px]">No Image</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($discount > 0): ?>
                            <span class="absolute top-1.5 left-1.5 bg-red-500 text-white text-[9px] font-bold
                           px-1.5 py-0.5 rounded-full">
                                -<?= round($discount) ?>%
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="p-2.5">
                        <h3 class="text-[11px] font-semibold line-clamp-2 leading-snug mb-1 ">
                            <?= htmlspecialchars($row['product_name']) ?>
                        </h3>

                        <?php if (!empty($row['description'])): ?>
                            <p class="text-[10px] text-gray-400 line-clamp-1 mb-1">
                                <?= htmlspecialchars($row['description']) ?>
                            </p>
                        <?php endif; ?>

                        <!-- Price -->
                        <p class="text-[12px] font-bold mb-1.5">
                            <?= $priceData['display_price'] ?>
                        </p>

                        <!-- Stats -->
                        <div class="flex flex-wrap gap-1">
                            <?php if ($view_count > 0): ?>
                                <span class="text-[9px] bg-blue-50 text-blue-500 px-1.5 py-0.5 rounded">
                                    <?= formatViewCount($view_count) ?> views
                                </span>
                            <?php endif; ?>
                            <?php if ($total_sold > 0): ?>
                                <span class="text-[9px] bg-green-50 text-green-500 px-1.5 py-0.5 rounded">
                                    <?= number_format($total_sold) ?> sold
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>


<!-- ===== DESKTOP CAROUSEL ===== -->
<section class="block mt-8 px-4 max-w-7xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <!-- Header -->
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold" style="font-family: 'Montserrat', sans-serif; color: #2f1200">
                    Related Products
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    Similar items you might like
                </p>
            </div>
            <span class="bg-orange-50 text-orange-500 text-xs font-semibold px-3 py-1 rounded-full">
                <?= $related_count ?> items
            </span>
        </div>

        <!-- Swiper -->
        <div class="p-6 bg-gray-50">
            <div class="relative">
                <div class="swiper relatedProductsSwiper overflow-visible">
                    <div class="swiper-wrapper">
                        <?php
                        $related_products->data_seek(0);
                        while ($row = $related_products->fetch_assoc()):
                            $priceData = calculateSmartPriceDisplay($row);
                            $discount = (float) ($row['discount'] ?? 0);
                            $total_sold = (int) ($row['total_sold'] ?? 0);
                            $view_count = (int) ($row['view_count'] ?? 0);
                            ?>
                            <div class="swiper-slide h-auto">
                                <a href="<?= BASE_URL ?>/productview?id=<?= $row['id'] ?>" class="group flex flex-col h-full bg-white rounded-xl border border-gray-100
                         hover:border-orange-200 hover:shadow-lg
                         transition-all duration-200 overflow-hidden">

                                    <!-- Image -->
                                    <div class="relative bg-gray-50 overflow-hidden shrink-0" style="height:180px;">
                                        <?php if ($row['main_image']): ?>
                                            <img src="<?= BASE_URL ?>/<?= $row['main_image'] ?>" loading="lazy"
                                                class="w-full h-full object-contain p-3 group-hover:scale-105 transition-transform duration-300"
                                                alt="<?= htmlspecialchars($row['product_name']) ?>">
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center h-full text-gray-300">
                                                <i class="fas fa-image text-3xl mb-2"></i>
                                                <span class="text-xs">No Image</span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($discount > 0): ?>
                                            <span class="absolute top-2 left-2 bg-red-500 text-white text-[9px] font-bold
                                   px-1.5 py-0.5 rounded-full shadow">
                                                -<?= round($discount) ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div class="p-4 flex flex-col flex-1">
                                        <h3 class="text-sm font-semibold line-clamp-2 leading-snug mb-2 flex-1 uppercase">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </h3>

                                        <?php if (!empty($row['description'])): ?>
                                            <p class="text-[11px] text-gray-400 line-clamp-2 mb-3">
                                                <?= htmlspecialchars($row['description']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Price -->
                                        <p class="text-sm font-bold mb-2">
                                            <?= $priceData['display_price'] ?>
                                        </p>

                                        <!-- Stats -->
                                        <div class="flex flex-wrap gap-1.5 mt-auto">
                                            <?php if ($view_count > 0): ?>
                                                <span class="text-[9px] bg-blue-50 text-blue-500 px-2 py-0.5 rounded-full">
                                                    <?= formatViewCount($view_count) ?> views
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($total_sold > 0): ?>
                                                <span class="text-[9px] bg-green-50 text-green-600 px-2 py-0.5 rounded-full">
                                                    <?= number_format($total_sold) ?> sold
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Nav Buttons -->
                <button class="relatedProducts-prev absolute left-0 top-1/2 -translate-y-1/2 -translate-x-5 z-10
                       w-10 h-10 bg-orange-500 hover:bg-orange-600 text-white rounded-full
                       flex items-center justify-center shadow-md transition-all">
                    <i class="fas fa-chevron-left text-sm"></i>
                </button>
                <button class="relatedProducts-next absolute right-0 top-1/2 -translate-y-1/2 translate-x-5 z-10
                       w-10 h-10 bg-orange-500 hover:bg-orange-600 text-white rounded-full
                       flex items-center justify-center shadow-md transition-all">
                    <i class="fas fa-chevron-right text-sm"></i>
                </button>
            </div>
        </div>

    </div>
</section>


<style>
    /* Swiper slide equal height */
    .relatedProductsSwiper .swiper-slide {
        height: auto;
    }

    /* Pagination dots */
    .swiper-pagination-bullet {
        background: #d1d5db;
        opacity: 1;
    }

    .swiper-pagination-bullet-active {
        background: #f97316;
    }
</style>


<script>
 document.addEventListener('DOMContentLoaded', function () {

    // ── Swiper ──
    if (typeof Swiper !== 'undefined') {
        const swiper = new Swiper('.relatedProductsSwiper', {
            slidesPerView: 1,
            spaceBetween: 16,
            grabCursor: true,
            navigation: {
                nextEl: '.relatedProducts-next',
                prevEl: '.relatedProducts-prev',
            },
            breakpoints: {
                640: { slidesPerView: 2, spaceBetween: 16 },
                1024: { slidesPerView: 4, spaceBetween: 20 },
            },
        });

        function updateNav() {
            const prev = document.querySelector('.relatedProducts-prev');
            const next = document.querySelector('.relatedProducts-next');
            if (prev) prev.style.opacity = swiper.isBeginning ? '0.4' : '1';
            if (next) next.style.opacity = swiper.isEnd ? '0.4' : '1';
        }
        swiper.on('slideChange', updateNav);
        updateNav();
    }

});
</script>