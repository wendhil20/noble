<?php
// Fetch cart items specifically for sidebar
// cart-sidebar.php
$sidebar_cart_items = [];
$sidebar_total = 0;
$sidebar_user_id = $_SESSION['user_id'] ?? null;

if ($sidebar_user_id) {
    $sidebar_stmt = $conn->prepare("
    SELECT 
      c.id,
      c.quantity,
      c.price,
      c.codename,
      c.variant_name,
      c.type_name,
      c.size,
      c.color_name,
      t.type_image,
      p.product_name,
      p.main_image
    FROM user_cart_items c
    LEFT JOIN product_types t 
      ON t.product_id = c.product_id AND t.type_name = c.type_name
    LEFT JOIN products p 
      ON c.product_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.id DESC
    LIMIT 10
  ");
    $sidebar_stmt->bind_param("i", $sidebar_user_id);
    $sidebar_stmt->execute();
    $sidebar_result = $sidebar_stmt->get_result();

    while ($row = $sidebar_result->fetch_assoc()) {
        $sidebar_cart_items[] = $row;
        $sidebar_total += floatval($row['price']) * intval($row['quantity']);
    }
    $sidebar_stmt->close();
}

$sidebar_count = count($sidebar_cart_items);
?>

<!-- Cart Modal Overlay -->
<div id="cartSidebarOverlay" onclick="closeCartSidebar()">
</div>


<div id="cartSidebar" style="position:fixed; top:72px; right:16px; 
            width:380px; max-width:calc(100vw - 24px);
            max-height:calc(100vh - 88px);
            background:#fff; z-index:99999; display:flex; flex-direction:column;
            border-radius:12px;
            box-shadow:0 8px 32px rgba(0,0,0,0.18);
            visibility:hidden; opacity:0;
            transition:opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
            transform:translateX(20px);">

    <!-- Header -->
<div style="background:#111;color:#fff;padding:1rem 1.25rem;display:flex;
            align-items:center;justify-content:space-between;flex-shrink:0;
            border-radius:12px 12px 0 0;">
  <div style="display:flex;align-items:center;gap:10px;">
    <i class="fa-solid fa-cart-shopping" style="font-size:16px;"></i>
    <span style="font-size:15px;font-weight:500;">Your Cart</span>
    <!-- badge is now driven by JS -->
    <span id="cartSidebarBadge"
          style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;
                 padding:2px 8px;border-radius:999px;display:none;">0</span>
  </div>
  <button onclick="closeCartSidebar()"
          style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;
                 padding:2px 6px;border-radius:6px;line-height:1;"><i class="fa-solid fa-circle-xmark"></i></button>
</div>

<!-- Spinner (visible only during fetch) -->
<div id="cartSidebarSpinner"
     style="display:none;justify-content:center;align-items:center;
            padding:2rem;">
  <div style="width:24px;height:24px;border:3px solid #e5e7eb;
              border-top-color:#f97316;border-radius:50%;
              animation:cart-spin 0.7s linear infinite;"></div>
</div>
<style>
  @keyframes cart-spin { to { transform: rotate(360deg); } }
</style>

<!-- Scrollable body — JS writes into this -->
<div id="cartSidebarBody" style="flex:1;overflow-y:auto;padding:0;"></div>

<!-- Footer — JS shows/hides this -->
<div id="cartSidebarFooter"
     style="display:none;flex-shrink:0;border-top:1px solid #e5e7eb;
            padding:14px;background:#fafafa;border-radius:0 0 12px 12px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
    <span style="font-size:13px;color:#6b7280;">Order Total</span>
    <span id="cartSidebarTotal"
          style="font-size:18px;font-weight:700;color:#111;">₱0.00</span>
  </div>
  <div style="display:flex;flex-direction:column;gap:6px;">
    <a href="<?= BASE_URL ?>/checkout"
       style="display:block;text-align:center;background:#16a34a;color:#fff;
              padding:10px;border-radius:8px;text-decoration:none;
              font-size:13px;font-weight:600;"
       onmouseover="this.style.background='#15803d'"
       onmouseout="this.style.background='#16a34a'">
      <i class="fa-solid fa-credit-card" style="margin-right:5px;"></i>
      Proceed to Checkout
    </a>
    <a href="<?= BASE_URL ?>/cartview" onclick="closeCartSidebar()"
       style="display:block;text-align:center;background:#111;color:#fff;
              padding:8px;border-radius:8px;text-decoration:none;
              font-size:12px;font-weight:500;"
       onmouseover="this.style.background='#374151'"
       onmouseout="this.style.background='#111'">
      View Full Cart
    </a>
  </div>
</div>
</div>

<script>
    const CART_API = '<?= BASE_URL ?>/cartreal';
    const BASE = '<?= BASE_URL ?>';
    let cartPollTimer = null;

    // ─── render helpers ───────────────────────────────────────────────
    function fmt(n) {
        return '₱' + parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
    }

    function renderItems(data) {
        const body = document.getElementById('cartSidebarBody');
        const footer = document.getElementById('cartSidebarFooter');
        const badge = document.getElementById('cartSidebarBadge');
        const spinner = document.getElementById('cartSidebarSpinner');

        if (spinner) spinner.style.display = 'none';

        // badge
        if (badge) {
            badge.textContent = data.count;
            badge.style.display = data.count > 0 ? 'inline' : 'none';
        }

        if (!data.logged_in || data.count === 0) {
            body.innerHTML = `
          <div style="display:flex;flex-direction:column;align-items:center;
                      justify-content:center;padding:3rem 1.5rem;text-align:center;">
            <i class="fa-solid fa-cart-shopping"
               style="font-size:48px;color:#d1d5db;margin-bottom:12px;"></i>
            <p style="font-size:15px;color:#6b7280;margin:0 0 6px;font-weight:500;">
              ${data.logged_in ? 'Your cart is empty' : 'Please log in to view your cart'}
            </p>
            <p style="font-size:13px;color:#9ca3af;margin:0 0 20px;">
              ${data.logged_in ? 'Add items to get started' : ''}
            </p>
            ${data.logged_in ? `
              <a href="${BASE}/shop"
                 onclick="closeCartSidebar()"
                 style="background:#111;color:#fff;padding:9px 22px;border-radius:8px;
                        text-decoration:none;font-size:13px;font-weight:500;">
                Browse Products
              </a>` : ''}
          </div>`;
            footer.style.display = 'none';
            return;
        }

        body.innerHTML = data.items.map(item => `
      <div style="display:flex;gap:10px;padding:12px 14px;
                  border-bottom:0.5px solid #e5e7eb;align-items:flex-start;
                  background:#fff;transition:background 0.15s;"
           onmouseover="this.style.background='#fafafa'"
           onmouseout="this.style.background='#fff'">

        <div style="flex-shrink:0;width:56px;height:56px;border-radius:8px;overflow:hidden;
                    border:0.5px solid #e5e7eb;background:#f9fafb;
                    display:flex;align-items:center;justify-content:center;">
          ${item.image
                ? `<img src="${BASE}/${item.image}"
                    style="width:100%;height:100%;object-fit:contain;"
                    onerror="this.style.display='none'">`
                : `<i class="fa-solid fa-image" style="font-size:18px;color:#d1d5db;"></i>`}
        </div>

        <div style="flex:1;min-width:0;">
          <p style="font-size:12px;font-weight:600;color:#111;margin:0 0 3px;
                    text-transform:uppercase;white-space:nowrap;
                    overflow:hidden;text-overflow:ellipsis;">
            ${item.name}
          </p>
          <p style="font-size:11px;color:#9ca3af;margin:0 0 6px;">${item.details}</p>
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;color:#6b7280;">
              Qty: <strong>${item.quantity}</strong>
            </span>
            <span style="font-size:13px;font-weight:700;color:#16a34a;">
              ${fmt(item.subtotal)}
            </span>
          </div>
        </div>

        <a href="javascript:void(0)"
   onclick="removeCartItem(${item.id}, this)"
   style="color:#ef4444;padding:2px;text-decoration:none;flex-shrink:0;cursor:pointer;"
   onmouseover="this.style.opacity='0.7'"
   onmouseout="this.style.opacity='1'">
  <svg width="14" height="14" fill="none" viewBox="0 0 24 24"
       stroke="currentColor" stroke-width="2">
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M6 18L18 6M6 6l12 12"/>
  </svg>
</a>
      </div>`).join('');

        // footer
        footer.style.display = 'block';
        document.getElementById('cartSidebarTotal').textContent = fmt(data.total);
    }

    // ─── fetch & render ───────────────────────────────────────────────
    function refreshCart() {
        fetch(CART_API, { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                renderItems(data);

                const navBadge = document.getElementById('cartNavBadge');
                if (navBadge) {
                    navBadge.style.display = data.count > 0 ? 'block' : 'none';
                }
            })
            .catch(() => { }); // silent fail – sidebar already shows last PHP render
    }

    function removeCartItem(itemId, btn) {
    // disable agad para di ma-double click
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.3';

    fetch(`${BASE}/removecart?key=${itemId}`, {
        credentials: 'include'
    })
    .then(r => {
        // hindi na kailangan ng JSON response, refresh na lang
        refreshCart();

        // sync red dot
        const navBadge = document.getElementById('cartNavBadge');
        setTimeout(() => {
            fetch(CART_API, { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    if (navBadge) {
                        navBadge.style.display = data.count > 0 ? 'block' : 'none';
                    }
                });
        }, 300);
    })
    .catch(() => {
        // re-enable kung may error
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '1';
    });
}

function openCartSidebar() {
    const sidebar = document.getElementById('cartSidebar');
    const icon = document.getElementById('cartNavIcon');

    sidebar.style.visibility = 'visible';
    sidebar.style.opacity = '1';
    sidebar.style.transform = 'translateX(0)';

    // highlight icon habang bukas
    if (icon) {
        icon.classList.add('bg-orange-50', 'text-orange-500');
        icon.classList.remove('text-black');
    }

    const spinner = document.getElementById('cartSidebarSpinner');
    if (spinner) spinner.style.display = 'flex';

    refreshCart();

    clearInterval(cartPollTimer);
    cartPollTimer = setInterval(refreshCart, 8000);
}

function closeCartSidebar() {
    const sidebar = document.getElementById('cartSidebar');
    const icon = document.getElementById('cartNavIcon');

    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(20px)';

    // remove highlight
    if (icon) {
        icon.classList.remove('bg-orange-50', 'text-orange-500');
        icon.classList.add('text-black');
    }

    clearInterval(cartPollTimer);
    setTimeout(() => { sidebar.style.visibility = 'hidden'; }, 250);
}

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeCartSidebar();
    });

    // Auto-refresh on page load para ma-sync agad ang badge
document.addEventListener('DOMContentLoaded', () => {
    refreshCart();
});
</script>