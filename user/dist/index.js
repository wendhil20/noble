
const swiperss = new Swiper(".mySwiper", {
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
        });

        const productsSwiper = new Swiper(".mySwiper-products", {
            slidesPerView: 2,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            breakpoints: {
                480: {
                    slidesPerView: 2,
                },
                640: {
                    slidesPerView: 2,
                },
                768: {
                    slidesPerView: 3,
                },
                1024: {
                    slidesPerView: 4,
                },
                1280: {
                    slidesPerView: 5,
                },
                1536: {
                    slidesPerView: 6,
                },
            },
        });


        var swiper = new Swiper(".mySwiper-indoor", {
            slidesPerView: 2,
            spaceBetween: 20,
            autoplay: {
                delay: 3000, // delay in milliseconds (3000ms = 3 seconds)
                disableOnInteraction: false, // continue autoplay after user interaction
            },
            loop: true, // optional: allows infinite loop of slides
            breakpoints: {
                640: {
                    slidesPerView: 2,
                },
                1024: {
                    slidesPerView: 3,
                },
                1440: {
                    slidesPerView: 5,
                },
                1920: {
                    slidesPerView: 6,
                }
            }
        });



        document.addEventListener('DOMContentLoaded', function() {
            new Swiper('.mySwiper-material', {
                slidesPerView: 2,
                spaceBetween: 15,
                autoplay: {
                    delay: 2500,
                    disableOnInteraction: false,
                },
                loop: true,
                breakpoints: {
                    320: {
                        slidesPerView: 2,
                        spaceBetween: 10,
                    },
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 10,
                    },
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 15,
                    },
                    1024: {
                        slidesPerView: 4,
                        spaceBetween: 20,
                    },
                    1280: {
                        slidesPerView: 5,
                        spaceBetween: 25,
                    },
                    1536: {
                        slidesPerView: 6,
                        spaceBetween: 30,
                    }
                },
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Handle form submissions for index.php product forms
            document.querySelectorAll('.productForm').forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const button = this.querySelector('button[type="submit"]');
                    const originalText = button.innerHTML;

                    // Show loading state
                    button.disabled = true;
                    button.innerHTML = '<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span> Adding...';

                    try {
                        // Ensure required fields are set
                        if (!formData.get('selected_color_id') || formData.get('selected_color_id') === '') {
                            formData.set('selected_color_id', '1');
                        }
                        if (!formData.get('selected_color_name') || formData.get('selected_color_name') === '') {
                            formData.set('selected_color_name', 'Default');
                        }
                        if (!formData.get('color_price')) {
                            formData.set('color_price', '0');
                        }

                        const response = await fetch('../cart/add_to_cart', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            showNotification(data.message || 'Product added to cart!', 'success');
                            updateCartCount(data.cart_count);

                            // Success feedback
                            button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Added!';
                            button.className = button.className.replace('bg-orange-500 hover:bg-orange-600', 'bg-green-500');

                            // Reset after 2 seconds
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

                        // Reset button
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                });
            });
        });

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            } [type] || 'bg-blue-500';

            notification.className = `fixed top-4 left-1/2 -translate-x-1/2 p-4 rounded-lg z-50 ${bgColor} text-white shadow-lg transform transition-all duration-300
`;
            notification.textContent = message;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Animate out and remove
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        function updateCartCount(count) {
            const cartCountElements = document.querySelectorAll('.cart-count, #cart-count, [data-cart-count]');
            cartCountElements.forEach(element => {
                element.textContent = count;
                element.style.display = count > 0 ? 'inline' : 'none';
            });

            const cartBubble = document.getElementById('cart-count-bubble');
            if (cartBubble) {
                if (count > 0) {
                    cartBubble.classList.remove('hidden');
                    cartBubble.style.display = 'inline';
                } else {
                    cartBubble.classList.add('hidden');
                    cartBubble.style.display = 'none';
                }
            }
        }


        function openChat() {
            document.getElementById('chat-box').style.display = 'block';
            document.getElementById('chat-toggle').style.display = 'none';
        }

        function closeChat() {
            document.getElementById('chat-box').style.display = 'none';
            document.getElementById('chat-toggle').style.display = 'inline-block';
        }



        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Check if Swiper is available
            if (typeof Swiper === 'undefined') {
                console.error('Swiper library is not loaded. Please include Swiper CSS and JS files.');
                return;
            }

            // Get all swiper containers
            const swiperContainers = document.querySelectorAll('[class*="swiper-auto-"]');

            swiperContainers.forEach((container, index) => {
                const slides = container.querySelectorAll('.swiper-slide');
                const slideCount = slides.length;

                if (slideCount > 0) {
                    const swiper = new Swiper(container, {
                        direction: 'vertical',
                        loop: slideCount > 1, // Only loop if more than 1 slide
                        slidesPerView: 1,
                        spaceBetween: 0,
                        autoplay: slideCount > 1 ? {
                            delay: 3000 + (index * 500), // Longer delay for smoother experience
                            disableOnInteraction: false,
                            pauseOnMouseEnter: false,
                            waitForTransition: true, // Wait for transition to complete
                        } : false,
                        speed: 1000, // Slower transition for smoothness
                        // Remove fade effect for smoother vertical sliding
                        effect: 'slide',
                        on: {
                            init: function() {
                                console.log(`Swiper ${index} initialized with ${slideCount} slides`);
                            },
                            slideChange: function() {
                                console.log(`Swiper ${index} slide changed`);
                            }
                        }
                    });
                }
            });
        });