<?php if (count($newProducts) > 0): ?>

<style>
  @keyframes npSlideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }
  @keyframes npFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
  }
  .np-overlay { animation: npFadeIn 0.2s ease forwards; }
  .np-card    { animation: npSlideUp 0.25s cubic-bezier(.22,1,.36,1) forwards; }
  .np-scroll::-webkit-scrollbar       { width: 3px; }
  .np-scroll::-webkit-scrollbar-track { background: transparent; }
  .np-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 99px; }
  .np-item-img { transition: transform 0.3s ease; }
  .np-item:hover .np-item-img { transform: scale(1.06); }
</style>

<div
  x-show="newProductsModal"
  x-cloak
  @click.self="newProductsModal = false"
  @keydown.escape.window="newProductsModal = false"
  class="np-overlay fixed inset-0 z-[999999] flex items-center p-4"
  style="background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); justify-content: center;">

  <!-- Modal Card -->
  <div
    @click.stop
    class="np-card bg-white w-full flex flex-col rounded-2xl overflow-hidden"
    style="max-width: 560px; max-height: 86vh; box-shadow: 0 24px 60px rgba(0,0,0,0.25);">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-4 bg-black shrink-0">
      <div class="flex items-center gap-3">
        <!-- Icon -->
        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(249,115,22,0.15); border: 1px solid rgba(249,115,22,0.25);">
          <i class="fa-solid fa-box-open text-orange-500 text-sm"></i>
        </div>
        <div>
          <h2 class="text-white text-sm font-semibold leading-tight">New Arrivals</h2>
          <p class="text-white/40 text-[11px] mt-0.5">
            <?= count($newProducts) ?> product<?= count($newProducts) > 1 ? 's' : '' ?> added recently
          </p>
        </div>
      </div>

      <!-- Close -->
      <button
        @click="newProductsModal = false"
        class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors"
        style="background: rgba(255,255,255,0.08);"
        onmouseover="this.style.background='rgba(255,255,255,0.16)'"
        onmouseout="this.style.background='rgba(255,255,255,0.08)'"
        aria-label="Close">
        <svg width="13" height="13" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Product List -->
    <div class="np-scroll flex-1 overflow-y-auto divide-y divide-gray-100">
      <?php foreach ($newProducts as $product): ?>
        <div
          class="np-item flex items-center gap-4 px-5 py-4 transition-colors hover:bg-orange-50/60 cursor-default">

          <!-- Image -->
          <div class="relative shrink-0">
            <div class="w-16 h-16 rounded-xl overflow-hidden border border-gray-100 bg-gray-50 flex items-center justify-center">
              <?php if (!empty($product['main_image'])): ?>
                <img
                  src="<?= BASE_URL ?>/<?= htmlspecialchars($product['main_image']) ?>"
                  alt="<?= htmlspecialchars($product['name']) ?>"
                  class="np-item-img w-full h-full object-contain"
                  onerror="this.parentElement.innerHTML='<svg class=\'w-7 h-7 text-gray-300\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 16M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg>'">
              <?php else: ?>
                <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 16M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              <?php endif; ?>
            </div>
            <!-- NEW badge -->
            <span class="absolute -top-1.5 -left-1.5 bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-md uppercase tracking-wide">
              NEW
            </span>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <!-- Name + Stock -->
            <div class="flex items-start justify-between gap-2 mb-1">
              <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wide truncate">
                <?= htmlspecialchars($product['name']) ?>
              </h3>
              <?php if ($product['stock_status'] === 'In Stock'): ?>
                <span class="text-[10px] text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full whitespace-nowrap shrink-0">
                  ● In Stock
                </span>
              <?php else: ?>
                <span class="text-[10px] text-red-600 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full whitespace-nowrap shrink-0">
                  ● Out of Stock
                </span>
              <?php endif; ?>
            </div>

            <!-- Meta -->
            <div class="flex items-center gap-2 mb-3">
              <span class="text-[11px] text-gray-400 flex items-center gap-1">
                <i class="fa-solid fa-tag text-[9px]"></i>
                <?= htmlspecialchars($product['codename']) ?>
              </span>
              <span class="text-gray-200 text-[10px]">•</span>
              <span class="text-[11px] text-gray-400 flex items-center gap-1">
                <i class="fa-regular fa-calendar text-[9px]"></i>
                <?= date('M j, Y', strtotime($product['created_at'])) ?>
              </span>
            </div>

            <!-- View Button -->
            <form action="<?= BASE_URL ?>/productview" method="GET">
              <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
              <button
                type="submit"
                class="text-[11px] font-semibold text-orange-500 border border-orange-500 px-3 py-1 rounded-lg transition-colors hover:bg-orange-500 hover:text-white">
                View →
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="px-5 py-3.5 bg-gray-50 border-t border-gray-100 flex gap-2.5 shrink-0">
      <button
        onclick="window.location.href='<?= BASE_URL ?>/shop'"
        class="flex-1 bg-black text-white text-[13px] font-semibold py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 transition-colors hover:bg-orange-500">
        <i class="fa-solid fa-store text-xs"></i>
        Browse All Products
      </button>
      <button
        @click="newProductsModal = false"
        class="text-[13px] font-medium text-gray-500 bg-white border border-gray-200 px-4 py-2.5 rounded-xl transition-colors hover:border-gray-400 hover:text-gray-700">
        Close
      </button>
    </div>
  </div>
</div>

<script>
  setInterval(function () {
    fetch(window.location.pathname + '?action=get_new_products_count')
      .then(r => r.headers.get('content-type')?.includes('application/json') ? r.json() : Promise.reject())
      .then(data => {
        const badge = document.querySelector('[\\@click="newProductsModal = true"] span');
        if (badge && data.count !== undefined) {
          badge.textContent = data.count;
          badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
        }
      })
      .catch(() => {});
  }, 30000);
</script>

<?php endif; ?>