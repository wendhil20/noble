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
    const qrFields = document.getElementById('qrPaymentFields');
    
    if (bankFields) {
        bankFields.classList.add('hidden');
        const bankRadios = bankFields.querySelectorAll('input[name="bank_selection"]');
        bankRadios.forEach(radio => radio.removeAttribute('required'));
        const screenshot = bankFields.querySelector('input[name="payment_screenshot"]');
        if (screenshot) screenshot.removeAttribute('required');
    }
    if (paypalFields) paypalFields.classList.add('hidden');
    if (paymongoFields) paymongoFields.classList.add('hidden');
    if (qrFields) {
        qrFields.classList.add('hidden');
        const qrRadios = qrFields.querySelectorAll('input[name="qr_payment_selection"]');
        qrRadios.forEach(radio => radio.removeAttribute('required'));
        const qrScreenshot = qrFields.querySelector('input[name="qr_payment_screenshot"]');
        if (qrScreenshot) qrScreenshot.removeAttribute('required');
    }

    const placeOrderBtn = document.getElementById('placeOrderBtn');

    // Show relevant fields based on method
    if (method === 'Bank Transfer') {
        if (bankFields) {
            bankFields.classList.remove('hidden');
            this.renderBankTransferInterface();
            const bankRadios = bankFields.querySelectorAll('input[name="bank_selection"]');
            bankRadios.forEach(radio => radio.setAttribute('required', 'required'));
        }
        if (placeOrderBtn) {
            placeOrderBtn.style.display = 'inline-block';
            placeOrderBtn.disabled = true;
            placeOrderBtn.textContent = 'Place Order';
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
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
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        }
        
    } else if (method === 'PayMongo') {
        if (paymongoFields) {
            paymongoFields.classList.remove('hidden');
            this.renderPayMongoInterface();
        }
        this.showPlaceOrderButton('Pay with PayMongo', true);
        this.updatePayMongoAmount();
        
    } else if (method === 'QR Payment') {
        if (qrFields) {
            qrFields.classList.remove('hidden');
            this.renderQRPaymentInterface();
            const qrRadios = qrFields.querySelectorAll('input[name="qr_payment_selection"]');
            qrRadios.forEach(radio => radio.setAttribute('required', 'required'));
        }
        if (placeOrderBtn) {
            placeOrderBtn.style.display = 'inline-block';
            placeOrderBtn.disabled = true;
            placeOrderBtn.textContent = 'Place Order';
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
        }
    }
}

    showPlaceOrderButton(text, enabled = true) {
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        placeOrderBtn.style.display = 'inline-block';
        placeOrderBtn.textContent = text;
        
        if (enabled) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        } else {
            placeOrderBtn.disabled = true;
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
        }
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
            <h5 class="font-bold text-blue-800 mb-3">Select Bank for Transfer *</h5>
            <div class="grid gap-3">
                ${Object.entries(bankAccounts).map(([bankCode, bankInfo]) => `
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition bank-option">
                        <input type="radio" name="bank_selection" value="${bankCode}" class="mr-3 bank-radio" required />
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-${bankInfo.color}-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                ${bankCode.substring(0, 2)}
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

    // ✅ ADD EVENT LISTENERS FOR BANK SELECTION
    const bankRadios = bankSelectionArea.querySelectorAll('.bank-radio');
    bankRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            if (e.target.checked) {
                const bankCode = e.target.value;
                const bankInfo = bankAccounts[bankCode];
                this.selectBank(bankCode, bankInfo);
                
                // Enable place order button after bank selection
                this.showPlaceOrderButton('Place Order');
            }
        });
    });
}

renderQRPaymentInterface() {
    const qrFields = document.getElementById('qrPaymentFields');
    if (!qrFields) return;

    // Setup QR method selection listeners
    const qrRadios = document.querySelectorAll('.qr-method-radio');
    qrRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            if (e.target.checked) {
                const methodName = e.target.dataset.methodName;
                const accountName = e.target.dataset.accountName;
                const accountNumber = e.target.dataset.accountNumber;
                const qrImage = e.target.dataset.qrImage;
                const instructions = e.target.dataset.instructions;
                
                this.selectQRMethod(e.target.value, {
                    methodName,
                    accountName,
                    accountNumber,
                    qrImage,
                    instructions
                });
            }
        });
    });
}

selectQRMethod(methodId, methodInfo) {
    console.log('QR method selected:', methodId);
    
    const selectedQRInput = document.getElementById('selectedQRMethod');
    if (selectedQRInput) {
        selectedQRInput.value = methodId;
    }

    this.showQRDetails(methodInfo);
}

showQRDetails(methodInfo) {
    const qrDetailsArea = document.getElementById('qrDetailsArea');
    if (!qrDetailsArea) return;

    const grandTotalElement = document.getElementById('grandTotalDisplay');
    const totalAmount = grandTotalElement ? grandTotalElement.textContent : '₱0.00';

    qrDetailsArea.classList.remove('hidden');
    qrDetailsArea.innerHTML = `
        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mt-4">
            <h6 class="font-bold text-indigo-800 mb-3 text-sm flex items-center">
                <i class="fas fa-qrcode mr-2"></i>
                ${methodInfo.methodName} Payment Details
            </h6>
            
            <div class="grid md:grid-cols-2 gap-3">
                <!-- QR Code Display - Compact -->
                <div class="bg-white rounded-lg p-3 text-center">
                    <img src="${methodInfo.qrImage}" 
                         alt="${methodInfo.methodName} QR Code" 
                         class="max-w-[180px] mx-auto rounded border border-indigo-200 shadow-sm cursor-pointer hover:scale-105 transition-transform"
                         onclick="window.open('${methodInfo.qrImage}', '_blank')">
                    <p class="text-xs text-gray-600 mt-2">
                        <i class="fas fa-expand-alt mr-1"></i>
                        Click to enlarge
                    </p>
                    <a href="${methodInfo.qrImage}" download class="inline-flex items-center justify-center w-full px-3 py-2 mt-2 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-download mr-1"></i>
                        Download QR
                    </a>
                </div>
                
                <!-- Payment Details - Compact -->
                <div class="space-y-2">
                    <div class="bg-white rounded p-2 text-sm">
                        <div class="text-gray-500 text-xs">Account Name</div>
                        <div class="font-medium">${methodInfo.accountName}</div>
                    </div>
                    <div class="bg-white rounded p-2 text-sm">
                        <div class="text-gray-500 text-xs">Account Number</div>
                        <div class="font-medium">${methodInfo.accountNumber}</div>
                    </div>
                    <div class="bg-white rounded p-2 text-sm border-t-2 border-indigo-200">
                        <div class="text-gray-700 font-medium text-xs">Amount to Pay</div>
                        <div class="font-bold text-green-600 text-lg">${totalAmount}</div>
                    </div>
                    
                    ${methodInfo.instructions ? `
                        <div class="bg-blue-50 border border-blue-200 rounded p-2 text-xs text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            ${methodInfo.instructions}
                        </div>
                    ` : ''}
                </div>
            </div>
            
            <!-- Payment Screenshot Upload - Compact -->
            <div class="mt-3">
                <label class="block font-medium mb-1 text-sm">
                    Payment Screenshot <span class="text-red-600">*</span>
                </label>
                <input type="file" 
                       name="qr_payment_screenshot" 
                       accept="image/*" 
                       required 
                       class="w-full border border-gray-300 px-2 py-2 rounded text-sm file-input" 
                       onchange="window.paymentSystem.validateQRPaymentForm()" />
                <p class="text-xs text-gray-500 mt-1">
                    <i class="fas fa-camera mr-1"></i>
                    Upload payment confirmation
                </p>
            </div>
            
            <!-- Reference Number (Optional) - Compact -->
            <div class="mt-2">
                <label class="block font-medium mb-1 text-sm">Reference Number (Optional)</label>
                <input type="text" 
                       name="qr_reference_number_input" 
                       id="qr_reference_number_input"
                       class="w-full border border-gray-300 px-2 py-2 rounded text-sm" 
                       placeholder="Enter transaction reference" 
                       onchange="document.getElementById('qrReferenceNumber').value = this.value" />
            </div>
        </div>
    `;
}

validateQRPaymentForm() {
    const selectedQR = document.getElementById('selectedQRMethod');
    const qrScreenshot = document.querySelector('input[name="qr_payment_screenshot"]');
    
    const qrSelected = selectedQR && selectedQR.value;
    const screenshotUploaded = qrScreenshot && qrScreenshot.files && qrScreenshot.files.length > 0;
    
    console.log('QR Payment Validation:', {
        qrSelected: qrSelected,
        screenshot: screenshotUploaded
    });
    
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        if (qrSelected && screenshotUploaded) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            return true;
        } else {
            placeOrderBtn.disabled = true;
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            return false;
        }
    }
    
    return false;

    
}



    selectBank(bankCode, bankInfo) {
    console.log('Bank selected:', bankCode);
    
    const selectedBankInput = document.getElementById('selectedBank');
    if (selectedBankInput) {
        selectedBankInput.value = bankCode;
        console.log('✓ Bank type hidden input set:', bankCode);
    }

    this.showBankDetails(bankInfo);
    
    // Enable place order button
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        placeOrderBtn.disabled = false;
        placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
        placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
    }
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
                       class="w-full border border-gray-300 px-3 py-2 rounded-lg file-input" 
                       onchange="window.paymentSystem.validateBankTransferForm()" />
                <p class="text-xs text-gray-500 mt-1">Required: Upload proof of payment</p>
            </div>
            
            <div class="mt-4">
                <label class="block font-medium mb-2">Reference Number (Optional)</label>
                <input type="text" name="reference_number_input" id="reference_number_input"
                       class="w-full border border-gray-300 px-3 py-2 rounded-lg" 
                       placeholder="Enter bank reference number" />
            </div>
        </div>
    `;
}

validateBankTransferForm() {
    const selectedBank = document.getElementById('selectedBank');
    const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');
    
    const bankSelected = selectedBank && selectedBank.value;
    const screenshotUploaded = paymentScreenshot && paymentScreenshot.files && paymentScreenshot.files.length > 0;
    
    console.log('Bank Transfer Validation:', {
        bankSelected: bankSelected,
        screenshot: screenshotUploaded
    });
    
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        if (bankSelected && screenshotUploaded) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            return true;
        } else {
            placeOrderBtn.disabled = true;
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            return false;
        }
    }
    
    return false;
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
            
            <div class="bg-blue-100 border border-blue-200 rounded-lg p-4">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="font-bold text-blue-800" id="paypalAmount">${totalAmount}</span>
                    </div>
                    <div class="text-xs text-blue-600 mt-2">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Safe and secure payment with PayPal</li>
                            <li>Pay with your PayPal balance, bank account, or credit card</li>
                            <li>No need to share financial details with us</li>
                            <li>Instant payment confirmation</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded flex items-center">
                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm text-blue-700">Click "Continue to PayPal" to complete your payment securely</span>
            </div>
        </div>
    `;
    
    // ✅ Update PayPal amount display
    this.updatePayPalAmount();
}

updatePayPalAmount() {
    const paypalAmount = document.getElementById('paypalAmount');
    const grandTotal = document.getElementById('grandTotalDisplay');
    if (paypalAmount && grandTotal) {
        paypalAmount.textContent = grandTotal.textContent;
        console.log('✓ PayPal amount updated:', grandTotal.textContent);
    }
}

handlePayPalSubmission(form) {
    if (this.isSubmitting) {
        console.log('⚠️ Already submitting, ignoring duplicate request');
        return;
    }

    console.log('Processing PayPal payment...');
    
    // Validate delivery calculation first
    const deliveryDistanceInput = document.getElementById('deliveryDistance');
    const deliveryFeeInput = document.getElementById('deliveryFee');
    
    if (!deliveryDistanceInput || !deliveryFeeInput) {
        showNotification('Delivery calculation missing. Please refresh the page.', 'error');
        return;
    }
    
    const deliveryDistance = parseFloat(deliveryDistanceInput.value || '0');
    const deliveryFee = parseFloat(deliveryFeeInput.value || '0');
    
    if (deliveryDistance <= 0) {
        showNotification('Please calculate delivery distance first (Step 3)', 'error');
        goToStep(3);
        return;
    }
    
    if (deliveryFee < 0) {
        showNotification('Invalid delivery fee. Please recalculate.', 'error');
        goToStep(3);
        return;
    }

    console.log('✓ Delivery validated for PayPal:', { distance: deliveryDistance, fee: deliveryFee });

    // Get total amount for validation
    const grandTotalElement = document.getElementById('grandTotalDisplay');
    if (!grandTotalElement) {
        showNotification('Cannot find total amount. Please refresh the page.', 'error');
        return;
    }

    const totalText = grandTotalElement.textContent.replace(/[₱,]/g, '');
    const totalAmount = parseFloat(totalText);
    
    if (isNaN(totalAmount) || totalAmount <= 0) {
        showNotification('Invalid order total. Please refresh the page.', 'error');
        return;
    }

    console.log('✓ PayPal amount validated:', totalAmount);

    // Update button state
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        placeOrderBtn.disabled = true;
        placeOrderBtn.textContent = 'Redirecting to PayPal...';
        placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
    }

    showNotification('Redirecting to PayPal for secure payment...', 'info');
    
    // Mark as submitting
    this.isSubmitting = true;

    // Submit the form (PHP will handle PayPal redirect)
    console.log('✓ Submitting form for PayPal processing');
    form.submit();
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

        // ✅ VALIDATE BANK TRANSFER BEFORE SUBMISSION
        if (selectedMethod.value === 'Bank Transfer') {
            e.preventDefault(); // Always prevent default for validation
            
            const selectedBank = document.getElementById('selectedBank');
            const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');
            
            // Check if bank is selected
            if (!selectedBank || !selectedBank.value) {
                showNotification('Please select a bank for transfer', 'error');
                return false;
            }
            
            // Check if screenshot is uploaded
            if (!paymentScreenshot || !paymentScreenshot.files || !paymentScreenshot.files.length) {
                showNotification('Please upload payment screenshot', 'error');
                return false;
            }
            
            // ✅ If validation passes, submit normally
            console.log('✓ Bank Transfer validated - submitting form');
            this.isSubmitting = true;
            checkoutForm.submit(); // Submit the form
            return true;
        }

        // Handle QR Payment submission
if (selectedMethod.value === 'QR Payment') {
    e.preventDefault(); // Always prevent default for validation
    
    const selectedQR = document.getElementById('selectedQRMethod');
    const qrScreenshot = document.querySelector('input[name="qr_payment_screenshot"]');
    
    // Check if QR method is selected
    if (!selectedQR || !selectedQR.value) {
        showNotification('Please select a QR payment method', 'error');
        return false;
    }
    
    // Check if screenshot is uploaded
    if (!qrScreenshot || !qrScreenshot.files || !qrScreenshot.files.length) {
        showNotification('Please upload payment screenshot', 'error');
        return false;
    }
    
    // ✅ If validation passes, submit normally
    console.log('✓ QR Payment validated - submitting form');
    this.isSubmitting = true;
    checkoutForm.submit(); // Submit the form
    return true;
}

        // ✅ FIXED: Validate delivery calculation before submission
        const deliveryDistanceInput = document.getElementById('deliveryDistance');
        const deliveryFeeInput = document.getElementById('deliveryFee');
        
        if (!deliveryDistanceInput || !deliveryFeeInput) {
            e.preventDefault();
            showNotification('Delivery calculation missing. Please refresh the page.', 'error');
            return false;
        }
        
        const deliveryDistance = parseFloat(deliveryDistanceInput.value || '0');
        const deliveryFee = parseFloat(deliveryFeeInput.value || '0');
        
        if (deliveryDistance <= 0) {
            e.preventDefault();
            showNotification('Please calculate delivery distance first (Step 3)', 'error');
            goToStep(3);
            return false;
        }
        
        if (deliveryFee < 0) {
            e.preventDefault();
            showNotification('Invalid delivery fee. Please recalculate.', 'error');
            goToStep(3);
            return false;
        }

        console.log('✓ Delivery validated - Distance:', deliveryDistance, 'Fee:', deliveryFee);
        console.log('Form submitting with method:', selectedMethod.value);

        // Handle PayMongo with AJAX
        if (selectedMethod.value === 'PayMongo') {
            e.preventDefault();
            this.handlePayMongoPayment();
            return false;
        }

        // Handle PayPal submission
        if (selectedMethod.value === 'PayPal') {
            e.preventDefault();
            this.handlePayPalSubmission(checkoutForm);
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
        // ✅ Check delivery type
        const deliveryTypeInput = document.querySelector('input[name="delivery_type"]:checked');
        const deliveryType = deliveryTypeInput ? deliveryTypeInput.value : 'delivery';
        
        console.log('PayMongo - Delivery Type:', deliveryType);
        
        let vehicleData = {
            assigned_vehicle_id: null,
            assigned_vehicle_type: null,
            total_cubic_meters: 0,
            total_weight_kg: 0,
            total_width: 0,
            total_height: 0,
            total_length: 0
        };
        
        let deliveryDistance = 0;
        let deliveryFee = 0;
        
        // ✅ If delivery type is "delivery", validate and calculate vehicle assignment
        if (deliveryType === 'delivery') {
            // Validate delivery calculation first
            const deliveryDistanceInput = document.getElementById('deliveryDistance');
            const deliveryFeeInput = document.getElementById('deliveryFee');
            
            if (!deliveryDistanceInput || !deliveryFeeInput) {
                throw new Error('Delivery calculation fields not found');
            }
            
            deliveryDistance = parseFloat(deliveryDistanceInput.value || '0');
            deliveryFee = parseFloat(deliveryFeeInput.value || '0');
            
            if (deliveryDistance <= 0) {
                throw new Error('Please calculate delivery distance first');
            }
            
            // ✅ Calculate vehicle assignment for PayMongo
            if (!window.cartItemsData || window.cartItemsData.length === 0) {
                throw new Error('Cart items not found. Please refresh the page.');
            }
            
            console.log('Calculating vehicle assignment for PayMongo...');
            const vehicleAssignment = assignTransportifyVehicleJS(window.cartItemsData);
            
            if (!vehicleAssignment || !vehicleAssignment.vehicle) {
                throw new Error('Unable to assign delivery vehicle. Please recalculate delivery.');
            }
            
            // ✅ Calculate total dimensions
            let totalWidth = 0, totalHeight = 0, totalLength = 0;
            window.cartItemsData.forEach(item => {
                const width = parseFloat(item.width) || 30;
                const height = parseFloat(item.height) || 30;
                const length = parseFloat(item.length) || 30;
                const dimensionUnit = item.dimension_unit || 'cm';
                const quantity = parseInt(item.quantity) || 1;
                
                const meters = {
                    'cm': 0.01, 'm': 1, 'mm': 0.001, 'in': 0.0254, 'ft': 0.3048
                };
                const multiplier = meters[dimensionUnit.toLowerCase()] || 0.01;
                
                totalWidth += (width * multiplier * quantity);
                totalHeight += (height * multiplier * quantity);
                totalLength += (length * multiplier * quantity);
            });
            
            vehicleData = {
                assigned_vehicle_id: vehicleAssignment.vehicle.id,
                assigned_vehicle_type: vehicleAssignment.vehicle.vehicle_type,
                total_cubic_meters: vehicleAssignment.totalCubicMeters.toFixed(3),
                total_weight_kg: vehicleAssignment.totalWeightKg.toFixed(2),
                total_width: totalWidth.toFixed(2),
                total_height: totalHeight.toFixed(2),
                total_length: totalLength.toFixed(2)
            };
            
            console.log('✓ PayMongo vehicle assignment data:', vehicleData);
        } else {
            console.log('✓ PayMongo pickup mode - vehicle data set to zero');
        }

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
            
            // ✅ Create proper order details object with vehicle data
        const orderDetails = {
            customer_name: formData.get('customer_name') || '',
            email: formData.get('email') || '',
            mobile: formData.get('mobile') || '',
            address: formData.get('address') || '',
            zipcode: formData.get('zipcode') || '',
            billing_address_id: formData.get('billing_address_id') || null,
            delivery_distance: deliveryDistance,
            delivery_fee: deliveryFee,
            delivery_type: deliveryType,
            // ✅ Add vehicle assignment data
            assigned_vehicle_id: vehicleData.assigned_vehicle_id,
            assigned_vehicle_type: vehicleData.assigned_vehicle_type,
            total_cubic_meters: vehicleData.total_cubic_meters,
            total_weight_kg: vehicleData.total_weight_kg,
            total_width: vehicleData.total_width,
            total_height: vehicleData.total_height,
            total_length: vehicleData.total_length
        };

            // ✅ VALIDATION: Check required fields
            const requiredFields = ['customer_name', 'email', 'mobile', 'address', 'zipcode'];
            const missingFields = requiredFields.filter(field => !orderDetails[field]);
            
            if (missingFields.length > 0) {
                throw new Error('Missing required fields: ' + missingFields.join(', '));
            }

            console.log('Creating PayMongo session with data:', {
                amount: amount,
                delivery_fee: deliveryFee,
                delivery_type: deliveryType,
                vehicle_data: vehicleData,
                order_details: orderDetails
            });

            // ✅ FIXED: Send complete data to PHP
            const response = await fetch('checkout-paymongo-create-sessions-page-12-A.php', {
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
                throw new Error('Server error: Check checkout-paymongo-create-sessions-page-12-A.php file for syntax errors.');
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

function showQRPaymentOption() {
    if (window.paymentSystem) {
        window.paymentSystem.switchPaymentMethod('QR Payment');
    }
}

window.showBankSelection = showBankSelection;
window.showPayPalOption = showPayPalOption;
window.showPayMongoOption = showPayMongoOption;
window.showQRPaymentOption = showQRPaymentOption;

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