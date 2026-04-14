<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

<style>
    footer * {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .footer-link {
        position: relative;
        display: inline-block;
        transition: color 0.2s;
    }

    .footer-link::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -2px;
        width: 0;
        height: 1.5px;
        background: #ef4444;
        transition: width 0.25s ease;
    }

    .footer-link:hover {
        color: #ef4444;
    }

    .social-btn {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        background: #fff;
    }

    .social-btn:hover {
        border-color: #f97316;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15);
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.94);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .modal-animate {
        animation: fadeInScale 0.2s ease-out;
    }
</style>

<footer class="bg-white text-gray-800 pt-5 pb-8 relative overflow-hidden">

    <!-- Top divider -->
    <div class="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent mb-12"></div>

    <div class="max-w-7xl mx-auto px-6">

        <!-- Main grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">

            <!-- Branding -->
            <div class="lg:col-span-2">
                <div class="flex items-center gap-3 mb-5">
                    <div
                        class="w-12 h-12 bg-white rounded-xl border border-gray-100 shadow-sm flex items-center justify-center overflow-hidden">
                        <img src="../img/logo.png" alt="Noble Home" class="w-8 h-8 object-contain" />
                    </div>
                    <span class="text-lg font-700 text-[#2f1200]" style="font-weight:700;">Noble Home</span>
                </div>

                <p class="text-sm leading-relaxed text-gray-500 max-w-sm mb-6">
                    Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home
                    awaits with our expert construction and design services.
                </p>

                <div class="space-y-2.5 text-sm text-gray-600">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-orange-50 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                            </svg>
                        </span>
                        <span>noblehomeconst.ph@gmail.com</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-orange-50 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                            </svg>
                        </span>
                        <span>09922394563 / (02) 8822-1295</span>
                    </div>
                </div>
            </div>

            <!-- Company Info -->
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-[#2f1200] mb-5">Company Info</h3>
                <div class="flex flex-col gap-3 text-sm text-gray-500">
                    <a href="../rules/terms.php" class="footer-link">Terms of use</a>
                    <a href="../rules/policy.php" class="footer-link">Privacy Policy</a>
                    <a href="../about/about.php" class="footer-link">About Noblehome</a>
                    <a href="../rules/customer-services.php" class="footer-link">Help Center</a>
                </div>
            </div>

            <!-- Customer Services -->
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-[#2f1200] mb-5">Customer Services</h3>
                <div class="flex flex-col gap-3 text-sm text-gray-500">
                    <a href="../rules/return-policy.php" class="footer-link">Return Policy</a>
                    <a href="../rules/payment.php" class="footer-link">Payment Policy</a>
                    <a href="#" class="footer-link">Shipping Policy</a>
                </div>
            </div>
        </div>

        <!-- Bottom divider -->
        <div class="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent mb-8"></div>

        <!-- Bottom row -->
        <div class="flex flex-col lg:flex-row justify-between items-center gap-6">

            <!-- Copyright -->
            <div class="text-center lg:text-left">
                <p class="text-sm text-gray-600">© <?= date('Y') ?> Noble Home Construction. All rights reserved.</p>
            </div>

            <!-- Socials -->
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-400 mr-1">Follow us:</span>

                <!-- Facebook -->
                <a href="https://www.facebook.com/noblehomedepotph" class="social-btn" aria-label="Facebook">
                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
                    </svg>
                </a>

                <!-- Instagram -->
                <a href="https://www.instagram.com/noblehome_depot" class="social-btn" aria-label="Instagram">
                    <svg class="w-4 h-4 text-pink-500" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
                    </svg>
                </a>

                <!-- WeChat -->
                <button id="wechatBtn" class="social-btn" aria-label="WeChat">
                    <i class="fab fa-weixin text-base text-green-500"></i>
                </button>

                <!-- Viber -->
                <button id="viberBtn" class="social-btn" aria-label="Viber">
                    <i class="fab fa-viber text-base text-purple-500"></i>
                </button>

                <!-- WhatsApp -->
                <button id="whatsappBtn" class="social-btn" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp text-base text-green-600"></i>
                </button>
            </div>

            <!-- Back to top -->
            <button onclick="window.scrollTo({top:0,behavior:'smooth'})"
                class="w-10 h-10 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-200 hover:scale-105 shadow-md shadow-orange-100"
                aria-label="Back to top">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
            </button>
        </div>
    </div>
</footer>

<!-- QR Modal -->
<div id="socialModal" style="display:none;"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
    <div class="modal-animate bg-white rounded-2xl shadow-xl p-6 w-72 relative">
        <button id="closeModal"
            class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold leading-none">&times;</button>
        <h2 id="modalTitle" class="text-base font-600 text-gray-800 mb-4 text-center"
            style="font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;"></h2>
        <div class="flex flex-col items-center gap-3">
            <img id="modalImage" src="" alt="QR Code"
                class="rounded-xl w-48 h-48 object-contain border border-gray-100" />
            <p class="text-xs text-gray-400">Scan this QR code to start chatting</p>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('socialModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalImage = document.getElementById('modalImage');
        function openModal(title, img) {
            modalTitle.textContent = title;
            modalImage.src = img;
            modal.style.display = 'flex'; // ✅ flex, hindi classList.remove('hidden')
        }
        function closeModal() {
            modal.style.display = 'none'; // ✅
        }

        document.getElementById('wechatBtn')?.addEventListener('click', () => openModal('Chat with us on WeChat', '../img/wechat.png'));
        document.getElementById('viberBtn')?.addEventListener('click', () => openModal('Chat with us on Viber', '../img/viber.png'));
        document.getElementById('whatsappBtn')?.addEventListener('click', () => openModal('Chat with us on WhatsApp', '../img/whatapp.jpg'));
        document.getElementById('closeModal')?.addEventListener('click', closeModal);
        modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    })();
</script>