// index-checkout-paymentquickFixPayment-page-12-4.js - FIXED VERSION
console.log('Loading Complete Payment System with Dynamic Validation...');

// Notification system
function showNotification(message, type = 'info', duration = 5000) {
    console.log(`${type.toUpperCase()}: ${message}`);
    
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 9999;
        padding: 12px 20px; border-radius: 8px; color: white;
        font-weight: bold; max-width: 300px; word-wrap: break-word;
    `;

    const colors = {
        success: '#10B981',
        error: '#EF4444', 
        warning: '#F59E0B',
        info: '#3B82F6'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, duration);
    }
}

window.showNotification = showNotification;

// Payment Management Class
class PaymentSystem {
    constructor() {
        this.initialized = false;
        this.isSubmitting = false;
    }

    initialize() {
        if (this.initialized) return;
        
        console.log('Initializing Complete Payment System...');
        
        this.setupPaymentMethodSwitching();
        this.setupFormSubmission();
        this.initialized = true;
        
        showNotification('Payment system ready', 'success', 2000);
    }

    setupPaymentMethodSwitching() {
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.switchPaymentMethod(e.target.value);
            });
        });
    }

    // ✅ FIX: Dynamically manage 'required' attribute based on visibility
    switchPaymentMethod(method) {
        console.log('Switching to payment method:', method);
        
        // Hide all payment fields
        const bankFields = document.getElementById('bankTransferFields');
        const paypalFields = document.getElementById('paypalFields'); 
        const paymongoFields = document.getElementById('paymongoFields');
        const qrFields = document.getElementById('qrPaymentFields');
        
        if (bankFields) bankFields.classList.add('hidden');
        if (paypalFields) paypalFields.classList.add('hidden');
        if (paymongoFields) paymongoFields.classList.add('hidden');
        if (qrFields) qrFields.classList.add('hidden');

        // ✅ Remove 'required' from ALL hidden fields
        this.removeRequiredFromHidden();

        const placeOrderBtn = document.getElementById('placeOrderBtn');

        // Show relevant fields based on method
        if (method === 'Bank Transfer') {
            if (bankFields) {
                bankFields.classList.remove('hidden');
                this.renderBankTransferInterface();
                // ✅ Set required only for bank transfer fields
                this.setRequiredForBankTransfer();
            }
            if (placeOrderBtn) {
                placeOrderBtn.style.display = 'inline-block';
                placeOrderBtn.disabled = true;
                placeOrderBtn.textContent = 'Place Order';
            }
            
        } else if (method === 'PayPal') {
            if (paypalFields) {
                paypalFields.classList.remove('hidden');
                this.renderPayPalInterface();
            }
            if (placeOrderBtn) {
                placeOrderBtn.style.display = 'inline-block';
                placeOrderBtn.disabled = false;
                placeOrderBtn.textContent = 'Continue to PayPal';
            }
            
        } else if (method === 'PayMongo') {
            if (paymongoFields) {
                paymongoFields.classList.remove('hidden');
                this.renderPayMongoInterface();
            }
            if (placeOrderBtn) {
                placeOrderBtn.style.display = 'inline-block';
                placeOrderBtn.disabled = false;
                placeOrderBtn.textContent = 'Pay with PayMongo';
            }
            this.updatePayMongoAmount();
            
        } else if (method === 'QR Payment') {
            if (qrFields) {
                qrFields.classList.remove('hidden');
                this.renderQRPaymentInterface();
                // ✅ Set required only for QR payment fields
                this.setRequiredForQRPayment();
            }
            if (placeOrderBtn) {
                placeOrderBtn.style.display = 'inline-block';
                placeOrderBtn.disabled = true;
                placeOrderBtn.textContent = 'Place Order';
            }
        }
    }

    // ✅ NEW: Remove 'required' from all payment method fields
    removeRequiredFromHidden() {
        // Bank Transfer fields
        const bankRadios = document.querySelectorAll('input[name="bank_selection"]');
        bankRadios.forEach(radio => radio.removeAttribute('required'));
        const bankScreenshot = document.querySelector('input[name="payment_screenshot"]');
        if (bankScreenshot) bankScreenshot.removeAttribute('required');

        // QR Payment fields
        const qrRadios = document.querySelectorAll('input[name="qr_payment_selection"]');
        qrRadios.forEach(radio => radio.removeAttribute('required'));
        const qrScreenshot = document.querySelector('input[name="qr_payment_screenshot"]');
        if (qrScreenshot) qrScreenshot.removeAttribute('required');
        
        console.log('✓ Removed required attributes from hidden fields');
    }

    // ✅ NEW: Set required for Bank Transfer fields
    setRequiredForBankTransfer() {
        const bankRadios = document.querySelectorAll('input[name="bank_selection"]');
        bankRadios.forEach(radio => radio.setAttribute('required', 'required'));
        
        // Screenshot will be set to required after bank is selected
        console.log('✓ Set required for Bank Transfer fields');
    }

    // ✅ NEW: Set required for QR Payment fields
    setRequiredForQRPayment() {
        const qrRadios = document.querySelectorAll('input[name="qr_payment_selection"]');
        qrRadios.forEach(radio => radio.setAttribute('required', 'required'));
        
        // Screenshot will be set to required after QR method is selected
        console.log('✓ Set required for QR Payment fields');
    }

    updatePayMongoAmount() {
        const paymongoAmount = document.getElementById('paymongoAmount');
        const grandTotal = document.getElementById('grandTotalDisplay');
        if (paymongoAmount && grandTotal) {
            paymongoAmount.textContent = grandTotal.textContent;
        }
    }

    renderPayMongoInterface() {
        const paymongoFields = document.getElementById('paymongoFields');
        if (!paymongoFields || paymongoFields.innerHTML.trim() !== '') return;

        paymongoFields.innerHTML = `
            <div class="bg-green-100 border border-green-200 rounded-lg p-4 mb-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="text-green-600">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div>
                        <h5 class="font-bold text-green-800">PayMongo Payment</h5>
                        <p class="text-sm text-green-600">Secure payment with GCash, Maya, Cards & more</p>
                    </div>
                </div>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="font-bold text-green-800" id="paymongoAmount">₱0.00</span>
                    </div>
                    <div class="text-xs text-green-600 mt-2">
                        Available: GCash, Maya, Credit/Debit Cards, GrabPay
                    </div>
                </div>
            </div>
        `;
    }

    renderBankTransferInterface() {
        if (window.bankQRModule) {
            window.bankQRModule.renderBankTransferInterface();
        }
    }

    renderQRPaymentInterface() {
        if (window.bankQRModule) {
            window.bankQRModule.renderQRPaymentInterface();
        }
    }

    renderPayPalInterface() {
        const paypalFields = document.getElementById('paypalFields');
        if (!paypalFields) return;

        const grandTotalElement = document.getElementById('grandTotalDisplay');
        const totalAmount = grandTotalElement ? grandTotalElement.textContent : '₱0.00';

        if (!paypalFields.innerHTML.includes('PayPal Payment')) {
            paypalFields.innerHTML = `
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div>
                            <h5 class="font-bold text-blue-800">PayPal Payment</h5>
                            <p class="text-sm text-blue-600">Secure payment with PayPal</p>
                        </div>
                    </div>
                    <div class="bg-blue-100 border border-blue-200 rounded-lg p-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Total Amount:</span>
                            <span class="font-bold text-blue-800">${totalAmount}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    setupFormSubmission() {
        const paymentForm = document.getElementById('paymentForm') || document.getElementById('checkoutForm');
        if (!paymentForm) {
            console.warn('⚠️ Payment form not found');
            return;
        }

        console.log('✓ Form found:', paymentForm.id);

        paymentForm.addEventListener('submit', (e) => {
            if (this.isSubmitting) {
                e.preventDefault();
                return false;
            }

            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!selectedMethod) {
                e.preventDefault();
                showNotification('Please select a payment method', 'error');
                return false;
            }

            console.log('Form submitted with method:', selectedMethod.value);

            // Handle PayMongo with AJAX
            if (selectedMethod.value === 'PayMongo') {
                e.preventDefault();
                this.handlePayMongoPayment();
                return false;
            }

            // Let other methods submit normally
            this.isSubmitting = true;
            showNotification(`Processing ${selectedMethod.value}...`, 'info');
        });
    }

    async handlePayMongoPayment() {
        if (this.isSubmitting) return;
        this.isSubmitting = true;

        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.disabled = true;
            placeOrderBtn.textContent = 'Creating PayMongo session...';
        }

        try {
            const grandTotalElement = document.getElementById('grandTotalDisplay');
            if (!grandTotalElement) {
                throw new Error('Cannot find total amount');
            }
            
            const totalText = grandTotalElement.textContent.replace(/[₱,]/g, '').trim();
            const amount = parseFloat(totalText);
            
            if (isNaN(amount) || amount <= 0) {
                throw new Error('Invalid amount: ' + totalText);
            }

            let deliveryFee = 0;
            const feeEl = document.getElementById('deliveryFee');
            if (feeEl) {
                deliveryFee = parseFloat(feeEl.value) || 0;
            } else if (window.deliveryFee !== undefined) {
                deliveryFee = parseFloat(window.deliveryFee) || 0;
            }

            const requestData = {
                amount: amount,
                delivery_fee: deliveryFee,
                order_details: {
                    customer_name: 'Processing',
                    email: 'processing@order.com',
                    mobile: '0000000000',
                    address: 'Processing',
                    zipcode: '0000',
                    delivery_type: 'delivery'
                }
            };

            const response = await fetch('checkout-paymongo-create-sessions-page-12-A.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            });

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Server returned invalid JSON');
            }

            if (data.error) {
                throw new Error(data.error);
            }

            if (data.data && data.data.attributes && data.data.attributes.checkout_url) {
                window.location.href = data.data.attributes.checkout_url;
            } else {
                throw new Error('No checkout URL in response');
            }

        } catch (error) {
            console.error('❌ PayMongo error:', error);
            showNotification('PayMongo error: ' + error.message, 'error');
            
            const placeOrderBtn = document.getElementById('placeOrderBtn');
            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.textContent = 'Pay with PayMongo';
            }
            this.isSubmitting = false;
        }
    }
}

// Initialize when DOM is ready
function initializePaymentSystem() {
    if (!window.paymentSystem) {
        window.paymentSystem = new PaymentSystem();
        window.paymentSystem.initialize();
        console.log('✓ Payment system initialized');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePaymentSystem);
} else {
    initializePaymentSystem();
}

setTimeout(initializePaymentSystem, 500);