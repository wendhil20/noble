// Single Payment Solution - Replace ALL your payment JS files with just this one
console.log('Loading Complete Payment System...');

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

    switchPaymentMethod(method) {
        console.log('Switching to payment method:', method);
        
        // Hide all payment fields
        const bankFields = document.getElementById('bankTransferFields');
        const paypalFields = document.getElementById('paypalFields'); 
        const paymongoFields = document.getElementById('paymongoFields');
        
        if (bankFields) bankFields.classList.add('hidden');
        if (paypalFields) paypalFields.classList.add('hidden');
        if (paymongoFields) paymongoFields.classList.add('hidden');

        const placeOrderBtn = document.getElementById('placeOrderBtn');

        // Show relevant fields based on method
        if (method === 'Bank Transfer') {
            if (bankFields) {
                bankFields.classList.remove('hidden');
                this.renderBankTransferInterface();
            }
            this.showPlaceOrderButton('Place Order');
            
        } else if (method === 'PayPal') {
            if (paypalFields) {
                paypalFields.classList.remove('hidden');
                this.renderPayPalInterface();
            }
            this.showPlaceOrderButton('Continue to PayPal');
            
        } else if (method === 'PayMongo') {
            if (paymongoFields) {
                paymongoFields.classList.remove('hidden');
                this.renderPayMongoInterface();
            }
            this.showPlaceOrderButton('Pay with PayMongo');
            this.updatePayMongoAmount();
        }
    }

    showPlaceOrderButton(text) {
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.style.display = 'inline-block';
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = text;
        }
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
        const bankFields = document.getElementById('bankTransferFields');
        const bankSelectionArea = document.getElementById('bankSelectionArea');
        if (!bankSelectionArea) return;

        const bankAccounts = {
            'BPI': {
                name: 'Bank of the Philippine Islands',
                accountName: 'Noble Home Construction',
                accountNumber: '1234-5678-90',
                color: 'red'
            },
            'BDO': {
                name: 'Banco de Oro',
                accountName: 'Noble Home Construction', 
                accountNumber: '0987-6543-21',
                color: 'blue'
            },
            'Metrobank': {
                name: 'Metropolitan Bank',
                accountName: 'Noble Home Construction',
                accountNumber: '5678-9012-34',
                color: 'yellow'
            }
        };

        bankSelectionArea.innerHTML = `
            <div class="space-y-4">
                <h5 class="font-bold text-blue-800 mb-3">Select Bank for Transfer</h5>
                <div class="grid gap-3">
                    ${Object.entries(bankAccounts).map(([bankCode, bankInfo]) => `
                        <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                            <input type="radio" name="bank_selection" value="${bankCode}" class="mr-3" onchange="window.paymentSystem.selectBank('${bankCode}', ${JSON.stringify(bankInfo).replace(/"/g, '&quot;')})" />
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-${bankInfo.color}-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                    ${bankCode}
                                </div>
                                <div>
                                    <div class="font-medium">${bankInfo.name}</div>
                                    <div class="text-sm text-gray-600">${bankCode}</div>
                                </div>
                            </div>
                        </label>
                    `).join('')}
                </div>
                <div id="bankDetailsArea" class="hidden"></div>
            </div>
        `;
    }

    selectBank(bankCode, bankInfo) {
        const selectedBankInput = document.getElementById('selectedBank');
        if (selectedBankInput) selectedBankInput.value = bankCode;

        this.showBankDetails(bankInfo);
    }

    showBankDetails(bankInfo) {
        const bankDetailsArea = document.getElementById('bankDetailsArea');
        if (!bankDetailsArea) return;

        const grandTotalElement = document.getElementById('grandTotalDisplay');
        const totalAmount = grandTotalElement ? grandTotalElement.textContent : '₱0.00';

        bankDetailsArea.classList.remove('hidden');
        bankDetailsArea.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                <h6 class="font-bold text-blue-800 mb-3">Transfer Details</h6>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Account Name:</span>
                        <span class="font-medium">${bankInfo.accountName}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Account Number:</span>
                        <span class="font-medium">${bankInfo.accountNumber}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <span>Amount:</span>
                        <span class="font-bold text-green-600">${totalAmount}</span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Payment Screenshot *</label>
                    <input type="file" name="payment_screenshot" accept="image/*" required 
                           class="w-full border border-gray-300 px-3 py-2 rounded-lg" />
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Reference Number (Optional)</label>
                    <input type="text" name="reference_number_input" 
                           class="w-full border border-gray-300 px-3 py-2 rounded-lg" />
                </div>
            </div>
        `;
    }

    renderPayPalInterface() {
        const paypalFields = document.getElementById('paypalFields');
        if (!paypalFields) return;

        const grandTotalElement = document.getElementById('grandTotalDisplay');
        const totalAmount = grandTotalElement ? grandTotalElement.textContent : '₱0.00';

        paypalFields.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8">
                            <path fill="#003087" d="M15.7 4.2h6.5c2.2 0 3.9.5 5.1 1.5 1.1.9 1.6 2.3 1.3 4.2-.7 4.8-3.6 6.8-8.5 6.8h-2.2c-.5 0-.9.3-1 .8l-1 6.6c0 .3-.3.5-.6.5H11c-.5 0-.9-.4-.8-.9L13.5 5c.1-.4.4-.8.9-.8h1.3z" />
                            <path fill="#009cde" d="M26.8 10.6c-.3 2-1.2 3.6-2.6 4.6-1.4 1-3.3 1.5-5.7 1.5h-2.4c-.5 0-.9.3-1 .8l-1.1 7.1c0 .3-.3.5-.6.5h-3.4c-.5 0-.9-.4-.8-.9l2.4-15.6c.1-.4.4-.8.9-.8h7.2c1.4 0 2.6.2 3.6.6 1.5.6 2.1 2 1.9 3.2z" />
                        </svg>
                    </div>
                    <div>
                        <h5 class="font-bold text-blue-800">PayPal Payment</h5>
                        <p class="text-sm text-blue-600">Secure payment with PayPal</p>
                    </div>
                </div>
                
                <div class="text-center p-4">
                    <div class="text-lg font-bold text-blue-800 mb-2">Total: ${totalAmount}</div>
                    <p class="text-sm text-blue-600">Click "Continue to PayPal" to proceed with payment</p>
                </div>
            </div>
        `;
    }

    setupFormSubmission() {
        const checkoutForm = document.getElementById('checkoutForm');
        if (!checkoutForm) {
            console.warn('Checkout form not found');
            return;
        }

        checkoutForm.addEventListener('submit', (e) => {
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

            console.log('Form submitting with method:', selectedMethod.value);

            // Handle PayMongo with AJAX
            if (selectedMethod.value === 'PayMongo') {
                e.preventDefault();
                this.handlePayMongoPayment();
                return false;
            }

            // Let other payment methods submit normally
            this.isSubmitting = true;
            showNotification(`Processing ${selectedMethod.value} payment...`, 'info');
        });
    }

    async handlePayMongoPayment() {
        if (this.isSubmitting) return;
        this.isSubmitting = true;

        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (!placeOrderBtn) {
            this.isSubmitting = false;
            return;
        }

        placeOrderBtn.disabled = true;
        placeOrderBtn.textContent = 'Creating PayMongo session...';

        try {
            // Get total amount
            const grandTotalElement = document.getElementById('grandTotalDisplay');
            if (!grandTotalElement) {
                throw new Error('Cannot find total amount');
            }
            
            const totalText = grandTotalElement.textContent.replace(/[₱,]/g, '');
            const amount = parseFloat(totalText);
            
            if (isNaN(amount) || amount <= 0) {
                throw new Error('Invalid amount: ' + totalText);
            }

            // ✅ FIXED: Get ALL required form data properly
            const checkoutForm = document.getElementById('checkoutForm');
            if (!checkoutForm) {
                throw new Error('Checkout form not found');
            }

            // Extract all required form fields
            const formData = new FormData(checkoutForm);
            
            // Create proper order details object
            const orderDetails = {
                customer_name: formData.get('customer_name') || '',
                email: formData.get('email') || '',
                mobile: formData.get('mobile') || '',
                address: formData.get('address') || '',
                zipcode: formData.get('zipcode') || '',
                billing_address_id: formData.get('billing_address_id') || null,
                delivery_distance: parseFloat(formData.get('delivery_distance')) || 0,
                delivery_fee: parseFloat(formData.get('delivery_fee')) || 0,
                zone_id: formData.get('zone_id') || null
            };

            // ✅ VALIDATION: Check required fields
            const requiredFields = ['customer_name', 'email', 'mobile', 'address', 'zipcode'];
            const missingFields = requiredFields.filter(field => !orderDetails[field]);
            
            if (missingFields.length > 0) {
                throw new Error('Missing required fields: ' + missingFields.join(', '));
            }

            console.log('Creating PayMongo session with data:', {
                amount: amount,
                order_details: orderDetails
            });

            // ✅ FIXED: Send complete data to PHP
            const response = await fetch('paymongo-create-sessions.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    amount: amount,
                    delivery_fee: orderDetails.delivery_fee,
                    order_details: orderDetails
                })
            });

            console.log('PayMongo response status:', response.status);

            const responseText = await response.text();
            console.log('Raw PayMongo response:', responseText.substring(0, 500));

            // Check if it's HTML (error page)
            if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html')) {
                throw new Error('Server error: Check paymongo-create-sessions.php file for syntax errors.');
            }

            const data = JSON.parse(responseText);
            console.log('Parsed PayMongo data:', data);

            if (data.error) {
                throw new Error(data.error);
            }

            if (data.data && data.data.attributes && data.data.attributes.checkout_url) {
                console.log('Redirecting to PayMongo...');
                window.location.href = data.data.attributes.checkout_url;
            } else {
                throw new Error('Invalid PayMongo response - no checkout URL');
            }

        } catch (error) {
            console.error('PayMongo error:', error);
            showNotification('PayMongo payment failed: ' + error.message, 'error');
            
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = 'Pay with PayMongo';
            this.isSubmitting = false;
        }
    }

    // ✅ FIXED: Update PayMongo amount display
    updatePayMongoAmount() {
        const paymongoAmount = document.getElementById('paymongoAmount');
        const grandTotal = document.getElementById('grandTotalDisplay');
        if (paymongoAmount && grandTotal) {
            paymongoAmount.textContent = grandTotal.textContent;
        }
    }
}

// Backward compatibility functions
function showBankSelection() {
    if (window.paymentSystem) {
        window.paymentSystem.switchPaymentMethod('Bank Transfer');
    }
}

function showPayPalOption() {
    if (window.paymentSystem) {
        window.paymentSystem.switchPaymentMethod('PayPal');
    }
}

function showPayMongoOption() {
    if (window.paymentSystem) {
        window.paymentSystem.switchPaymentMethod('PayMongo');
    }
}

window.showBankSelection = showBankSelection;
window.showPayPalOption = showPayPalOption;
window.showPayMongoOption = showPayMongoOption;

// Initialize everything
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing payment system...');
    
    if (!window.paymentSystem) {
        window.paymentSystem = new PaymentSystem();
        window.paymentSystem.initialize();
        
        // Hide place order button initially
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.style.display = 'none';
            placeOrderBtn.disabled = true;
        }
    }
});

if (document.readyState !== 'loading') {
    setTimeout(() => {
        if (!window.paymentSystem) {
            window.paymentSystem = new PaymentSystem();
            window.paymentSystem.initialize();
        }
    }, 100);
}