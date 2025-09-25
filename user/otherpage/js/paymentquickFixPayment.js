// quickFixPayment.js - Immediate fix for your current errors

// This file combines the essential payment functionality to resolve the loading errors
// Replace all your payment JS files with just this one file for now

console.log('Loading QuickFix Payment System...');

// 1. NOTIFICATION SYSTEM (simplified)
function showNotification(message, type = 'info', duration = 5000) {
    console.log(`${type.toUpperCase()}: ${message}`);
    
    // Create simple notification
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

// Make it global
window.showNotification = showNotification;

// 2. BASE PAYMENT HANDLER CLASS
class PaymentHandler {
    constructor(methodName) {
        this.methodName = methodName;
    }

    show() {
        console.warn(`show() method not implemented for ${this.methodName}`);
    }

    hide() {
        console.warn(`hide() method not implemented for ${this.methodName}`);
    }

    validatePaymentMethod() {
        console.warn(`validatePaymentMethod() method not implemented for ${this.methodName}`);
        return false;
    }

    processPayment(event) {
        console.warn(`processPayment() method not implemented for ${this.methodName}`);
    }

    updateTotalAmount() {
        const grandTotalElement = document.getElementById('grandTotalDisplay');
        return grandTotalElement ? grandTotalElement.textContent : '₱0.00';
    }
}

// 3. BANK TRANSFER HANDLER
class BankTransferHandler extends PaymentHandler {
    constructor() {
        super('Bank Transfer');
        this.selectedBank = null;
        this.screenshotUploaded = false;
        this.bankAccounts = {
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
    }

    show() {
        console.log('Showing Bank Transfer payment method');
        
        const paypalFields = document.getElementById('paypalFields');
        const bankTransferFields = document.getElementById('bankTransferFields');
        
        if (paypalFields) paypalFields.classList.add('hidden');
        if (bankTransferFields) {
            bankTransferFields.classList.remove('hidden');
            this.renderBankSelection();
        }
    }

    renderBankSelection() {
        const bankSelectionArea = document.getElementById('bankSelectionArea');
        if (!bankSelectionArea) {
            console.warn('bankSelectionArea not found');
            return;
        }

        bankSelectionArea.innerHTML = `
            <div class="space-y-4">
                <h5 class="font-bold text-blue-800 mb-3">Select Bank for Transfer</h5>
                <div class="grid gap-3">
                    ${Object.entries(this.bankAccounts).map(([bankCode, bankInfo]) => `
                        <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                            <input type="radio" name="bank_selection" value="${bankCode}" class="mr-3" onchange="window.bankHandler.selectBank('${bankCode}')" />
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

    selectBank(bankCode) {
        this.selectedBank = bankCode;
        const bankInfo = this.bankAccounts[bankCode];
        
        const selectedBankInput = document.getElementById('selectedBank');
        if (selectedBankInput) selectedBankInput.value = bankCode;

        this.showBankDetails(bankInfo);
        this.updateButtonState();
    }

    showBankDetails(bankInfo) {
        const bankDetailsArea = document.getElementById('bankDetailsArea');
        if (!bankDetailsArea) return;

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
                        <span class="font-bold text-green-600">${this.updateTotalAmount()}</span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Payment Screenshot *</label>
                    <input type="file" name="payment_screenshot" accept="image/*" required 
                           class="w-full border border-gray-300 px-3 py-2 rounded-lg" 
                           onchange="window.bankHandler.handleFileUpload(event)" />
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Reference Number (Optional)</label>
                    <input type="text" name="reference_number_input" 
                           class="w-full border border-gray-300 px-3 py-2 rounded-lg" />
                </div>
            </div>
        `;
    }

    handleFileUpload(event) {
        const file = event.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                showNotification('File too large. Maximum 5MB allowed.', 'error');
                event.target.value = '';
                return;
            }
            this.screenshotUploaded = true;
            showNotification('Screenshot uploaded successfully!', 'success');
        } else {
            this.screenshotUploaded = false;
        }
        this.updateButtonState();
    }

    validatePaymentMethod() {
        return this.selectedBank && this.screenshotUploaded;
    }

    updateButtonState() {
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            if (this.validatePaymentMethod()) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.style.display = 'inline-block';
            } else {
                placeOrderBtn.disabled = true;
            }
        }
    }

    processPayment(event) {
        if (!this.validatePaymentMethod()) {
            event.preventDefault();
            showNotification('Please complete bank transfer requirements', 'error');
            return;
        }
        
        showNotification('Processing bank transfer order...', 'info');
    }
}

// 4. PAYPAL HANDLER
class PayPalHandler extends PaymentHandler {
    constructor() {
        super('PayPal');
    }

    show() {
        console.log('Showing PayPal payment method');
        
        const bankTransferFields = document.getElementById('bankTransferFields');
        const paypalFields = document.getElementById('paypalFields');
        
        if (bankTransferFields) bankTransferFields.classList.add('hidden');
        if (paypalFields) {
            paypalFields.classList.remove('hidden');
            this.initializePayPal();
        }
    }

    initializePayPal() {
        const paypalFields = document.getElementById('paypalFields');
        if (!paypalFields) return;

        // Simple PayPal interface
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
                    <div class="text-lg font-bold text-blue-800 mb-2">Total: ${this.updateTotalAmount()}</div>
                    <p class="text-sm text-blue-600">Click "Place Order" to proceed to PayPal</p>
                </div>
            </div>
        `;
        
        this.updateButtonState();
    }

    validatePaymentMethod() {
        return typeof paypal !== 'undefined';
    }

    updateButtonState() {
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.style.display = 'inline-block';
        }
    }

    processPayment(event) {
        showNotification('Redirecting to PayPal...', 'info');
        // Let form submit normally for PayPal
    }
}

// 5. PAYMENT MANAGER
class PaymentManager {
    constructor() {
        this.handlers = {};
        this.currentMethod = null;
        this.isInitialized = false;
    }

    initialize() {
        if (this.isInitialized) return;

        console.log('Initializing PaymentManager...');
        
        // Register handlers
        this.handlers['Bank Transfer'] = new BankTransferHandler();
        this.handlers['PayPal'] = new PayPalHandler();
        
        // Make handlers globally accessible for onclick events
        window.bankHandler = this.handlers['Bank Transfer'];
        window.paypalHandler = this.handlers['PayPal'];
        
        this.setupEventListeners();
        this.isInitialized = true;
        
        console.log('PaymentManager initialized successfully');
        showNotification('Payment system loaded', 'success', 2000);
    }

    setupEventListeners() {
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.switchPaymentMethod(e.target.value);
            });
        });

        // Hide place order button initially
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.style.display = 'none';
            placeOrderBtn.disabled = true;
        }
    }

    switchPaymentMethod(method) {
        console.log('Switching to payment method:', method);
        
        this.currentMethod = method;
        const handler = this.handlers[method];
        
        if (handler) {
            // Hide all payment fields first
            Object.values(this.handlers).forEach(h => h.hide && h.hide());
            
            // Show the selected payment method
            handler.show();
        } else {
            console.error('No handler found for payment method:', method);
        }
    }
}

// 6. BACKWARD COMPATIBILITY FUNCTIONS
// These maintain compatibility with your existing HTML
function showBankSelection() {
    console.log('showBankSelection called (compatibility mode)');
    if (window.paymentManager && window.paymentManager.isInitialized) {
        window.paymentManager.switchPaymentMethod('Bank Transfer');
    }
}

function showPayPalOption() {
    console.log('showPayPalOption called (compatibility mode)');
    if (window.paymentManager && window.paymentManager.isInitialized) {
        window.paymentManager.switchPaymentMethod('PayPal');
    }
}

// Make functions globally available
window.showBankSelection = showBankSelection;
window.showPayPalOption = showPayPalOption;

// 7. INITIALIZE EVERYTHING
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing payment system...');
    
    // Initialize payment manager
    if (!window.paymentManager) {
        window.paymentManager = new PaymentManager();
        window.paymentManager.initialize();
    }
    
    // Set up form submission
    const checkoutForm = document.getElementById('checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    
    if (checkoutForm && placeOrderBtn) {
        checkoutForm.addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!selectedMethod) {
                e.preventDefault();
                showNotification('Please select a payment method', 'error');
                return;
            }
            
            const handler = window.paymentManager.handlers[selectedMethod.value];
            if (handler) {
                if (!handler.validatePaymentMethod()) {
                    e.preventDefault();
                    showNotification('Please complete all payment requirements', 'error');
                    return;
                }
                
                handler.processPayment(e);
            }
        });
    }
});

// Also initialize immediately if DOM is already loaded
if (document.readyState !== 'loading') {
    setTimeout(() => {
        if (!window.paymentManager) {
            window.paymentManager = new PaymentManager();
            window.paymentManager.initialize();
        }
    }, 100);
}