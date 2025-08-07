document.addEventListener('DOMContentLoaded', function () {
    const cartContainer = document.getElementById('cart-container');
    const cartModal = document.getElementById('cart-modal');
    let hoverTimeout;
    let autoRefreshInterval;
    let lastCartHash = '';

    // Remove existing show class and hide modal initially
    cartModal.classList.remove('show');
    cartModal.style.display = 'none';

    // Enhanced hover functionality with real-time updates
    cartContainer.addEventListener('mouseenter', function () {
        clearTimeout(hoverTimeout);
        
        // Quick update on hover
        quickCartUpdate();
        
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
        cartModal.style.display = 'block';
        
        // Small delay for smooth animation
        setTimeout(() => {
            cartModal.classList.add('show');
        }, 10);
    });

    cartContainer.addEventListener('mouseleave', function () {
        hoverTimeout = setTimeout(() => {
            cartModal.classList.remove('show');
            setTimeout(() => {
                if (!cartModal.classList.contains('show')) {
                    cartModal.style.display = 'none';
                }
            }, 300);
        }, 300);
    });

    cartModal.addEventListener('mouseenter', function () {
        clearTimeout(hoverTimeout);
    });

    cartModal.addEventListener('mouseleave', function () {
        hoverTimeout = setTimeout(() => {
            cartModal.classList.remove('show');
            setTimeout(() => {
                if (!cartModal.classList.contains('show')) {
                    cartModal.style.display = 'none';
                }
            }, 300);
        }, 300);
    });

    // Start auto-refresh system
    startAutoRefresh();
    
    // Listen for page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
            quickCartUpdate();
        }
    });

    // Listen for storage events (cross-tab updates)
    window.addEventListener('storage', function(e) {
        if (e.key === 'cart_updated' || e.key === 'cart_changed') {
            console.log('🔄 Cross-tab cart update detected');
            silentCartUpdate();
        }
    });

    // Initial silent update after page load
    setTimeout(() => {
        silentCartUpdate();
    }, 1000);
});

// ===== AUTO-REFRESH SYSTEM =====
function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    
    // Auto-update every 20 seconds (faster for better real-time feel)
    autoRefreshInterval = setInterval(() => {
        silentCartUpdate();
    }, 20000);
    
    console.log('🔄 Real-time auto-refresh started (every 20s)');
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    console.log('⏸️ Auto-refresh paused');
}

// ===== SILENT UPDATE - NO LOADING INDICATORS =====
function silentCartUpdate() {
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
            const response = await fetch(possiblePaths[pathIndex], {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin'
            });

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
            return tryPath(pathIndex + 1);
        }
    }

    return tryPath().then(data => {
        if (data) {
            updateCartDisplay(data, false, true); // false = no animations, true = silent
            return data;
        }
        return null;
    }).catch(error => {
        console.log('Silent update failed:', error.message);
        return null;
    });
}

// ===== QUICK UPDATE - MINIMAL LOADING =====
function quickCartUpdate() {
    const cartCountSpan = document.querySelector('.cart-count[data-cart-count]');
    if (cartCountSpan) {
        cartCountSpan.style.opacity = '0.8';
        cartCountSpan.style.transform = 'scale(0.95)';
    }
    
    silentCartUpdate().then(data => {
        if (cartCountSpan) {
            cartCountSpan.style.opacity = '1';
            cartCountSpan.style.transform = 'scale(1)';
            
            if (data && data.total_items !== undefined) {
                // Add subtle pulse for count change
                cartCountSpan.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    cartCountSpan.style.transform = 'scale(1)';
                }, 200);
            }
        }
    });
}

// ===== ENHANCED REFRESH FUNCTION =====
function refreshCart() {
    const refreshBtn = document.getElementById('refresh-cart-btn');
    const cartItemsContainer = document.getElementById('cart-items-container');
    const cartFooter = document.getElementById('cart-footer');
    const cartLoading = document.getElementById('cart-loading');
    
    // Show loading state
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-sm"></i>';
        refreshBtn.disabled = true;
    }
    
    if (cartLoading) {
        cartLoading.classList.remove('hidden');
    }
    
    if (cartItemsContainer) {
        cartItemsContainer.style.opacity = '0.6';
    }

    const possiblePaths = [
        'navbar/refresh_cart.php',
        '../navbar/refresh_cart.php',
        '../../navbar/refresh_cart.php',
        '/Noble/user/otherpage/navbar/refresh_cart.php',
        '/Noble/user/navbar/refresh_cart.php',
        '/Noble/navbar/refresh_cart.php'
    ];

    async function tryFetchPath(pathIndex = 0) {
        if (pathIndex >= possiblePaths.length) {
            throw new Error('Could not find refresh_cart.php');
        }

        const currentPath = possiblePaths[pathIndex];
        
        try {
            const response = await fetch(currentPath, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin'
            });

            const responseText = await response.text();
            
            if (responseText.trim().startsWith('<!DOCTYPE html>') || 
                responseText.trim().startsWith('<html')) {
                return tryFetchPath(pathIndex + 1);
            }

            if (!response.ok) {
                return tryFetchPath(pathIndex + 1);
            }

            try {
                const data = JSON.parse(responseText);
                console.log('✅ Manual refresh successful at:', currentPath);
                return data;
            } catch (parseError) {
                return tryFetchPath(pathIndex + 1);
            }

        } catch (error) {
            return tryFetchPath(pathIndex + 1);
        }
    }

    tryFetchPath()
        .then(data => {
            if (data && data.success) {
                updateCartDisplay(data, true, false); // true = with animations, false = not silent
                showNotification('Cart refreshed successfully!', 'success');
            } else {
                throw new Error(data?.message || 'Server returned success=false');
            }
        })
        .catch(error => {
            console.error('Manual refresh failed:', error);
            if (cartItemsContainer) {
                cartItemsContainer.innerHTML = `
                    <div class="text-center py-4 text-red-500">
                        <i class="fas fa-exclamation-triangle mb-2 text-xl"></i>
                        <p class="text-sm font-medium">Failed to refresh cart</p>
                        <button onclick="refreshCart()" class="mt-2 px-4 py-2 text-xs bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                            🔄 Try Again
                        </button>
                    </div>`;
            }
            showNotification('Failed to refresh cart', 'error');
        })
        .finally(() => {
            // Reset button state
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt text-sm"></i>';
                refreshBtn.disabled = false;
            }
            if (cartLoading) {
                cartLoading.classList.add('hidden');
            }
            if (cartItemsContainer) {
                cartItemsContainer.style.opacity = '1';
            }
        });
}

// ===== UPDATE CART DISPLAY FUNCTION =====
function updateCartDisplay(data, withAnimations = true, isSilent = false) {
    const cartItemsContainer = document.getElementById('cart-items-container');
    const cartFooter = document.getElementById('cart-footer');
    const modalCartCount = document.getElementById('modal-cart-count');
    const cartCountBubble = document.getElementById('cart-count-bubble');
    const cartCountSpan = document.querySelector('.cart-count[data-cart-count]');
    
    // Check if cart content actually changed
    const newCartHash = data.cart_hash || JSON.stringify(data.cart_html);
    if (isSilent && newCartHash === lastCartHash) {
        return; // No changes, skip update
    }
    lastCartHash = newCartHash;
    
    // Update cart items
    if (data.cart_html && cartItemsContainer) {
        if (withAnimations && !isSilent) {
            cartItemsContainer.style.opacity = '0';
            setTimeout(() => {
                cartItemsContainer.innerHTML = data.cart_html;
                cartItemsContainer.style.opacity = '1';
                
                // Add slide animations
                const items = cartItemsContainer.querySelectorAll('.cart-item-slide');
                items.forEach((item, index) => {
                    item.style.animation = `slideInRight 0.3s ease-out ${index * 0.05}s forwards`;
                });
            }, 150);
        } else {
            cartItemsContainer.innerHTML = data.cart_html;
        }
    }
    
    // Update item count with smooth transition
    if (modalCartCount && data.total_items !== undefined) {
        const currentText = modalCartCount.textContent;
        const newText = data.total_items + ' items';
        
        if (currentText !== newText) {
            if (withAnimations && !isSilent) {
                modalCartCount.style.transform = 'scale(0.8)';
                modalCartCount.style.opacity = '0.5';
                setTimeout(() => {
                    modalCartCount.textContent = newText;
                    modalCartCount.style.transform = 'scale(1)';
                    modalCartCount.style.opacity = '1';
                }, 100);
            } else {
                modalCartCount.textContent = newText;
            }
        }
    }
    
    if (cartCountSpan && data.total_items !== undefined) {
        const currentCount = cartCountSpan.textContent;
        const newCount = data.total_items.toString();
        
        if (currentCount !== newCount) {
            cartCountSpan.textContent = newCount;
            
            // Add pulse animation for count change
            if (withAnimations && !isSilent) {
                cartCountSpan.style.transform = 'scale(1.2)';
                cartCountSpan.style.backgroundColor = '#10b981'; // Green flash
                setTimeout(() => {
                    cartCountSpan.style.transform = 'scale(1)';
                    cartCountSpan.style.backgroundColor = ''; // Reset to original
                }, 300);
            }
        }
    }

    // Show/hide cart count bubble
    if (cartCountBubble) {
        if (data.total_items > 0) {
            cartCountBubble.classList.remove('hidden');
        } else {
            cartCountBubble.classList.add('hidden');
        }
    }

    // Update footer
    if (data.footer_html) {
        if (cartFooter) {
            if (cartFooter.innerHTML !== data.footer_html) {
                cartFooter.innerHTML = data.footer_html;
            }
        } else {
            // Create footer if it doesn't exist
            const footerDiv = document.createElement('div');
            footerDiv.id = 'cart-footer';
            footerDiv.className = 'border-t border-gray-200 p-3 sm:p-4 bg-gray-50 rounded-b-xl';
            footerDiv.innerHTML = data.footer_html;
            document.getElementById('cart-modal').appendChild(footerDiv);
        }
    } else if (cartFooter) {
        cartFooter.remove();
    }
    
    // Trigger cross-tab update
    triggerCartUpdate();
}

// ===== ENHANCED REMOVE FUNCTION =====
function removeFromCart(itemId) {
    const cartCount = document.querySelector('.cart-count[data-cart-count]');
    if (cartCount && cartCount.textContent === '0') {
        showNotification('Cart appears to be empty. Refreshing...', 'info');
        quickCartUpdate();
        return;
    }

    // Show loading state
    const removeButton = document.querySelector(`[onclick="removeFromCart(${itemId})"]`);
    const originalContent = removeButton ? removeButton.innerHTML : '';
    if (removeButton) {
        removeButton.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i>';
        removeButton.disabled = true;
        removeButton.style.pointerEvents = 'none';
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

        const currentPath = possiblePaths[pathIndex];
        
        try {
            const response = await fetch(currentPath, {
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

            const responseText = await response.text();
            
            if (responseText.trim().startsWith('<!DOCTYPE html>') || 
                responseText.trim().startsWith('<html')) {
                return tryRemovePath(pathIndex + 1);
            }

            if (!response.ok) {
                return tryRemovePath(pathIndex + 1);
            }

            try {
                const data = JSON.parse(responseText);
                console.log(' Item removal successful at:', currentPath);
                return data;
            } catch (parseError) {
                return tryRemovePath(pathIndex + 1);
            }

        } catch (error) {
            return tryRemovePath(pathIndex + 1);
        }
    }

    tryRemovePath()
        .then(data => {
            if (data && data.success) {
                // Immediately update cart display
                silentCartUpdate().then(() => {
                    showNotification('Item removed from cart!', 'success');
                });
                
                // Double-check update after a short delay
                setTimeout(() => {
                    silentCartUpdate();
                }, 1500);
                
            } else {
                throw new Error(data?.message || 'Failed to remove item from cart');
            }
        })
        .catch(error => {
            console.error('Remove item failed:', error);
            showNotification('Failed to remove item: ' + error.message, 'error');
        })
        .finally(() => {
            // Reset button state
            if (removeButton) {
                removeButton.innerHTML = originalContent;
                removeButton.disabled = false;
                removeButton.style.pointerEvents = 'auto';
            }
        });
}

// ===== NOTIFICATION SYSTEM =====
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.cart-notification');
    existingNotifications.forEach(n => n.remove());
    
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
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Slide out and remove
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, type === 'error' ? 4000 : 2500);
}

// ===== CROSS-TAB SYNC =====
function triggerCartUpdate() {
    try {
        localStorage.setItem('cart_updated', Date.now().toString());
        setTimeout(() => {
            localStorage.removeItem('cart_updated');
        }, 100);
    } catch (e) {
        // localStorage not available, skip
    }
}

// ===== GLOBAL CART UPDATE FUNCTION =====
window.updateCartGlobally = function() {
    silentCartUpdate();
    triggerCartUpdate();
};

// ===== ADD ENHANCED CSS STYLES =====
const realtimeCartStyles = document.createElement('style');
realtimeCartStyles.textContent = `
    .cart-count[data-cart-count] {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    #modal-cart-count {
        transition: all 0.2s ease;
    }
    
    #cart-items-container {
        transition: opacity 0.2s ease;
    }
    
    .cart-notification {
        max-width: 300px;
        word-wrap: break-word;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .cart-item-slide {
        transition: all 0.2s ease;
    }
    
    .cart-modal {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
`;
document.head.appendChild(realtimeCartStyles);

console.log(' Real-time Cart System Loaded Successfully!');