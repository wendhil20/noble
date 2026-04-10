<?php
/**
 * index-promotion-banner-front.php
 *
 * I-include isang beses sa TUKTOK ng page (bago ang HTML output):
 *   include 'index-promotion-banner-front.php';
 *
 * Tapos gamitin kung saan mo gusto:
 *   <?php include_banner(1); ?>   <- Stage 1 (Top)
 *   <?php include_banner(3); ?>   <- Stage 3 (Middle)
 *   <?php include_banner(2); ?>   <- Stage 2 (Bottom)
 *
 * IMPORTANTE: Kung walang active banner sa isang stage,
 * ZERO HTML ang ilalabas — walang blank space, walang section tag.
 */

// ── Load all 3 active banners ONCE ───────────────────────────────────────────
if (!isset($__promo_banners_loaded)) {
    $__promo_banners_loaded = true;
    $__promo_banners = [];
    $__promo_css_injected = false;

    for ($__s = 1; $__s <= 3; $__s++) {
        $__q = $conn->prepare(
            "SELECT * FROM promotion_banners WHERE stage = ? AND status = 'active' LIMIT 1"
        );
        $__q->bind_param("i", $__s);
        $__q->execute();
        $__promo_banners[$__s] = $__q->get_result()->fetch_assoc();
        $__q->close();
    }
}

// ── Helper: inject CSS only once, only when actually needed ──────────────────
function _inject_banner_css()
{
    global $__promo_css_injected;
    if ($__promo_css_injected)
        return;
    $__promo_css_injected = true;
    echo '<style>
@keyframes shimmer-banner{0%{background-position:-200% 0}100%{background-position:200% 0}}
.promo-shimmer{position:absolute;inset:0;border-radius:.75rem;
  background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);
  background-size:200% 100%;
  animation:shimmer-banner 1.5s ease-in-out infinite;}
.promo-img{transition:opacity .3s ease-in-out;}
</style>';
}

// ── Main function ─────────────────────────────────────────────────────────────
if (!function_exists('include_banner')) {
    function include_banner($stage)
    {
        global $__promo_banners;
        $b = $__promo_banners[$stage] ?? null;

        if (!$b || empty($b['image_path']))
            return;

        _inject_banner_css();

        $img_src = '../../uploads/' . htmlspecialchars($b['image_path']);
        $has_link = !empty(trim($b['link'] ?? ''));
        $link = $has_link ? htmlspecialchars($b['link']) : '';
        $title = trim($b['title'] ?? '');
        $has_title = $title !== '';
        ?>
        <section class="px-4 sm:px-6 lg:px-8 my-3">

            <?php if ($has_title): ?>
                <div class="flex items-center gap-2 mb-2">
                    <p class="text-2xl font-bold tracking-wide flex items-center gap-2 shrink-0">
                        <span class="text-gray-900"><?= htmlspecialchars(strtoupper(explode(' ', $title)[0])) ?></span>
                        <span
                            class="text-yellow-400"><?= htmlspecialchars(strtoupper(implode(' ', array_slice(explode(' ', $title), 1)))) ?></span>
                    </p>
                    <span class="h-0.5 " style="width: 200px; background: linear-gradient(to right, #facc15, transparent);"></span>
                </div>
            <?php endif; ?>

            <?php if ($has_link): ?>
                <a href="<?= $link ?>" class="group block relative overflow-hidden rounded-xl">
                <?php else: ?>
                    <div class="group block relative overflow-hidden rounded-xl">
                    <?php endif; ?>

                    <div class="promo-shimmer"></div>

                    <img src="<?= $img_src ?>" alt="<?= $has_title ? htmlspecialchars($title) : 'Promotion Banner' ?>"
                        class="promo-img w-full object-contain opacity-0 relative z-10 rounded-xl block"
                        onload="this.style.opacity='1';this.previousElementSibling.style.display='none';"
                        onerror="this.closest('section').remove();">

                    <?php if ($has_link): ?>
                        <div
                            class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-all duration-300 rounded-xl z-30 pointer-events-none">
                        </div>
                    <?php endif; ?>

                    <?php echo $has_link ? '</a>' : '</div>'; ?>

        </section>
        <?php
    }
}
?>