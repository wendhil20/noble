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
    const paymongoFields = document.getElementById('paymongoFields');
    const qrFields = document.getElementById('qrPaymentFields');
    
    if (bankFields) bankFields.classList.add('hidden');
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
            // Wait for DOM update before setting required
            setTimeout(() => this.setRequiredForBankTransfer(), 100);
        }
        if (placeOrderBtn) {
            placeOrderBtn.disabled = true;
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            placeOrderBtn.textContent = 'Place Order';
        }
        
    } else if (method === 'PayMongo') {
        if (paymongoFields) {
            paymongoFields.classList.remove('hidden');
            this.renderPayMongoInterface();
        }
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            placeOrderBtn.textContent = 'Pay with PayMongo';
        }
        this.updatePayMongoAmount();
        
    } else if (method === 'QR Payment') {
        if (qrFields) {
            qrFields.classList.remove('hidden');
            this.renderQRPaymentInterface();
            // Wait for DOM update before setting required
            setTimeout(() => this.setRequiredForQRPayment(), 100);
        }
        if (placeOrderBtn) {
            placeOrderBtn.disabled = true;
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            placeOrderBtn.textContent = 'Place Order';
        }
    }
    
    console.log(`✓ Switched to ${method}, button state:`, placeOrderBtn ? !placeOrderBtn.disabled : 'N/A');
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
    bankRadios.forEach(radio => {
        radio.setAttribute('required', 'required');
        console.log('✓ Set required on bank radio:', radio.value);
    });
    
    // Screenshot will be dynamically set to required after bank selection
    const bankScreenshot = document.querySelector('input[name="payment_screenshot"]');
    if (bankScreenshot) {
        // Only set required after bank is selected - handled in bank-qr module
        console.log('✓ Bank screenshot field found (will be required after bank selection)');
    }
}

    // ✅ NEW: Set required for QR Payment fields
setRequiredForQRPayment() {
    const qrRadios = document.querySelectorAll('input[name="qr_payment_selection"]');
    qrRadios.forEach(radio => {
        radio.setAttribute('required', 'required');
        console.log('✓ Set required on QR radio:', radio.value);
    });
    
    // Screenshot will be dynamically set to required after QR method selection
    const qrScreenshot = document.querySelector('input[name="qr_payment_screenshot"]');
    if (qrScreenshot) {
        console.log('✓ QR screenshot field found (will be required after QR selection)');
    }
}

    updatePayMongoAmount() {
    const paymongoAmount = document.getElementById('paymongoAmount');
    if (paymongoAmount && window.grandTotal) {
        const formatted = '₱' + parseFloat(window.grandTotal).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        paymongoAmount.textContent = formatted;
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
            console.log('⚠️ Already submitting, ignoring...');
            return false;
        }

        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        
        if (!selectedMethod) {
            e.preventDefault();
            showNotification('Please select a payment method', 'error');
            return false;
        }

        console.log('📝 Form submitted with method:', selectedMethod.value);

        // Validate based on payment method
        if (selectedMethod.value === 'Bank Transfer') {
            const bankValid = window.bankQRModule ? window.bankQRModule.validateBankTransferForm() : false;
            if (!bankValid) {
                e.preventDefault();
                showNotification('Please complete bank transfer details', 'error');
                return false;
            }
        } else if (selectedMethod.value === 'QR Payment') {
            const qrValid = window.bankQRModule ? window.bankQRModule.validateQRPaymentForm() : false;
            if (!qrValid) {
                e.preventDefault();
                showNotification('Please complete QR payment details', 'error');
                return false;
            }
        }

        // Handle PayMongo with AJAX
        if (selectedMethod.value === 'PayMongo') {
            e.preventDefault();
            this.handlePayMongoPayment();
            return false;
        }

        // Let other methods submit normally
        this.isSubmitting = true;
        showNotification(`Processing ${selectedMethod.value}...`, 'info', 2000);
        console.log('✅ Form submission allowed for:', selectedMethod.value);
    });
}

  // FIXED handlePayMongoPayment() method
// Replace the existing method in index-checkout-paymentquickFixPayment-page-12-4.js

async handlePayMongoPayment() {
    if (this.isSubmitting) return;
    this.isSubmitting = true;

    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        placeOrderBtn.disabled = true;
        placeOrderBtn.textContent = 'Creating PayMongo session...';
    }

    try {
        // ✅ FIX: Get grand total from hidden input VALUE attribute, not textContent
        const grandTotalElement = document.getElementById('grandTotalDisplay');
        if (!grandTotalElement) {
            throw new Error('Cannot find total amount element');
        }
        
        // Read from the VALUE attribute (which contains the number)
        const totalText = grandTotalElement.value || grandTotalElement.textContent;
        const cleanedAmount = totalText.replace(/[₱,\s]/g, '').trim();
        const amount = parseFloat(cleanedAmount);
        
        console.log('Debug - Grand Total Element:', grandTotalElement);
        console.log('Debug - Total Text:', totalText);
        console.log('Debug - Cleaned Amount:', cleanedAmount);
        console.log('Debug - Parsed Amount:', amount);
        
        if (isNaN(amount) || amount <= 0) {
            throw new Error('Invalid amount: ' + cleanedAmount + ' (parsed: ' + amount + ')');
        }

        // ✅ Get delivery fee from window or hidden input
        let deliveryFee = 0;
        if (window.deliveryFee !== undefined) {
            deliveryFee = parseFloat(window.deliveryFee);
        }

        // ✅ Get vehicle data from hidden inputs
        const vehicleData = {
            assigned_vehicle_id: parseInt(document.getElementById('assignedVehicleId')?.value || '0'),
            assigned_vehicle_type: document.getElementById('assignedVehicleType')?.value || '',
            total_cubic_meters: parseFloat(document.getElementById('totalCubicMeters')?.value || '0'),
            total_weight_kg: parseFloat(document.getElementById('totalWeightKg')?.value || '0'),
            total_width: parseFloat(document.getElementById('totalWidth')?.value || '0'),
            total_height: parseFloat(document.getElementById('totalHeight')?.value || '0'),
            total_length: parseFloat(document.getElementById('totalLength')?.value || '0')
        };

        // ✅ Get referral code if applied
        const referralCode = document.getElementById('referralCodeHidden')?.value || '';

        const requestData = {
            amount: amount,
            delivery_fee: deliveryFee,
            order_details: {
                ...vehicleData,
                referral_code: referralCode
            }
        };

        console.log('✓ Sending PayMongo request:', requestData);

        const response = await fetch('checkout-paymongo-create-sessions-page-12-A.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        });

        const text = await response.text();
        console.log('PayMongo raw response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('❌ Failed to parse JSON:', text);
            throw new Error('Server returned invalid response: ' + text.substring(0, 100));
        }

        if (data.error) {
            throw new Error(data.error);
        }

        if (data.data && data.data.attributes && data.data.attributes.checkout_url) {
            console.log('✓ Redirecting to PayMongo:', data.data.attributes.checkout_url);
            showNotification('Redirecting to payment gateway...', 'success', 2000);
            window.location.href = data.data.attributes.checkout_url;
        } else {
            console.error('❌ Invalid response structure:', data);
            throw new Error('No checkout URL in response');
        }

    } catch (error) {
        console.error('❌ PayMongo error:', error);
        showNotification('Payment error: ' + error.message, 'error', 5000);
        
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