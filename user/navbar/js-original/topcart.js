// ===== CART SYSTEM - Event-driven only, NO continuous polling =====
document.addEventListener('DOMContentLoaded', function () {
    const cartContainer = document.getElementById('cart-container');
    const cartModal     = document.getElementById('cart-modal');

    if (!cartContainer || !cartModal) return;

    let hoverTimeout = null;
    let lastCartHash = '';
    let isFetching   = false;

    cartModal.classList.remove('show');
    cartModal.style.display = 'none';

    // ===== READ INITIAL COUNT FROM PHP HTML =====
    function getRenderedCount() {
        const span = document.querySelector('.cart-count[data-cart-count]');
        return parseInt(span?.textContent?.trim()) || 0;
    }

    let cartItemCount = getRenderedCount();
    console.log('[CART] Init count:', cartItemCount);

    // ===== SINGLE FETCH - called only when needed =====
    async function fetchCart() {
        if (isFetching) return;
        isFetching = true;

        const paths = [
            'navbar/refresh_cart.php',
            '../navbar/refresh_cart.php',
            '../../navbar/refresh_cart.php',
        ];

        for (const path of paths) {
            try {
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 5000);

                const res = await fetch(path, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache'
                    },
                    credentials: 'same-origin',
                    signal: controller.signal
                });

                clearTimeout(timer);
                if (!res.ok) continue;

                const text = await res.text();
                if (text.trim().startsWith('<!') || text.trim().startsWith('<html')) continue;

                let data;
                try { data = JSON.parse(text); } catch (e) { continue; }
                if (!data?.success) continue;

                // Update count
                cartItemCount = parseInt(data.total_items) || 0;

                // Update DOM only if changed
                const newHash = cartItemCount + '|' + (data.cart_html || '');
                if (newHash !== lastCartHash) {
                    lastCartHash = newHash;
                    updateDOM(data);
                }

                isFetching = false;
                return data;

            } catch (e) {
                if (e.name !== 'AbortError') continue;
            }
        }

        isFetching = false;
        return null;
    }

    // ===== UPDATE DOM =====
    function updateDOM(data) {
        requestAnimationFrame(() => {
            // Items
            const container = document.getElementById('cart-items-container');
            if (container && data.cart_html != null) {
                container.innerHTML = data.cart_html;
            }

            // Count badge
            const span   = document.querySelector('.cart-count[data-cart-count]');
            const bubble = document.getElementById('cart-count-bubble');
            const modal  = document.getElementById('modal-cart-count');

            if (span)   span.textContent   = data.total_items;
            if (modal)  modal.textContent  = data.total_items + ' items';

            if (bubble) {
                if (data.total_items > 0) {
                    bubble.classList.remove('hidden');
                } else {
                    bubble.classList.add('hidden');
                }
            }

            // Footer
            let footer = document.getElementById('cart-footer');
            if (data.footer_html) {
                if (!footer) {
                    footer = document.createElement('div');
                    footer.id = 'cart-footer';
                    footer.className = 'border-t border-gray-200 p-3 sm:p-4 bg-gray-50 rounded-b-xl';
                    cartModal.appendChild(footer);
                }
                if (footer.innerHTML !== data.footer_html) {
                    footer.innerHTML = data.footer_html;
                }
            } else if (footer) {
                footer.remove();
            }
        });
    }

    // ===== HOVER - fetch once on hover if has items =====
    cartContainer.addEventListener('mouseenter', function () {
        clearTimeout(hoverTimeout);

        // Fetch latest on hover only if cart has items
        if (cartItemCount > 0) {
            fetchCart();
        }

        positionModal();
        showModal();
    });

    cartContainer.addEventListener('mouseleave', hideModal);
    cartModal.addEventListener('mouseenter', () => clearTimeout(hoverTimeout));
    cartModal.addEventListener('mouseleave', hideModal);

    function positionModal() {
        const rect = cartContainer.getBoundingClientRect();
        const vw   = window.innerWidth;

        if (vw <= 640) {
            cartModal.style.right = '0.5rem';
            cartModal.style.left  = '0.5rem';
            cartModal.style.width = 'auto';
        } else {
            cartModal.style.right = (vw - rect.right) + 'px';
            cartModal.style.left  = 'auto';
            cartModal.style.width = '';
        }
        cartModal.style.top = (rect.bottom + 8) + 'px';
    }

    function showModal() {
        cartModal.style.display = 'block';
        requestAnimationFrame(() => cartModal.classList.add('show'));
    }

    function hideModal() {
        hoverTimeout = setTimeout(() => {
            cartModal.classList.remove('show');
            setTimeout(() => {
                if (!cartModal.classList.contains('show')) {
                    cartModal.style.display = 'none';
                }
            }, 300);
        }, 300);
    }

    // ===== REMOVE FROM CART =====
    window.removeFromCart = async function (itemId) {
        const btn    = document.querySelector('[onclick="removeFromCart(' + itemId + ')"]');
        const itemEl = btn?.closest('.cart-item-slide');

        if (itemEl) {
            itemEl.style.opacity   = '0.4';
            itemEl.style.transform = 'translateX(-20px)';
        }

        const paths = [
            'remove_from_cart_ajax.php',
            '../remove_from_cart_ajax.php',
            'navbar/remove_from_cart_ajax.php',
            '../navbar/remove_from_cart_ajax.php',
        ];

        for (const path of paths) {
            try {
                const res = await fetch(path, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: 'key=' + encodeURIComponent(itemId)
                });

                if (!res.ok) continue;
                const text = await res.text();
                if (text.trim().startsWith('<!')) continue;

                const result = JSON.parse(text);
                if (!result?.success) continue;

                // Animate out
                if (itemEl) {
                    itemEl.style.transform = 'translateX(-100%)';
                    itemEl.style.opacity   = '0';
                    setTimeout(() => itemEl.remove(), 300);
                }

                // ONE fetch after remove to update totals/footer
                setTimeout(() => fetchCart(), 350);

                showNotification('Item removed!', 'success');
                return;

            } catch (e) { /* try next path */ }
        }

        if (itemEl) {
            itemEl.style.opacity   = '1';
            itemEl.style.transform = 'translateX(0)';
        }
        showNotification('Failed to remove item', 'error');
    };

    // ===== CHECKOUT =====
    window.proceedToCheckout = function () {
        window.location.href = '../otherpage/index-checkout-page-12.php';
    };

window.updateCartGlobally = function () {
    console.log('[CART] updateCartGlobally - fetching after delay');
    // Huwag i-override ang cartItemCount dito
    // Hayaan ang fetchCart() na mag-update ng tamang value
    setTimeout(() => fetchCart(), 800);
};

    // ===== NOTIFICATION =====
    function showNotification(message, type) {
        document.querySelectorAll('.cart-notification').forEach(n => n.remove());

        const colors = { success: '#22c55e', error: '#ef4444', info: '#3b82f6' };
        const icons  = { success: 'fas fa-check', error: 'fas fa-exclamation-triangle', info: 'fas fa-info-circle' };

        const n = document.createElement('div');
        n.className = 'cart-notification';
        n.style.cssText = `
            position:fixed; top:1rem; right:1rem;
            padding:0.5rem 1rem; border-radius:0.5rem;
            background:${colors[type] || colors.success};
            color:white; font-size:0.875rem; font-weight:500;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
            z-index:10001; transform:translateX(400px);
            transition:transform 0.3s ease;
        `;
        n.innerHTML = '<i class="' + (icons[type] || icons.success) + ' mr-2"></i>' + message;
        document.body.appendChild(n);

        requestAnimationFrame(() => { n.style.transform = 'translateX(0)'; });
        setTimeout(() => {
            n.style.transform = 'translateX(400px)';
            setTimeout(() => n.remove(), 300);
        }, type === 'error' ? 4000 : 2500);
    }

    window.showNotification = showNotification;

window.updateCartBadge = function(newCount) {
    const count  = parseInt(newCount) || 0;
    const bubble = document.getElementById('cart-count-bubble');
    const modal  = document.getElementById('modal-cart-count');
    
    if (modal) modal.textContent = count + ' items';
    
    if (bubble) {
        if (count > 0) {
            bubble.classList.remove('hidden');
        } else {
            bubble.classList.add('hidden');
        }
    }
    cartItemCount = count;
};

 
});

// ===== STYLES =====
(function () {
    const s = document.createElement('style');
    s.textContent = `
        .cart-item-slide { transition: all 0.3s ease; }
        .cart-modal { transition: all 0.25s ease !important; }
        #cart-count-bubble { transition: all 0.25s ease; }
        @keyframes slideInRight {
            from { opacity:0; transform:translateX(30px); }
            to   { opacity:1; transform:translateX(0); }
        }
    `;
    document.head.appendChild(s);
})();