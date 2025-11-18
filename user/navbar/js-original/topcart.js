          // ===== ENHANCED REAL-TIME CART SYSTEM =====
document.addEventListener('DOMContentLoaded', function () {
    const cartContainer = document.getElementById('cart-container');
    const cartModal = document.getElementById('cart-modal');
    let hoverTimeout;
    let autoRefreshInterval;
    let lastCartHash = '';
    let isUpdating = false;
    let updateQueue = [];
    let wsConnection = null;

    // Remove existing show class and hide modal initially
    cartModal.classList.remove('show');
    cartModal.style.display = 'none';

    // ===== ENHANCED AUTO-REFRESH WITH INTELLIGENT INTERVALS =====
    function startEnhancedAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        let intervalTime = 5000; // Start with 5 seconds
        let lastActivity = Date.now();
        
        autoRefreshInterval = setInterval(() => {
            const timeSinceLastActivity = Date.now() - lastActivity;
            
            // Adjust interval based on user activity
            if (timeSinceLastActivity < 30000) { // Active in last 30 seconds
                intervalTime = 3000; // 3 second updates
            } else if (timeSinceLastActivity < 300000) { // Active in last 5 minutes
                intervalTime = 10000; // 10 second updates
            } else {
                intervalTime = 30000; // 30 second updates for inactive users
            }
            
            silentCartUpdate();
        }, intervalTime);
        
        // Track user activity
        ['mouseenter', 'click', 'focus', 'scroll'].forEach(event => {
            cartContainer.addEventListener(event, () => {
                lastActivity = Date.now();
            });
        });
        
        console.log('');
    }

    // ===== QUEUE-BASED UPDATE SYSTEM =====
    function queueUpdate(updateFunction) {
        updateQueue.push(updateFunction);
        processUpdateQueue();
    }

    async function processUpdateQueue() {
        if (isUpdating || updateQueue.length === 0) return;
        
        isUpdating = true;
        
        while (updateQueue.length > 0) {
            const update = updateQueue.shift();
            try {
                await update();
                // Small delay between updates to prevent overwhelming
                await new Promise(resolve => setTimeout(resolve, 100));
            } catch (error) {
                console.error('Update failed:', error);
            }
        }
        
        isUpdating = false;
    }

    // ===== ENHANCED HOVER FUNCTIONALITY =====
    cartContainer.addEventListener('mouseenter', function () {
        clearTimeout(hoverTimeout);
        
        // Immediate update on hover without loading indicators
        queueUpdate(() => instantCartUpdate());
        
        positionModal();
        showModal();
    });

    function positionModal() {
        const cartRect = cartContainer.getBoundingClientRect();
        const modalWidth = 320;
        const viewportWidth = window.innerWidth;

        let rightPos = viewportWidth - cartRect.right;
        if (cartRect.right - modalWidth < 0) {
            rightPos = 16;
        }

        // Responsive positioning
        if (window.innerWidth <= 640) {
            cartModal.style.right = '0.5rem';
            cartModal.style.left = '0.5rem';
            cartModal.style.width = 'auto';
        } else {
            cartModal.style.right = rightPos + 'px';
            cartModal.style.left = 'auto';
            cartModal.style.width = '';
        }
        
        cartModal.style.top = (cartRect.bottom + 8) + 'px';
    }

    function showModal() {
        cartModal.style.display = 'block';
        // Use requestAnimationFrame for smooth animation
        requestAnimationFrame(() => {
            cartModal.classList.add('show');
        });
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

    cartContainer.addEventListener('mouseleave', hideModal);
    cartModal.addEventListener('mouseenter', () => clearTimeout(hoverTimeout));
    cartModal.addEventListener('mouseleave', hideModal);

    // ===== INSTANT UPDATE (NO LOADING INDICATORS) =====
    async function instantCartUpdate() {
        return silentCartUpdate(true); // true = instant mode
    }

    // ===== ENHANCED SILENT UPDATE =====
    async function silentCartUpdate(instantMode = false) {
        const possiblePaths = [
            'navbar/refresh_cart.php',
            '../navbar/refresh_cart.php',
            '../../navbar/refresh_cart.php',
            '/Noble/user/otherpage/navbar/refresh_cart.php',
            '/Noble/user/navbar/refresh_cart.php',
            '/Noble/navbar/refresh_cart.php'
        ];

        async function tryPath(pathIndex = 0) {
            if (pathIndex >= possiblePaths.length) return null;

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), instantMode ? 2000 : 5000);

                const response = await fetch(possiblePaths[pathIndex], {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0'
                    },
                    credentials: 'same-origin',
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (!response.ok) return tryPath(pathIndex + 1);
                
                const responseText = await response.text();
                if (responseText.trim().startsWith('<!DOCTYPE html>') || 
                    responseText.trim().startsWith('<html')) {
                    return tryPath(pathIndex + 1);
                }

                const data = JSON.parse(responseText);
                if (data && data.success) {
                    return data;
                }
                return tryPath(pathIndex + 1);
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log('Request timeout, trying next path');
                }
                return tryPath(pathIndex + 1);
            }
        }

        try {
            const data = await tryPath();
            if (data) {
                updateCartDisplay(data, !instantMode, true);
                return data;
            }
        } catch (error) {
            console.log('Silent update failed:', error.message);
        }
        return null;
    }

    // ===== OPTIMIZED UPDATE DISPLAY FUNCTION =====
    function updateCartDisplay(data, withAnimations = false, isSilent = false) {
        // Use DocumentFragment for better performance
        const fragment = document.createDocumentFragment();
        
        // Check if cart content actually changed
        const newCartHash = data.cart_hash || JSON.stringify(data.cart_html);
        if (isSilent && newCartHash === lastCartHash) {
            return; // No changes, skip update
        }
        lastCartHash = newCartHash;

        // Batch DOM updates
        requestAnimationFrame(() => {
            updateCartItems(data, withAnimations, isSilent);
            updateCartCounts(data, withAnimations, isSilent);
            updateCartFooter(data);
        });

        // Trigger cross-tab update
        triggerCartUpdate();
    }

    function updateCartItems(data, withAnimations, isSilent) {
        const cartItemsContainer = document.getElementById('cart-items-container');
        if (!data.cart_html || !cartItemsContainer) return;

        if (withAnimations && !isSilent) {
            cartItemsContainer.style.opacity = '0';
            setTimeout(() => {
                cartItemsContainer.innerHTML = data.cart_html;
                cartItemsContainer.style.opacity = '1';
                
                // Add slide animations with stagger
                const items = cartItemsContainer.querySelectorAll('.cart-item-slide');
                items.forEach((item, index) => {
                    item.style.animation = `slideInRight 0.3s ease-out ${index * 0.05}s forwards`;
                });
            }, 100);
        } else {
            cartItemsContainer.innerHTML = data.cart_html;
        }
    }

    function updateCartCounts(data, withAnimations, isSilent) {
        const modalCartCount = document.getElementById('modal-cart-count');
        const cartCountBubble = document.getElementById('cart-count-bubble');
        const cartCountSpan = document.querySelector('.cart-count[data-cart-count]');

        if (data.total_items === undefined) return;

        // Update modal count
        if (modalCartCount) {
            const newText = data.total_items + ' items';
            if (modalCartCount.textContent !== newText) {
                modalCartCount.textContent = newText;
                
                if (withAnimations && !isSilent) {
                    modalCartCount.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        modalCartCount.style.transform = 'scale(1)';
                    }, 200);
                }
            }
        }

        // Update count bubble
        if (cartCountSpan) {
            const newCount = data.total_items.toString();
            if (cartCountSpan.textContent !== newCount) {
                cartCountSpan.textContent = newCount;
                
                if (withAnimations && !isSilent) {
                    cartCountSpan.style.transform = 'scale(1.2)';
                    cartCountSpan.style.backgroundColor = '#10b981';
                    setTimeout(() => {
                        cartCountSpan.style.transform = 'scale(1)';
                        cartCountSpan.style.backgroundColor = '';
                    }, 300);
                }
            }
        }

        // Show/hide count bubble with smooth transition
        if (cartCountBubble) {
            if (data.total_items > 0) {
                cartCountBubble.classList.remove('hidden');
                cartCountBubble.style.opacity = '1';
                cartCountBubble.style.transform = 'scale(1)';
            } else {
                cartCountBubble.style.opacity = '0';
                cartCountBubble.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    cartCountBubble.classList.add('hidden');
                }, 200);
            }
        }
    }

    function updateCartFooter(data) {
        let cartFooter = document.getElementById('cart-footer');
        
        if (data.footer_html) {
            if (cartFooter) {
                if (cartFooter.innerHTML !== data.footer_html) {
                    cartFooter.innerHTML = data.footer_html;
                }
            } else {
                cartFooter = document.createElement('div');
                cartFooter.id = 'cart-footer';
                cartFooter.className = 'border-t border-gray-200 p-3 sm:p-4 bg-gray-50 rounded-b-xl';
                cartFooter.innerHTML = data.footer_html;
                cartModal.appendChild(cartFooter);
            }
        } else if (cartFooter) {
            cartFooter.remove();
        }
    }

    // ===== ENHANCED REMOVE FUNCTION =====
    window.removeFromCart = async function(itemId) {
        // Immediate visual feedback
        const itemElement = document.querySelector(`[onclick="removeFromCart(${itemId})"]`).closest('.cart-item-slide');
        if (itemElement) {
            itemElement.style.opacity = '0.5';
            itemElement.style.transform = 'translateX(-20px)';
        }

        const possiblePaths = [
            'remove_from_cart_ajax.php',
            'navbar/remove_from_cart_ajax.php',
            '../remove_from_cart_ajax.php',
            '../navbar/remove_from_cart_ajax.php',
            '/noble/navbar/remove_from_cart_ajax.php'
        ];

        async function tryRemovePath(pathIndex = 0) {
            if (pathIndex >= possiblePaths.length) {
                throw new Error('Could not find remove_from_cart_ajax.php');
            }

            try {
                const response = await fetch(possiblePaths[pathIndex], {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache'
                    },
                    credentials: 'same-origin',
                    body: 'key=' + encodeURIComponent(itemId)
                });

                if (!response.ok) return tryRemovePath(pathIndex + 1);

                const responseText = await response.text();
                if (responseText.trim().startsWith('<!DOCTYPE html>')) {
                    return tryRemovePath(pathIndex + 1);
                }

                const data = JSON.parse(responseText);
                if (data && data.success) {
                    return data;
                }
                return tryRemovePath(pathIndex + 1);

            } catch (error) {
                return tryRemovePath(pathIndex + 1);
            }
        }

        try {
            const result = await tryRemovePath();
            if (result && result.success) {
                // Remove item with animation
                if (itemElement) {
                    itemElement.style.transform = 'translateX(-100%)';
                    itemElement.style.opacity = '0';
                    setTimeout(() => {
                        itemElement.remove();
                    }, 300);
                }

                // Update cart immediately
                setTimeout(() => {
                    queueUpdate(() => instantCartUpdate());
                }, 300);

                showNotification('Item removed!', 'success');
            } else {
                throw new Error('Failed to remove item');
            }
        } catch (error) {
            // Reset visual state on error
            if (itemElement) {
                itemElement.style.opacity = '1';
                itemElement.style.transform = 'translateX(0)';
            }
            showNotification('Failed to remove item', 'error');
        }
    };

    // ===== ENHANCED REFRESH FUNCTION =====
    window.refreshCart = function() {
        const refreshBtn = document.getElementById('refresh-cart-btn');
        
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-sm"></i>';
            refreshBtn.disabled = true;
        }

        queueUpdate(async () => {
            const data = await silentCartUpdate();
            if (data) {
                showNotification('Cart updated!', 'success');
            } else {
                showNotification('Update failed', 'error');
            }
        }).finally(() => {
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt text-sm"></i>';
                refreshBtn.disabled = false;
            }
        });
    };

    // ===== NOTIFICATION SYSTEM =====
    function showNotification(message, type = 'success') {
        // Remove existing notifications
        document.querySelectorAll('.cart-notification').forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `cart-notification fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-[10000] text-sm font-medium transition-all duration-300 transform translate-x-full`;
        
        const colors = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            info: 'bg-blue-500 text-white'
        };
        
        const icons = {
            success: 'fas fa-check',
            error: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        notification.className += ` ${colors[type] || colors.success}`;
        notification.innerHTML = `<i class="${icons[type] || icons.success} mr-2"></i>${message}`;
        
        document.body.appendChild(notification);
        
        // Slide in
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });
        
        // Auto-hide
        setTimeout(() => {
            notification.style.transform = 'translateX(400px)';
            setTimeout(() => notification.remove(), 300);
        }, type === 'error' ? 4000 : 2500);
    }

    // ===== CROSS-TAB SYNCHRONIZATION =====
    function triggerCartUpdate() {
        try {
            localStorage.setItem('cart_updated', Date.now().toString());
            // Broadcast custom event for same-tab updates
            window.dispatchEvent(new CustomEvent('cartUpdated', { 
                detail: { timestamp: Date.now() } 
            }));
        } catch (e) {
            console.log('Cross-tab sync not available');
        }
    }

    // Listen for storage events (cross-tab)
    window.addEventListener('storage', function(e) {
        if (e.key === 'cart_updated') {
          
            queueUpdate(() => silentCartUpdate(true));
        }
    });

    // Listen for same-tab events
    window.addEventListener('cartUpdated', function(e) {
       
        queueUpdate(() => silentCartUpdate(true));
    });

    // ===== PAGE VISIBILITY OPTIMIZATION =====
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Pause updates when tab is hidden
            clearInterval(autoRefreshInterval);
        } else {
            // Resume updates when tab becomes visible
            startEnhancedAutoRefresh();
            queueUpdate(() => instantCartUpdate());
        }
    });

    // ===== GLOBAL FUNCTIONS =====
    window.updateCartGlobally = function() {
        queueUpdate(() => instantCartUpdate());
        triggerCartUpdate();
    };

    // Initial update
    setTimeout(() => {
        queueUpdate(() => silentCartUpdate(true));
    }, 500);

    
});

// ===== ENHANCED CSS STYLES =====
const enhancedStyles = document.createElement('style');
enhancedStyles.textContent = `
    .cart-count[data-cart-count] {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform-origin: center;
    }
    
    #modal-cart-count {
        transition: all 0.2s ease;
        transform-origin: center;
    }
    
    #cart-items-container {
        transition: opacity 0.15s ease;
    }
    
    .cart-modal {
        will-change: transform, opacity;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    
    .cart-item-slide {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: transform, opacity;
    }
    
    .cart-notification {
        will-change: transform;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Smooth count bubble transitions */
    #cart-count-bubble {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        transform-origin: center;
    }
    
    /* Optimized for performance */
    .cart-modal * {
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
    }
`;
document.head.appendChild(enhancedStyles);