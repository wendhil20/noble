<?php
$promo_query = "SELECT * FROM promotion_discount WHERE status = 'active' ORDER BY created_at DESC";
$promo_result = $conn->query($promo_query);
$promos = [];
if ($promo_result) {
    while ($row = $promo_result->fetch_assoc()) {
        $promos[] = $row;
    }
}
?>
<?php if (!empty($promos)): ?>
    <section class="py-10 px-4 bg-white">
        <div class="mx-auto p-4">

            <!-- Header -->
            <div class="flex items-center gap-2 mb-6">
                <span class="text-2xl font-bold text-gray-900 uppercase tracking-wide">NEW</span>
                <span class="text-2xl font-bold text-red-500 uppercase tracking-wide">PROMO</span>
                <div class="h-px bg-orange-400 ml-1" style="width: 60px;"></div>
            </div>

            <!-- Scroll Wrapper -->
            <div class="relative">

                <!-- Prev Button -->
                <button id="promoPrev"
                    class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 z-10
                       bg-white border border-gray-200 shadow-md hover:bg-orange-500 hover:text-white hover:border-orange-500
                       text-gray-700 rounded-full w-10 h-10 flex items-center justify-center transition-all duration-200"
                    onclick="scrollPromo('prev')" aria-label="Previous">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Horizontal Scroll Cards -->
                <div id="promoScroll" class="flex gap-5 overflow-x-auto pb-3 promo-scroll ">
                    <?php foreach ($promos as $promo): ?>
                        <?php $discount = number_format((float) $promo['discount'], 0); ?>

                        <div class="relative rounded-lg overflow-hidden shrink-0 group cursor-pointer border border-gray-200"
                            style="width: 340px; height: 280px;">

                            <!-- Background Image -->
                            <img src="../../uploads/promotion_banners/<?= htmlspecialchars($promo['image']) ?>"
                                alt="<?= htmlspecialchars($promo['title']) ?>" class="w-full h-full object-contain"
                                loading="lazy" onerror="this.src='../../uploads/placeholder.jpg'">

                            <!-- Dark gradient overlay -->
                            <div class="absolute inset-0"
                                style="background: linear-gradient(to top, rgba(0,0,0,0.88) 0%, rgba(0,0,0,0.15) 55%, transparent 100%);">
                            </div>

                            <!-- Discount Badge - top left -->
                            <div
                                class="absolute top-4 left-4 bg-white bg-opacity-60 text-red-500 text-sm font-semibold px-3 py-1.5 rounded-lg backdrop-blur-sm">
                                <?= $discount ?>% Discount
                            </div>

                            <!-- Bottom content: title + button -->
                            <div class="absolute bottom-0 left-0 right-0 p-5 flex flex-col gap-3">
                                <h3 class="text-white font-bold text-lg uppercase leading-snug drop-shadow-lg line-clamp-2">
                                    <?= htmlspecialchars($promo['title']) ?>
                                </h3>
                                <div>
                                    <a href="../otherpage/index-allproduct-page-3.php?filter=discounted&min_discount=<?= $discount ?>"
                                        class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-400 text-white text-sm font-bold px-4 py-2 rounded-full transition-all duration-200">
                                        Shop Now
                                        <i class="fa-solid fa-circle-arrow-right"></i>
                                    </a>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Next Button -->
                <button id="promoNext"
                    class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 z-10
                       bg-white border border-gray-200 shadow-md hover:bg-orange-500 hover:text-white hover:border-orange-500
                       text-gray-700 rounded-full w-10 h-10 flex items-center justify-center transition-all duration-200"
                    onclick="scrollPromo('next')" aria-label="Next">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

            </div>
        </div>
    </section>

    <style>
        .promo-scroll::-webkit-scrollbar {
            display: none;
        }

        .promo-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>

    <script>
        function scrollPromo(direction) {
            const container = document.getElementById('promoScroll');
            const scrollAmount = 365; // card width (340) + gap (25)
            if (direction === 'next') {
                container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            } else {
                container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            }

            // Auto-hide/show buttons based on scroll position
            setTimeout(() => updatePromoButtons(), 350);
        }

        function updatePromoButtons() {
            const container = document.getElementById('promoScroll');
            const prevBtn = document.getElementById('promoPrev');
            const nextBtn = document.getElementById('promoNext');

            prevBtn.style.opacity = container.scrollLeft <= 0 ? '0.3' : '1';
            prevBtn.style.pointerEvents = container.scrollLeft <= 0 ? 'none' : 'auto';

            const atEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 5;
            nextBtn.style.opacity = atEnd ? '0.3' : '1';
            nextBtn.style.pointerEvents = atEnd ? 'none' : 'auto';
        }

        // Initialize button states on load
        document.addEventListener('DOMContentLoaded', () => {
            updatePromoButtons();
            document.getElementById('promoScroll').addEventListener('scroll', updatePromoButtons);
        });
    </script>
<?php endif; ?>