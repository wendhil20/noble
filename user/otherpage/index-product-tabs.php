<?php
if (empty($product_specs)) return;
?>

<section class="mt-4 lg:mt-8 px-3 lg:px-0 max-w-7xl mx-auto">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    <!-- ===== TAB NAVIGATION ===== -->
    <div class="border-b border-gray-100 bg-gray-50">
      <div class="flex overflow-x-auto scrollbar-hide">

        <button onclick="switchTab('specifications')" id="tab-specifications"
          class="product-tab flex-1 min-w-[100px] px-3 lg:px-6 py-3 lg:py-4 text-[11px] lg:text-sm font-semibold text-gray-500
                 hover:text-orange-500 hover:bg-white border-b-2 border-transparent
                 transition-all duration-200 whitespace-nowrap"
          style="font-family: 'Montserrat', sans-serif;">
          <i class="fas fa-list-alt mr-1.5"></i>
          Specifications
        </button>

        <button onclick="switchTab('reviews')" id="tab-reviews"
          class="product-tab flex-1 min-w-[100px] px-3 lg:px-6 py-3 lg:py-4 text-[11px] lg:text-sm font-semibold text-gray-500
                 hover:text-orange-500 hover:bg-white border-b-2 border-transparent
                 transition-all duration-200 whitespace-nowrap"
          style="font-family: 'Montserrat', sans-serif;">
          <i class="fas fa-star mr-1.5"></i>
          Reviews
          <?php if ($total_raters > 0): ?>
            <span class="ml-1 bg-orange-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
              <?= $total_raters ?>
            </span>
          <?php endif; ?>
        </button>

        <button onclick="switchTab('productinfo')" id="tab-productinfo"
          class="product-tab flex-1 min-w-[100px] px-3 lg:px-6 py-3 lg:py-4 text-[11px] lg:text-sm font-semibold text-gray-500
                 hover:text-orange-500 hover:bg-white border-b-2 border-transparent
                 transition-all duration-200 whitespace-nowrap"
          style="font-family: 'Montserrat', sans-serif;">
          <i class="fas fa-images mr-1.5"></i>
          Gallery
        </button>

      </div>
    </div>

    <!-- ===== TAB CONTENTS ===== -->
    <div class="p-3 lg:p-8">

      <!-- ── SPECIFICATIONS ── -->
      <div id="content-specifications" class="tab-content">

        <?php
        $has_specs = !empty($product_specs['descrip1'])
                  || !empty($product_specs['descrip6'])
                  || !empty($product_specs['descrip7']);
        ?>

        <?php if ($has_specs): ?>
          <div class="space-y-4 lg:space-y-6">

            <?php if (!empty($product_specs['descrip1'])): ?>
              <div>
                <h3 class="text-xs lg:text-sm font-bold text-gray-400 uppercase tracking-widest mb-3"
                    style="font-family: 'Montserrat', sans-serif;">
                  Product Details
                </h3>
                <div class="divide-y divide-gray-100">
                  <?php
                  foreach (explode("\n", $product_specs['descrip1']) as $line):
                    $line = trim($line);
                    if (empty($line)) continue;
                  ?>
                    <p class="py-2 lg:py-2.5 text-xs lg:text-sm text-gray-700"
                       style="font-family: 'Montserrat', sans-serif;">
                      <?= htmlspecialchars($line) ?>
                    </p>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($product_specs['descrip6'])): ?>
              <div class="bg-orange-50 border border-orange-100 rounded-xl p-3 lg:p-4">
                <p class="text-xs lg:text-sm text-gray-700"
                   style="font-family: 'Montserrat', sans-serif;">
                  <?= htmlspecialchars($product_specs['descrip6']) ?>
                </p>
              </div>
            <?php endif; ?>

            <?php if (!empty($product_specs['descrip7'])): ?>
              <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 lg:p-4">
                <p class="text-xs lg:text-sm text-gray-700"
                   style="font-family: 'Montserrat', sans-serif;">
                  <?= htmlspecialchars($product_specs['descrip7']) ?>
                </p>
              </div>
            <?php endif; ?>

          </div>

        <?php else: ?>
          <div class="text-center py-12 lg:py-16">
            <div class="w-14 h-14 lg:w-16 lg:h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-inbox text-xl lg:text-2xl text-gray-300"></i>
            </div>
            <p class="text-gray-400 text-xs lg:text-sm" style="font-family: 'Montserrat', sans-serif;">
              No specifications available yet.
            </p>
          </div>
        <?php endif; ?>

      </div>


      <!-- ── REVIEWS ── -->
      <div id="content-reviews" class="tab-content hidden">

        <!-- Rating Summary Card -->
        <div class="bg-orange-50 border border-orange-100 rounded-xl p-4 lg:p-6 mb-4 lg:mb-6">
          <div class="flex flex-col sm:flex-row sm:items-center gap-4 lg:gap-6">

            <!-- Big Number -->
            <div class="text-center shrink-0">
              <div class="text-5xl lg:text-6xl font-black text-orange-500"
                   style="font-family: 'Montserrat', sans-serif;">
                <?= $total_raters > 0 ? $avg_rating : '0.0' ?>
              </div>
              <div class="flex justify-center gap-0.5 mt-1 text-yellow-400 text-base lg:text-lg">
                <?php
                if ($total_raters > 0) {
                  $full  = floor($avg_rating);
                  $half  = ($avg_rating - $full >= 0.5) ? 1 : 0;
                  $empty = 5 - $full - $half;
                  for ($i = 0; $i < $full;  $i++) echo '<i class="fas fa-star"></i>';
                  if ($half)                       echo '<i class="fas fa-star-half-alt"></i>';
                  for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star text-gray-300"></i>';
                } else {
                  for ($i = 0; $i < 5; $i++)      echo '<i class="far fa-star text-gray-200"></i>';
                }
                ?>
              </div>
              <p class="text-xs text-gray-500 mt-1" style="font-family: 'Montserrat', sans-serif;">
                <?= $total_raters ?> review<?= $total_raters == 1 ? '' : 's' ?>
              </p>
            </div>

            <!-- Rating Bars -->
            <?php if ($total_raters > 0): ?>
              <div class="flex-1 space-y-1.5 lg:space-y-2">
                <?php
                $rating_dist_query = $conn->prepare("
                  SELECT rating, COUNT(*) as count
                  FROM product_ratings
                  WHERE product_id = ?
                  GROUP BY rating ORDER BY rating DESC
                ");
                $rating_dist_query->bind_param("i", $product_id);
                $rating_dist_query->execute();
                $rating_dist_result = $rating_dist_query->get_result();
                $rating_counts = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
                while ($dist = $rating_dist_result->fetch_assoc()) {
                  $rating_counts[$dist['rating']] = $dist['count'];
                }
                $rating_dist_query->close();

                foreach ([5, 4, 3, 2, 1] as $star):
                  $count = $rating_counts[$star];
                  $pct   = $total_raters > 0 ? ($count / $total_raters) * 100 : 0;
                ?>
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 w-4 text-right"><?= $star ?></span>
                    <i class="fas fa-star text-yellow-400 text-[10px]"></i>
                    <div class="flex-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                      <div class="bg-orange-400 h-1.5 rounded-full transition-all duration-500"
                           style="width: <?= $pct ?>%"></div>
                    </div>
                    <span class="text-xs text-gray-400 w-4"><?= $count ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Review Cards -->
        <?php if ($total_raters > 0): ?>
          <div class="space-y-2 lg:space-y-3">
            <?php
            $reviews_query = $conn->prepare("
              SELECT pr.*, u.name as user_name, u.profile_picture
              FROM product_ratings pr
              LEFT JOIN users u ON pr.user_id = u.id
              WHERE pr.product_id = ?
              ORDER BY pr.created_at DESC LIMIT 10
            ");
            $reviews_query->bind_param("i", $product_id);
            $reviews_query->execute();
            $reviews_result = $reviews_query->get_result();

            while ($review = $reviews_result->fetch_assoc()):
            ?>
              <div class="border border-gray-100 rounded-xl p-3 lg:p-4
                          hover:border-orange-200 hover:shadow-sm transition-all duration-200">
                <div class="flex items-start gap-2 lg:gap-3">

                  <!-- Avatar -->
                  <?php if (!empty($review['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($review['profile_picture']) ?>"
                         alt="<?= htmlspecialchars($review['user_name']) ?>"
                         class="w-8 h-8 lg:w-9 lg:h-9 rounded-full object-cover shrink-0">
                  <?php else: ?>
                    <div class="w-8 h-8 lg:w-9 lg:h-9 rounded-full bg-orange-500 flex items-center justify-center
                                text-white text-xs lg:text-sm font-bold shrink-0">
                      <?= strtoupper(substr($review['user_name'], 0, 1)) ?>
                    </div>
                  <?php endif; ?>

                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 mb-1">
                      <span class="text-xs lg:text-sm font-semibold text-gray-800"
                            style="font-family: 'Montserrat', sans-serif;">
                        <?= htmlspecialchars($review['user_name']) ?>
                      </span>
                      <span class="text-[10px] lg:text-[11px] text-gray-400 shrink-0">
                        <?= date('M d, Y', strtotime($review['created_at'])) ?>
                      </span>
                    </div>

                    <!-- Stars -->
                    <div class="flex gap-0.5 text-yellow-400 text-[10px] lg:text-xs mb-1.5 lg:mb-2">
                      <?php
                      $r = (int) $review['rating'];
                      for ($i = 0; $i < $r;  $i++) echo '<i class="fas fa-star"></i>';
                      for ($i = $r; $i < 5;  $i++) echo '<i class="far fa-star text-gray-200"></i>';
                      ?>
                    </div>

                    <?php if (!empty($review['comment'])): ?>
                      <p class="text-xs lg:text-sm font-semibold text-gray-700 mb-1"
                         style="font-family: 'Montserrat', sans-serif;">
                        <?= htmlspecialchars($review['comment']) ?>
                      </p>
                    <?php endif; ?>

                    <?php if (!empty($review['review'])): ?>
                      <p class="text-xs lg:text-sm text-gray-500 leading-relaxed"
                         style="font-family: 'Montserrat', sans-serif;">
                        <?= nl2br(htmlspecialchars($review['review'])) ?>
                      </p>
                    <?php endif; ?>
                  </div>

                </div>
              </div>
            <?php endwhile; ?>
            <?php $reviews_query->close(); ?>
          </div>

        <?php else: ?>
          <div class="text-center py-12 lg:py-16">
            <div class="w-14 h-14 lg:w-16 lg:h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-star text-xl lg:text-2xl text-gray-300"></i>
            </div>
            <h3 class="text-sm lg:text-base font-semibold text-gray-600 mb-1"
                style="font-family: 'Montserrat', sans-serif;">No Reviews Yet</h3>
            <p class="text-xs lg:text-sm text-gray-400"
               style="font-family: 'Montserrat', sans-serif;">
              Be the first to review this product!
            </p>
          </div>
        <?php endif; ?>

      </div>


      <!-- ── GALLERY / PRODUCT INFO ── -->
      <div id="content-productinfo" class="tab-content hidden">

        <?php if (!empty($product_images)): ?>
          <div class="mb-6 lg:mb-8">
            <h3 class="text-xs lg:text-sm font-bold text-gray-400 uppercase tracking-widest mb-3 lg:mb-4"
                style="font-family: 'Montserrat', sans-serif;">
              Product Images
              <span class="ml-2 bg-blue-100 text-blue-600 text-[10px] px-2 py-0.5 rounded-full normal-case tracking-normal">
                <?= count($product_images) ?>
              </span>
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 lg:gap-3">
              <?php foreach ($product_images as $img): ?>
                <div class="group relative aspect-square rounded-xl overflow-hidden bg-gray-100 cursor-pointer
                            shadow-sm hover:shadow-lg transition-all duration-300"
                     onclick="openImageModal('<?= htmlspecialchars($img['src']) ?>')">

                  <img src="<?= htmlspecialchars($img['src']) ?>"
                       alt="Product image <?= $img['index'] + 1 ?>"
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                       loading="lazy"
                       onerror="this.parentElement.classList.add('img-error')">

                  <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300
                              flex items-center justify-center">
                    <div class="w-7 h-7 lg:w-8 lg:h-8 bg-white/90 rounded-full flex items-center justify-center
                                opacity-0 group-hover:opacity-100 transition-opacity duration-300 shadow">
                      <i class="fas fa-search-plus text-gray-700 text-xs"></i>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        <?php else: ?>
          <div class="text-center py-10 lg:py-12 mb-4 lg:mb-6">
            <div class="w-14 h-14 lg:w-16 lg:h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-images text-xl lg:text-2xl text-gray-300"></i>
            </div>
            <p class="text-xs lg:text-sm text-gray-400"
               style="font-family: 'Montserrat', sans-serif;">
              No images uploaded yet.
            </p>
          </div>
        <?php endif; ?>

        <?php if (!empty($product_specs['descriptionpic'])): ?>
          <div>
            <h3 class="text-xs lg:text-sm font-bold text-gray-400 uppercase tracking-widest mb-3 lg:mb-4"
                style="font-family: 'Montserrat', sans-serif;">
              Detailed Description
            </h3>
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 lg:p-5">
              <p class="text-xs lg:text-sm text-gray-700 leading-relaxed whitespace-pre-line"
                 style="font-family: 'Montserrat', sans-serif;">
                <?= htmlspecialchars($product_specs['descriptionpic']) ?>
              </p>
            </div>
          </div>
        <?php endif; ?>

      </div>
      <!-- ── END TAB CONTENTS ── -->

    </div>
  </div>
</section>


<!-- ===== IMAGE MODAL ===== -->
<div id="productImageModal"
     class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-3 lg:p-4"
     onclick="closeImageModal()">
  <div class="relative max-w-4xl w-full" onclick="event.stopPropagation()">
    <button onclick="closeImageModal()"
            class="absolute -top-8 lg:-top-10 right-0 text-white/70 hover:text-white text-xl lg:text-2xl transition">
      <i class="fas fa-times"></i>
    </button>
    <img id="modalProductImage"
         class="w-full max-h-[85vh] object-contain rounded-xl" src="" alt="">
  </div>
</div>


<style>
  .product-tab.active {
    color: #f97316 !important;
    border-bottom-color: #f97316 !important;
    background-color: #fff !important;
  }

  .tab-content {
    animation: tabFade 0.25s ease-out;
  }

  @keyframes tabFade {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0);   }
  }

  .img-error {
    background: #fef2f2;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .img-error::after {
    content: '⚠';
    font-size: 1.5rem;
    color: #fca5a5;
  }

  .scrollbar-hide::-webkit-scrollbar { display: none; }
  .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

  #productImageModal.flex { display: flex !important; }

  @media (max-width: 640px) {
    .product-tab {
      min-width: 90px;
      font-size: 11px;
    }
  }
</style>


<script>
  function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.product-tab').forEach(el => el.classList.remove('active'));

    const content = document.getElementById('content-' + tabName);
    if (content) content.classList.remove('hidden');

    const tab = document.getElementById('tab-' + tabName);
    if (tab) tab.classList.add('active');

    localStorage.setItem('activeProductTab', tabName);
  }

  function openImageModal(src) {
    const modal = document.getElementById('productImageModal');
    document.getElementById('modalProductImage').src = src;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeImageModal() {
    const modal = document.getElementById('productImageModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  function shareProduct() {
    if (navigator.share) {
      navigator.share({ title: document.title, url: window.location.href })
        .catch(() => {});
    } else {
      navigator.clipboard.writeText(window.location.href)
        .then(() => alert('Link copied!'))
        .catch(() => alert('Copy manually: ' + window.location.href));
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('activeProductTab') || 'specifications';
    switchTab(saved);

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeImageModal();
    });
  });
</script>