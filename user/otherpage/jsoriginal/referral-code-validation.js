// Referral Code Validation System
class ReferralCodeValidator {
    constructor() {
        this.init();
    }

    init() {
        const input = document.getElementById('referralCodeInput');
        const applyBtn = document.getElementById('applyReferralBtn');

        if (!input || !applyBtn) {
            console.warn('Referral code elements not found');
            return;
        }

        // Auto-format to uppercase
        input.addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase();
        });

        // Apply button click
        applyBtn.addEventListener('click', () => this.validateCode());

        // Enter key to apply
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.validateCode();
            }
        });

        console.log('✓ Referral code validator initialized');
    }

    async validateCode() {
    const input = document.getElementById('referralCodeInput');
    const statusDiv = document.getElementById('referralStatus');
    const code = input.value.trim();

    if (!code) {
        this.showStatus('Please enter a referral code', 'error');
        return;
    }

    this.showStatus('Verifying code...', 'loading');

    try {
        const response = await fetch('validate-referral-code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `referral_code=${encodeURIComponent(code)}`
        });

        const data = await response.json();

        if (data.valid) {
            this.showStatus(
                `✓ Valid code! You'll get ${data.discount_display} discount`, 
                'success'
            );
            
            // Create and submit form to apply referral code
            setTimeout(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                
                const codeInput = document.createElement('input');
                codeInput.type = 'hidden';
                codeInput.name = 'referral_code';
                codeInput.value = code;
                
                const applyInput = document.createElement('input');
                applyInput.type = 'hidden';
                applyInput.name = 'apply_referral_only';
                applyInput.value = '1';
                
                form.appendChild(codeInput);
                form.appendChild(applyInput);
                document.body.appendChild(form);
                form.submit();
            }, 1000);
        } else {
            this.showStatus(data.message || 'Invalid or inactive referral code', 'error');
        }
    } catch (error) {
        console.error('Validation error:', error);
        this.showStatus('Error validating code. Please try again.', 'error');
    }
}

    showStatus(message, type) {
        const statusDiv = document.getElementById('referralStatus');
        if (!statusDiv) return;

        const colors = {
            success: 'bg-green-100 border-green-400 text-green-700',
            error: 'bg-red-100 border-red-400 text-red-700',
            loading: 'bg-blue-100 border-blue-400 text-blue-700'
        };

        const icons = {
            success: '<i class="fas fa-check-circle mr-2"></i>',
            error: '<i class="fas fa-exclamation-circle mr-2"></i>',
            loading: '<i class="fas fa-spinner fa-spin mr-2"></i>'
        };

        statusDiv.className = `border-2 rounded-lg p-3 ${colors[type] || colors.loading}`;
        statusDiv.innerHTML = `${icons[type] || ''}${message}`;
        statusDiv.classList.remove('hidden');

        if (type === 'success' || type === 'error') {
            setTimeout(() => {
                if (type === 'error') {
                    statusDiv.classList.add('hidden');
                }
            }, 5000);
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new ReferralCodeValidator();
    });
} else {
    new ReferralCodeValidator();
}