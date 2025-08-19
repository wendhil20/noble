// 🔁 Universal Swiper initializer
function initSwiper(selector, options) {
    if (document.querySelector(selector)) {
        return new Swiper(selector, options);
    }
}

// 🛒 Product form submit handler
async function handleProductFormSubmit(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const button = form.querySelector('button[type="submit"]');
    const originalText = button.innerHTML;

    // Show loading state
    button.disabled = true;
    button.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span> Adding...';

    try {
        // Defaults
        formData.set('selected_color_id', formData.get('selected_color_id') || '1');
        formData.set('selected_color_name', formData.get('selected_color_name') || 'Default');
        formData.set('color_price', formData.get('color_price') || '0');

        const response = await fetch('../cart/add_to_cart', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message || 'Added to cart', 'success');
            updateCartCount(data.cart_count);

            button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Added!';
            button.className = button.className.replace('bg-orange-500 hover:bg-orange-600', 'bg-green-500');

            setTimeout(() => {
                button.innerHTML = originalText;
                button.className = button.className.replace('bg-green-500', 'bg-orange-500 hover:bg-orange-600');
                button.disabled = false;
            }, 2000);
        } else {
            throw new Error(data.message || 'Add to cart failed.');
        }
    } catch (error) {
        showNotification(' ' + error.message, 'error');
        console.error('Add to cart error:', error);

        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// 🔔 Notification utility
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const bgColor = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    }[type] || 'bg-blue-500';

    notification.className = `fixed top-4 left-1/2 -translate-x-1/2 p-4 rounded-lg z-50 ${bgColor} text-white shadow-lg transform transition-all duration-300`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);

    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// 🛒 Cart count updater
function updateCartCount(count) {
    document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]').forEach(el => {
        el.textContent = count;
        el.style.display = count > 0 ? 'inline' : 'none';
    });

    const bubble = document.getElementById('cart-count-bubble');
    if (bubble) {
        bubble.classList.toggle('hidden', count <= 0);
        bubble.style.display = count > 0 ? 'inline' : 'none';
    }
}

// 💬 Chat toggle
function openChat() {
    document.getElementById('chat-box').style.display = 'block';
    document.getElementById('chat-toggle').style.display = 'none';
}
function closeChat() {
    document.getElementById('chat-box').style.display = 'none';
    document.getElementById('chat-toggle').style.display = 'inline-block';
}

// 🔃 Auto vertical swiper loader
function initAutoVerticalSwipers() {
    const containers = document.querySelectorAll('[class*="swiper-auto-"]');
    containers.forEach((container, index) => {
        const slides = container.querySelectorAll('.swiper-slide');
        if (slides.length > 0) {
            new Swiper(container, {
                direction: 'vertical',
                loop: slides.length > 1,
                slidesPerView: 1,
                spaceBetween: 0,
                autoplay: slides.length > 1 ? {
                    delay: 3000 + (index * 500),
                    disableOnInteraction: false,
                    pauseOnMouseEnter: false,
                    waitForTransition: true,
                } : false,
                speed: 1000,
                effect: 'slide',
                on: {
                    init: () => console.log(`Swiper ${index} initialized with ${slides.length} slides`),
                    slideChange: () => console.log(`Swiper ${index} slide changed`)
                }
            });
        }
    });
}

// 🚀 DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if Swiper is loaded
    if (typeof Swiper === 'undefined') {
        console.error('Swiper library is not loaded.');
        return;
    }

    // 🔁 Swiper instances
    initSwiper('.mySwiper', {
        loop: true,
        autoplay: { delay: 3000, disableOnInteraction: false },
        pagination: { el: ".swiper-pagination", clickable: true },
        navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" },
    });

    initSwiper('.mySwiper-products', {
        slidesPerView: 2,
        spaceBetween: 10,
        loop: true,
        autoplay: { delay: 3000, disableOnInteraction: false, pauseOnMouseEnter: true },
        pagination: { el: ".swiper-pagination", clickable: true },
        navigation: { nextEl: ".swiper-button-next", prevEl: ".swiper-button-prev" },
        breakpoints: {
            768: { slidesPerView: 4, spaceBetween: 15 },  // ✅ 4 products sa tablet up
            1024: { slidesPerView: 4, spaceBetween: 20 }, // ✅ 4 products sa desktop
            1280: { slidesPerView: 4, spaceBetween: 25 }, // ✅ 4 products sa large desktop
            1536: { slidesPerView: 4, spaceBetween: 30 }  // ✅ 4 products sa xl desktop
        }
    });

    // 🛏️ FIXED: Para sa BED FURNITURE - 4 products display
    initSwiper('.mySwiper-indoor', {
        slidesPerView: 2,
        spaceBetween: 10,
        loop: true,
        autoplay: { delay: 3000, disableOnInteraction: false },
        breakpoints: {
            480: { slidesPerView: 2, spaceBetween: 10 },  // mobile landscape
            640: { slidesPerView: 3, spaceBetween: 15 },  // small tablet
            768: { slidesPerView: 4, spaceBetween: 15 },  // ✅ FIXED: 4 products sa tablet
            1024: { slidesPerView: 4, spaceBetween: 20 }, // ✅ FIXED: 4 products sa desktop
            1440: { slidesPerView: 4, spaceBetween: 25 }, // ✅ FIXED: 4 products sa large desktop
            1920: { slidesPerView: 4, spaceBetween: 30 }  // ✅ FIXED: 4 products sa xl desktop
        }
    });

    initSwiper('.mySwiper-material', {
        slidesPerView: 2,
        spaceBetween: 15,
        loop: true,
        autoplay: { delay: 2500, disableOnInteraction: false },
        breakpoints: {
            768: { slidesPerView: 4, spaceBetween: 15 },  // ✅ 4 products sa tablet up
            1024: { slidesPerView: 4, spaceBetween: 20 }, // ✅ 4 products sa desktop
            1280: { slidesPerView: 4, spaceBetween: 25 }, // ✅ 4 products sa large desktop
            1536: { slidesPerView: 4, spaceBetween: 30 }  // ✅ 4 products sa xl desktop
        }
    });

    // 💡 Auto vertical swipers
    initAutoVerticalSwipers();

    // 🛒 Form events
    document.querySelectorAll('.productForm').forEach(form => {
        form.addEventListener('submit', handleProductFormSubmit);
    });

    // 🔗 Smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});