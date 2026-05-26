<?php
// user/navbar/partials/login-modal.php
?>

<!-- ========== FULLSCREEN LOGIN MODAL (all screen sizes) ========== -->
<?php if (!isset($_SESSION['user_name'])): ?>
    <div x-show="loginOpen" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/50 p-4"
        @click.self="loginOpen = false">

        <div x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="bg-white w-full max-w-md max-h-[95vh] overflow-y-auto rounded-lg shadow-2xl relative">

            <!-- Header -->
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between z-10">
                <h2 class="text-xl font-bold text-gray-800">Login</h2>
                <button @click="loginOpen = false"
                    class="text-gray-500 hover:text-gray-800 text-2xl font-bold p-1 leading-none">&times;</button>
            </div>

            <!-- Form -->
            <div class="p-6">
                <form x-data="loginForm()" @submit.prevent="handleLogin($event)" class="space-y-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">Email or Mobile</label>
                        <input type="text" name="login" x-model="loginInput" @input="checkLoginType" autocomplete="off"
                            placeholder="you@example.com or 09123456789" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>

                    <div x-show="(isMobile) || (isEmail && otpVerified)" x-transition class="space-y-2">
                        <label class="block text-sm font-medium text-gray-600">Password</label>
                        <input type="password" name="password" x-model="password" autocomplete="off"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>

                    <div x-show="isEmail && !otpSent && !otpVerified" x-transition class="space-y-2">
                        <label class="block text-sm font-medium text-gray-600">OTP Verification</label>
                        <button type="button" @click="sendOTP" :disabled="otpLoading || resendCooldown > 0"
                            class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center space-x-2">
                            <template x-if="!otpLoading && resendCooldown === 0"><span>Send OTP</span></template>
                            <template x-if="otpLoading">
                                <div class="flex items-center space-x-2">
                                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <span>Verifying...</span>
                                </div>
                            </template>
                            <template x-if="!otpLoading && resendCooldown > 0">
                                <span>Resend in <span x-text="resendCooldown"></span>s</span>
                            </template>
                        </button>
                    </div>

                    <div x-show="otpSent && !otpVerified" x-transition class="space-y-3">
                        <label class="block text-sm font-medium text-gray-600 mb-2">Enter OTP</label>
                        <p class="text-xs text-gray-500">We sent a verification code to your email</p>
                        <input type="text" x-model="otp" maxlength="6"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-center text-lg tracking-widest"
                            placeholder="000000">
                        <div class="flex gap-3">
                            <button type="button" @click="cancelOTP"
                                class="flex-1 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium">Cancel</button>
                            <button type="button" @click="verifyOTP" :disabled="!otp || otp.length < 4"
                                class="flex-1 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:bg-orange-300 font-medium">
                                Verify
                            </button>
                        </div>
                        <div class="text-center mt-2">
                            <template x-if="resendCooldown > 0">
                                <p class="text-sm text-gray-500">Resend in <span x-text="resendCooldown"></span>s</p>
                            </template>
                            <template x-if="resendCooldown === 0">
                                <button @click="sendOTP" class="text-blue-500 hover:underline text-sm font-medium"
                                    type="button">Resend OTP</button>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-2" x-show="isMobile">
                        <input type="checkbox" id="remember_me" name="remember" class="h-4 w-4 text-orange-500 rounded">
                        <label for="remember_me" class="text-sm text-gray-600">Remember me</label>
                    </div>

                    <button type="submit" :disabled="submitLoading"
                        class="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-orange-300 text-white font-semibold py-3 px-4 rounded-lg transition"
                        x-show="(isMobile) || (isEmail && otpVerified)">
                        <span x-show="!submitLoading">Log In</span>
                        <span x-show="submitLoading">Logging in...</span>
                    </button>

                    <div x-show="errorMessage" x-transition
                        class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                        <span x-text="errorMessage"></span>
                    </div>
                    <div x-show="successMessage" x-transition
                        class="p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                        <span x-text="successMessage"></span>
                    </div>

                    <div class="text-center space-y-2 pt-4 border-t border-gray-200">
                        <div>
                            <a href="<?= BASE_URL ?>/forgotpass"
                                class="text-orange-500 hover:underline text-sm font-medium">Forgot password?</a>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Don't have an account? </span>
                            <a href="#" @click.prevent="registerOpen = true; loginOpen = false"
                                class="text-orange-500 hover:underline font-medium text-sm">Register</a>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <a href="javascript:void(0)" onclick="openGooglePopup('<?= BASE_URL ?>/login')"
                            class="inline-flex items-center justify-center w-full gap-3 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-lg transition">
                            <svg class="w-5 h-5 bg-white rounded-full p-[2px]" viewBox="0 0 48 48">
                                <path fill="#EA4335"
                                    d="M24 9.5c3.5 0 6.3 1.2 8.3 3.2l6.2-6.2C34.8 2.6 29.7 0 24 0 14.8 0 6.8 5.9 3.2 14.1l7.3 5.7C12.7 13.2 17.9 9.5 24 9.5z" />
                                <path fill="#34A853"
                                    d="M24 48c6.5 0 12-2.1 16.1-5.7l-7.4-6.1C30.5 38.7 27.5 40 24 40c-6 0-11.2-3.7-13.4-8.8l-7.2 5.5C6.3 43.8 14.6 48 24 48z" />
                                <path fill="#FBBC05"
                                    d="M43.6 20H24v8.4h11.3c-1.1 3.2-3.4 5.8-6.5 7.6l7.4 6.1c4.3-4 6.8-9.9 6.8-17.1 0-1.2-.1-2.3-.3-3.4z" />
                                <path fill="#4285F4"
                                    d="M10.6 29.6C9.7 27.2 9.2 24.7 9.2 22s.5-5.2 1.4-7.6l-7.4-5.7C1.1 13.6 0 17.7 0 22c0 4.2 1.1 8.3 3.2 11.8l7.4-4.2z" />
                            </svg>
                            Login with Google
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
<?php endif; ?>