function loginForm() {
    return {
        // User input
        loginInput: '',
        password: '',
        otp: '',

        // State flags
        isMobile: false,
        isEmail: false,
        isTrustedDevice: false,
        otpSent: false,
        otpVerified: false,
        otpLoading: false,
        submitLoading: false,

        // Feedback
        errorMessage: '',
        successMessage: '',
        messageTimeout: null,

        // Resend cooldown
        resendCooldown: 0,
        resendTimer: null,
        cooldownDuration: 20,

        // Initialize
        init() {
            this.resumeCooldown();
            const cookies = document.cookie.split(';');
            this.isTrustedDevice = cookies.some(c => c.trim().startsWith('otp_trust_token='));
        },

        // Detect if input is email or mobile
        checkLoginType() {
            const mobilePattern = /^09\d{9}$/;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            this.isMobile = mobilePattern.test(this.loginInput);
            this.isEmail = emailPattern.test(this.loginInput);

            if (this.isEmail) {
                const cookies = document.cookie.split(';');
                this.isTrustedDevice = cookies.some(c => c.trim().startsWith('otp_trust_token='));
            } else {
                this.isTrustedDevice = false;
            }

            if (!this.isTrustedDevice) {
                this.resetOTPStates();
            } else {
                this.otpSent = false;
                this.otpVerified = false;
                this.otp = '';
                this.errorMessage = '';
                this.successMessage = '';
            }
        },

        // Reset OTP related states
        resetOTPStates() {
            this.otpSent = false;
            this.otpVerified = false;
            this.errorMessage = '';
            this.successMessage = '';
            this.otp = '';
            this.password = '';
            this.resendCooldown = 0;
            if (this.resendTimer) {
                clearInterval(this.resendTimer);
            }
        },

        // Send OTP to email
        sendOTP() {
            if (this.resendCooldown > 0) return;

            this.otpLoading = true;
            this.errorMessage = '';
            this.successMessage = '';

            fetch(BASE_URL + '/sendotp', {
                method: 'POST',
                body: JSON.stringify({ email: this.loginInput }),
                headers: { 'Content-Type': 'application/json' }
            })
                .then(res => res.json())
                .then(data => {
                    this.otpLoading = false;
                    if (data.success) {
                        this.otpSent = true;
                        this.successMessage = data.message || 'OTP sent successfully';
                        this.startResendCooldown();
                    } else {
                        this.errorMessage = data.message || 'Failed to send OTP';
                    }
                    this.clearMessages();
                })
                .catch(() => {
                    this.otpLoading = false;
                    this.errorMessage = 'Network error while sending OTP.';
                    this.clearMessages();
                });
        },

        // Verify OTP
        verifyOTP() {
            if (!this.otp || this.otp.length < 4) {
                this.errorMessage = 'Please enter a valid OTP';
                this.clearMessages();
                return;
            }

            this.submitLoading = true;
            this.errorMessage = '';
            this.successMessage = '';

            fetch(BASE_URL + '/verifyotp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: this.loginInput,
                    otp: this.otp
                })
            })
                .then(res => res.json())
                .then(data => {
                    this.submitLoading = false;

                    if (data.success) {
                        // I-set ang trust token cookie
                        if (data.trust_token) {
                            const expires = new Date();
                            expires.setDate(expires.getDate() + 30);
                            document.cookie = `otp_trust_token=${data.trust_token}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
                            this.isTrustedDevice = true;
                        }

                        this.successMessage = 'OTP verified! Please enter your password.';
                        this.otpVerified = true;
                        this.otpSent = false;
                        this.otp = '';
                        this.resendCooldown = 0;
                        if (this.resendTimer) {
                            clearInterval(this.resendTimer);
                        }
                    } else {
                        this.errorMessage = data.message || 'Invalid OTP.';
                    }

                    this.clearMessages();
                })
                .catch(() => {
                    this.submitLoading = false;
                    this.errorMessage = 'Network error occurred.';
                    this.clearMessages();
                });
        },

        // Cancel OTP process
        cancelOTP() {
            this.resetOTPStates();
        },

        // Handle form submission
        handleLogin(event) {
            if (this.isEmail && !this.otpVerified && !this.isTrustedDevice) {
                this.errorMessage = 'Please verify your email with OTP first.';
                this.clearMessages();
                return;
            }

            if (!this.password) {
                this.errorMessage = 'Please enter your password.';
                this.clearMessages();
                return;
            }

            this.submitLoading = true;
            this.errorMessage = '';
            this.successMessage = '';

            const formElement = event.target.closest('form');
            const formData = new FormData(formElement);

            fetch(BASE_URL + '/logins', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    this.submitLoading = false;
                    if (data.success) {
                        this.successMessage = 'Login successful!';
                        setTimeout(() => {
                            window.location.href = data.redirect || '../otherpage/index.php';
                        }, 1000);
                    } else {
                        this.errorMessage = data.message || 'Login failed.';
                        this.clearMessages();
                    }
                })
                .catch(() => {
                    this.submitLoading = false;
                    this.errorMessage = 'Network error occurred.';
                    this.clearMessages();
                });
        },

        // Start resend cooldown timer
        startResendCooldown() {
            this.resendCooldown = this.cooldownDuration;
            const expiresAt = Date.now() + this.cooldownDuration * 1000;
            this.cooldownExpiry = expiresAt;

            if (this.resendTimer) clearInterval(this.resendTimer);
            this.resendTimer = setInterval(() => {
                this.resendCooldown--;
                if (this.resendCooldown <= 0) {
                    clearInterval(this.resendTimer);
                    this.cooldownExpiry = null;
                }
            }, 1000);
        },

        // Resume cooldown on page reload
        resumeCooldown() {
            if (this.cooldownExpiry) {
                const remaining = Math.ceil((this.cooldownExpiry - Date.now()) / 1000);
                if (remaining > 0) {
                    this.resendCooldown = remaining;
                    this.otpSent = true;
                    this.startResendCooldown();
                } else {
                    this.cooldownExpiry = null;
                }
            }
        },

        // Auto-hide messages after 3 seconds
        clearMessages() {
            if (this.messageTimeout) clearTimeout(this.messageTimeout);
            this.messageTimeout = setTimeout(() => {
                this.successMessage = '';
                this.errorMessage = '';
            }, 3000);
        }
    };
}