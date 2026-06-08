<?php

include ROOT_PATH . '/connection/connect.php';

function updateUserActivity($conn, $email)
{
    $stmt = $conn->prepare("UPDATE nobleaccount SET last_activity = NOW(), is_online = 1, last_login = NOW() WHERE email = ?");
    $stmt->bind_param("s", $email);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function determineRedirect($userLevel)
{
    return match (strtolower($userLevel)) {
        'superadmin', 'admin' => BASE_URL . "/admin/client/owner_dashboard",
        'sales' => BASE_URL . "/ordermain",
        'accountant' => BASE_URL . "/accountant",
        'supplier' => BASE_URL . "/admin/suppliermain/suppliercompany",
        'productspecialist' => BASE_URL . "/admin/shop/main-adminshop-page-1",
        'logistic' => BASE_URL . "/logistic",
        'warehouse' => BASE_URL . "/warehousedashboard",
        'hr' => BASE_URL . "/admin/hr/account",
        default => BASE_URL . "/admin/client/dashboard.php"
    };
}

// ✅ FIXED: Auto-login check with better error handling
if (!isset($_SESSION['noble_user']) && isset($_COOKIE['noble_remember_token']) && isset($_COOKIE['noble_remember_email'])) {

    $remember_token = $_COOKIE['noble_remember_token'];
    $remember_email = $_COOKIE['noble_remember_email'];

    try {
        // ✅ Use a single optimized query
        $stmt = $conn->prepare("SELECT id, email, lvl, status FROM nobleaccount WHERE email = ? AND remember_token = ? AND remember_expires > NOW() AND status = 'active' LIMIT 1");
        $stmt->bind_param("ss", $remember_email, $remember_token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // ✅ FIXED: Update activity without additional queries
            if (updateUserActivity($conn, $remember_email)) {

                // Set minimal session data
                $_SESSION['noble_user'] = $user['email'];
                $_SESSION['noble_lvl'] = $user['lvl'];
                $_SESSION['noble_id'] = $user['id'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                $_SESSION['session_expires'] = time() + 86400;
                $_SESSION['is_online'] = true;
                $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['remember_me'] = true;

                $redirect = determineRedirect($user['lvl']);

                error_log("Auto-login successful for user: " . $remember_email);

                // ✅ Close connection before redirect
                $stmt->close();
                $conn->close();

                header("Location: " . BASE_URL . "/" . ltrim($redirect, '/'));
                exit();
            }
        } else {
            // ✅ Clear invalid cookies
            setcookie('noble_remember_token', '', time() - 3600, '/');
            setcookie('noble_remember_email', '', time() - 3600, '/');
        }

        $stmt->close();

    } catch (Exception $e) {
        error_log("Auto-login error: " . $e->getMessage());
        // Clear cookies on error
        setcookie('noble_remember_token', '', time() - 3600, '/');
        setcookie('noble_remember_email', '', time() - 3600, '/');
    }
}

// ✅ FIXED: Regular session check with immediate redirect
if (isset($_SESSION['noble_user'])) {
    $role = strtolower($_SESSION['noble_lvl'] ?? '');
    $redirect = determineRedirect($role);

    // ✅ Close connection before redirect
    $conn->close();

    header("Location: $redirect");
    exit();
}

// ✅ FIXED: Less aggressive cleanup - only run once per day per session
if (!isset($_SESSION['last_cleanup']) || (time() - $_SESSION['last_cleanup']) > 86400) {
    try {
        // ✅ Increased timeout to 4 hours to reduce interference
        $cleanup = $conn->prepare("UPDATE nobleaccount SET is_online = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 4 HOUR)");
        $cleanup->execute();
        $cleanup->close();
        $_SESSION['last_cleanup'] = time();
    } catch (Exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
    }
}

// ✅ Close connection at the end
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Portal - Professional Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
</head>

<body class="min-h-screen flex items-center justify-center p-4 relative"
    style="background-image: url('<?= BASE_URL ?>/uploads/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">

    <div id="toast-container" class="fixed top-5 right-5 z-[1000] flex flex-col gap-2 w-80"></div>

    <!-- ✅ Force Login Modal -->
    <div id="forceLoginModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay absolute inset-0"></div>
        <div class="relative z-10 flex items-center justify-center min-h-full p-4">
            <div class="modal-content bg-white rounded-xl shadow-2xl p-6 max-w-md w-full slide-in">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                        <i class="ri-error-warning-line text-yellow-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Account Already Active</h3>
                    <p id="forceLoginMessage" class="text-sm text-gray-600 mb-6">This account is currently active on
                        another device.</p>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
                        <div class="flex items-start">
                            <i class="ri-information-line text-blue-500 mt-0.5 mr-2"></i>
                            <p class="text-xs text-blue-700">Continuing will log out the other session and may disrupt
                                any ongoing work.</p>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <button type="button" onclick="cancelForceLogin()"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors">
                            Cancel
                        </button>
                        <button type="button" onclick="doForceLogin()"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors">
                            Force Login
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dark Overlay -->
    <div class="absolute inset-0" style="background: rgba(0, 0, 0, 0.55); "></div>

    <!-- Subtle Grid Pattern on top of overlay -->
    <div class="absolute inset-0 grid-pattern" style="opacity: 0.08;"></div>


    <!-- Main Container -->
    <div class="relative z-10 w-full max-w-md">

        <!-- Logo and Company Section -->
        <div class="text-center mb-8 fade-in-up">
            <div class="inline-flex items-center justify-center w-[130px] h-[60px] logo-animation  p-1">
                <img src="<?= BASE_URL ?>/user/img/logo.png" alt="Logo" class="w-full h-full object-contain " />
            </div>

            <h1 class="text-2xl font-semibold text-white mb-1">Admin panel</h1>
            <p class="text-white">Secure access to your dashboard</p>
        </div>

        <!-- Login Card -->
        <div class=" p-8 fade-in-up" style="animation-delay: 0.1s">

            <!-- Form Header -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-white mb-1 ">Sign in to your account</h2>
                <p class="text-sm text-white ">Please enter your credentials to continue</p>
            </div>

            <!-- Error Message -->
            <div id="error-message" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-start">
                    <i class="ri-error-warning-line text-red-500 mt-0.5 mr-2"></i>
                    <p class="text-sm text-red-700" id="error-text"></p>
                </div>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="space-y-5" method="POST" action="<?= BASE_URL ?>/loggingadmin">

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-1">
                        Email Address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="ri-mail-line text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 input-focus"
                            placeholder="john.doe@company.com" required autocomplete="email" />
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-white mb-1">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="ri-lock-line text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password"
                            class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 input-focus"
                            placeholder="Enter your password" required autocomplete="current-password" />
                        <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <i id="passwordIcon" class="ri-eye-off-line"></i>
                        </button>
                    </div>
                </div>

                <!--  Enhanced Remember Me with better styling -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember_me" type="checkbox" value="1"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-white">
                            Remember me for 30 days
                        </label>
                    </div>
                </div>

                <!-- Hidden field for force login -->
                <input type="hidden" name="force_login" id="force_login" value="0">

                <!-- Submit Button -->
                <button type="submit" id="submitBtn"
                    class="w-full text-white py-3 px-4 rounded-lg font-medium btn-primary focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <span id="btnText" class="flex items-center justify-center">
                        <i class="ri-login-circle-line mr-2"></i>
                        Sign In
                    </span>
                    <span id="btnLoading" class="hidden flex items-center justify-center">
                        <i class="ri-loader-4-line mr-2 spinner"></i>
                        Signing In...
                    </span>
                </button>

                <!-- Security Notice -->
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-center text-xs text-white">
                        <i class="ri-shield-check-line mr-1"></i>
                        <span>Secured with 256-bit SSL encryption</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-sm text-white fade-in-up" style="animation-delay: 0.2s">
            <p>©<?php echo date('Y'); ?> Your Company. All rights reserved.</p>
        </div>
    </div>

    <script>
        //  Auto-fill remembered email on page load
        document.addEventListener('DOMContentLoaded', function () {
            const rememberedEmail = localStorage.getItem('noble_remembered_email');
            if (rememberedEmail && !document.getElementById('email').value) {
                document.getElementById('email').value = rememberedEmail;
            }
        });

        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'ri-eye-line';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'ri-eye-off-line';
            }
        });

        //  Enhanced form submission with force login support
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const form = this;
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            const errorMessage = document.getElementById('error-message');

            // Clear previous errors
            hideError();

            // Disable button and show loading
            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            btnLoading.classList.remove('hidden');

            // Create FormData
            const formData = new FormData(form);

            //  Save email to localStorage if remember me is checked
            if (document.getElementById('remember_me').checked) {
                localStorage.setItem('noble_remembered_email', document.getElementById('email').value);
            } else {
                localStorage.removeItem('noble_remembered_email');
            }

            // Send AJAX request
            fetch('<?= BASE_URL ?>/loggingadmin', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    } else if (data.force_login_required) {
                        //  Show force login modal instead of error
                        showForceLoginModal(data.message, data.inactive_minutes);
                    } else {
                        showError(data.message);
                        // Add shake animation to form
                        document.querySelector('.bg-white.rounded-2xl').classList.add('error-shake');
                        setTimeout(() => {
                            document.querySelector('.bg-white.rounded-2xl').classList.remove('error-shake');
                        }, 500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An unexpected error occurred. Please try again.');
                })
                .finally(() => {
                    // Re-enable button
                    submitBtn.disabled = false;
                    btnText.classList.remove('hidden');
                    btnLoading.classList.add('hidden');
                    // Reset force login flag
                    document.getElementById('force_login').value = '0';
                });
        });

        //  Force Login Modal Functions
        function showForceLoginModal(message, inactiveMinutes) {
            const modal = document.getElementById('forceLoginModal');
            const messageElement = document.getElementById('forceLoginMessage');

            messageElement.textContent = message + (inactiveMinutes ? ` (Inactive for ${inactiveMinutes} minutes)` : '');
            modal.classList.remove('hidden');

            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function doForceLogin() {
            document.getElementById('force_login').value = '1';
            hideForceLoginModal();
            document.getElementById('loginForm').dispatchEvent(new Event('submit'));
        }

        function cancelForceLogin() {
            document.getElementById('force_login').value = '0';
            hideForceLoginModal();
        }

        function hideForceLoginModal() {
            const modal = document.getElementById('forceLoginModal');
            modal.classList.add('hidden');

            // Restore body scroll
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.getElementById('forceLoginModal').addEventListener('click', function (e) {
            if (e.target === this) {
                cancelForceLogin();
            }
        });

        // Error handling functions
        function showError(message) {
            const errorMessage = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');

            errorText.textContent = message;
            errorMessage.classList.remove('hidden');

            // Auto-hide after 5 seconds
            setTimeout(hideError, 5000);
        }

        function hideError() {
            document.getElementById('error-message').classList.add('hidden');
        }

        // Input validation feedback
        document.getElementById('email').addEventListener('blur', function () {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.add('border-red-300');
                this.classList.remove('border-gray-300');
            } else {
                this.classList.remove('border-red-300');
                this.classList.add('border-gray-300');
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Prevent double submission
        let isSubmitting = false;
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;

            // Reset flag after 3 seconds (failsafe)
            setTimeout(() => {
                isSubmitting = false;
            }, 3000);
        });

        //  Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Escape key to close modal
            if (e.key === 'Escape') {
                if (!document.getElementById('forceLoginModal').classList.contains('hidden')) {
                    cancelForceLogin();
                }
            }

            // Enter key in modal to force login
            if (e.key === 'Enter' && !document.getElementById('forceLoginModal').classList.contains('hidden')) {
                doForceLogin();
            }
        });

        const toastConfig = {
    success: {
        icon: 'ri-checkbox-circle-line',
        containerClass: 'border-green-200 bg-white',
        iconClass: 'text-green-500',
        barClass: 'bg-green-500',
        labelClass: 'text-green-700',
        label: 'Success'
    },
    error: {
        icon: 'ri-error-warning-line',
        containerClass: 'border-red-200 bg-white',
        iconClass: 'text-red-500',
        barClass: 'bg-red-500',
        labelClass: 'text-red-700',
        label: 'Error'
    },
    warning: {
        icon: 'ri-alert-line',
        containerClass: 'border-yellow-200 bg-white',
        iconClass: 'text-yellow-500',
        barClass: 'bg-yellow-500',
        labelClass: 'text-yellow-700',
        label: 'Warning'
    },
    info: {
        icon: 'ri-information-line',
        containerClass: 'border-blue-200 bg-white',
        iconClass: 'text-blue-500',
        barClass: 'bg-blue-500',
        labelClass: 'text-blue-700',
        label: 'Info'
    }
};

function showToast(message, type = 'error', duration = 4000) {
    const config = toastConfig[type] || toastConfig.error;
    const container = document.getElementById('toast-container');
    const id = 'toast-' + Date.now();

    const toast = document.createElement('div');
    toast.id = id;
    toast.className = `relative overflow-hidden rounded-lg border shadow-lg translate-x-full opacity-0 transition-all duration-300 ease-in-out ${config.containerClass}`;

    toast.innerHTML = `
        <div class="flex items-start gap-3 p-4">
            <i class="${config.icon} ${config.iconClass} text-xl mt-0.5 shrink-0"></i>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide ${config.labelClass} mb-0.5">${config.label}</p>
                <p class="text-sm text-gray-700 leading-snug">${message}</p>
            </div>
            <button onclick="dismissToast('${id}')" class="text-gray-400 hover:text-gray-600 transition-colors shrink-0 -mt-0.5">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
        <div class="absolute bottom-0 left-0 h-0.5 w-full ${config.barClass} origin-left" id="${id}-bar"></div>
    `;

    container.appendChild(toast);

    // Slide in
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        });
    });

    // Progress bar animation
    const bar = document.getElementById(`${id}-bar`);
    bar.style.transition = `transform ${duration}ms linear`;
    bar.style.transformOrigin = 'left';

    let paused = false;
    let remaining = duration;
    let startTime = Date.now();
    let timer;

    function startTimer() {
        startTime = Date.now();
        timer = setTimeout(() => dismissToast(id), remaining);
        bar.style.transform = 'scaleX(0)';
    }

    // Pause on hover
    toast.addEventListener('mouseenter', () => {
        clearTimeout(timer);
        remaining -= Date.now() - startTime;
        bar.style.transition = 'none';
        const currentScale = remaining / duration;
        bar.style.transform = `scaleX(${currentScale})`;
    });

    toast.addEventListener('mouseleave', () => {
        bar.style.transition = `transform ${remaining}ms linear`;
        startTimer();
    });

    startTimer();
}

function dismissToast(id) {
    const toast = document.getElementById(id);
    if (!toast) return;

    toast.classList.add('translate-x-full', 'opacity-0');
    setTimeout(() => toast.remove(), 300);
}

function hideToast() {
    // Legacy fallback — dismisses all toasts
    document.querySelectorAll('#toast-container > div').forEach(t => dismissToast(t.id));
}
    </script>

</body>

</html>