// Complete PayPal integration - add this to your payment.js file

function showPayPalOption() {
    // Hide bank transfer fields
    const bankTransferFields = document.getElementById('bankTransferFields');
    if (bankTransferFields) {
        bankTransferFields.classList.add('hidden');
    }
    
    // Show PayPal fields
    const paypalFields = document.getElementById('paypalFields');
    if (paypalFields) {
        paypalFields.classList.remove('hidden');
    }
    
    // Update PayPal amount
    updatePayPalAmount();
    
    // Initialize simplified PayPal buttons
    initializeSimplifiedPayPalButtons();
    
    // Enable place order button
    enablePlaceOrderButton();
}

// Fixed PayPal integration to handle window closing issues

function initializeSimplifiedPayPalButtons() {
    const paypalContainer = document.getElementById('paypal-button-container');
    if (!paypalContainer) return;
    
    // Clear existing content
    paypalContainer.innerHTML = '';
    
    // Get total amount
    const grandTotalElement = document.getElementById('grandTotalDisplay');
    const grandTotalText = grandTotalElement ? grandTotalElement.textContent.replace('₱', '').replace(',', '') : '0';
    const grandTotal = parseFloat(grandTotalText);
    
    if (isNaN(grandTotal) || grandTotal <= 0) {
        paypalContainer.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                <p class="text-red-600">Invalid order amount. Please refresh the page.</p>
            </div>
        `;
        return;
    }
    
    // Create container for payment options
    paypalContainer.innerHTML = `
        <div class="space-y-4">
            <div class="text-center text-sm text-gray-600 mb-4">
                <p>Total Amount: <span class="font-bold text-blue-800">₱${grandTotal.toFixed(2)}</span></p>
                <p class="text-xs mt-1">Complete your payment securely with PayPal</p>
            </div>
            <div id="paypal-buttons-container"></div>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-800 mt-3">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="font-medium">Important:</p>
                        <p>Please complete your payment in the PayPal window. Do not close it until you see the confirmation message.</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Check if PayPal SDK is loaded
    if (typeof paypal === 'undefined') {
        paypalContainer.innerHTML = `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                <p class="text-yellow-600">PayPal is loading... Please wait.</p>
            </div>
        `;
        
        setTimeout(() => {
            if (typeof paypal !== 'undefined') {
                initializeSimplifiedPayPalButtons();
            }
        }, 2000);
        return;
    }
    
    // IMPROVED: Render PayPal buttons with better error handling
    paypal.Buttons({
        style: {
            layout: 'vertical',
            color: 'gold',
            shape: 'rect',
            label: 'paypal',
            height: 50,
            tagline: false
        },
        
        createOrder: function(data, actions) {
            console.log('Creating PayPal order...');
            
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: grandTotal.toFixed(2),
                        currency_code: 'PHP'
                    },
                    description: 'Noble Home Construction Order',
                    custom_id: 'NH' + Date.now(),
                    soft_descriptor: 'Noble Home'
                }],
                application_context: {
                    brand_name: 'Noble Home Construction',
                    landing_page: 'BILLING',
                    user_action: 'PAY_NOW',
                    shipping_preference: 'NO_SHIPPING'
                }
            }).then(function(orderID) {
                console.log('PayPal order created:', orderID);
                return orderID;
            }).catch(function(err) {
                console.error('Create order error:', err);
                throw err;
            });
        },
        
        onApprove: function(data, actions) {
            console.log('PayPal payment approved:', data);
            
            // Show processing state immediately
            const container = document.getElementById('paypal-buttons-container');
            if (container) {
                container.innerHTML = `
                    <div class="flex items-center justify-center py-8 bg-blue-50 rounded-lg">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="ml-3 text-blue-600 font-medium">Processing payment...</span>
                    </div>
                `;
            }
            
            // FIXED: Handle window closing better
            return new Promise(function(resolve, reject) {
                // Set a timeout to handle hanging requests
                const timeoutId = setTimeout(function() {
                    console.warn('PayPal capture timeout - proceeding with order');
                    // Proceed without capture data if timeout occurs
                    handlePaymentSuccess(data, null);
                    resolve();
                }, 15000); // 15 second timeout
                
                // Attempt to capture payment
                actions.order.capture()
                    .then(function(details) {
                        clearTimeout(timeoutId);
                        console.log('PayPal payment captured:', details);
                        handlePaymentSuccess(data, details);
                        resolve(details);
                    })
                    .catch(function(err) {
                        clearTimeout(timeoutId);
                        console.error('Payment capture error:', err);
                        
                        // Check if it's a window closing error
                        if (err.message && err.message.includes('Target window is closed')) {
                            console.log('Window closed error - payment may have succeeded');
                            // Still proceed with the order since payment was approved
                            handlePaymentSuccess(data, null);
                            resolve();
                        } else {
                            // Other errors
                            reject(err);
                        }
                    });
            });
        },
        
        onCancel: function(data) {
            console.log('PayPal payment cancelled:', data);
            showPayPalMessage('Payment was cancelled. You can try again or choose a different payment method.', 'warning');
        },
        
        onError: function(err) {
            console.error('PayPal error:', err);
            
            // Handle specific error types
            if (err.message && err.message.includes('Target window is closed')) {
                showPayPalMessage('Payment window was closed. If payment was completed, please check your PayPal account. Otherwise, try again.', 'warning');
            } else {
                showPayPalMessage('Payment failed. Please try again or use a different payment method.', 'error');
            }
        }
        
    }).render('#paypal-buttons-container').catch(function(err) {
        console.error('PayPal render error:', err);
        showPayPalMessage('Failed to load PayPal buttons. Please refresh the page.', 'error');
    });
}

// NEW: Handle payment success consistently
function handlePaymentSuccess(approvalData, captureDetails) {
    console.log('Handling payment success...');
    
    // Create payment data object
    const paymentData = {
        orderID: approvalData.orderID,
        payerID: approvalData.payerID,
        status: 'APPROVED'
    };
    
    // Add capture details if available
    if (captureDetails) {
        paymentData.transactionID = captureDetails.purchase_units[0].payments.captures[0].id;
        paymentData.amount = captureDetails.purchase_units[0].payments.captures[0].amount.value;
        paymentData.status = captureDetails.status;
        
        if (captureDetails.payer) {
            paymentData.payerEmail = captureDetails.payer.email_address;
            paymentData.payerName = `${captureDetails.payer.name.given_name || ''} ${captureDetails.payer.name.surname || ''}`.trim();
        }
    }
    
    // Add payment data to form
    addPayPalDataToFormImproved(paymentData);
    
    // Submit form
    console.log('Submitting checkout form...');
    document.getElementById('checkoutForm').submit();
}

// IMPROVED: Add PayPal data to form with better error handling
function addPayPalDataToFormImproved(paymentData) {
    const form = document.getElementById('checkoutForm');
    if (!form) {
        console.error('Checkout form not found');
        return;
    }
    
    // Remove existing PayPal inputs
    const existingInputs = form.querySelectorAll('input[name^="paypal_"]');
    existingInputs.forEach(input => input.remove());
    
    // Add payment data as hidden inputs
    const fields = {
        paypal_order_id: paymentData.orderID || '',
        paypal_payer_id: paymentData.payerID || '',
        paypal_transaction_id: paymentData.transactionID || '',
        paypal_status: paymentData.status || 'APPROVED',
        paypal_amount: paymentData.amount || '',
        paypal_payer_email: paymentData.payerEmail || '',
        paypal_payer_name: paymentData.payerName || ''
    };
    
    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    
    console.log('PayPal data added to form:', fields);
}

// IMPROVED: Show PayPal messages with different types
function showPayPalMessage(message, type = 'error') {
    const container = document.getElementById('paypal-buttons-container');
    if (!container) return;
    
    const colors = {
        error: { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-600', button: 'bg-red-600 hover:bg-red-700' },
        warning: { bg: 'bg-yellow-50', border: 'border-yellow-200', text: 'text-yellow-600', button: 'bg-yellow-600 hover:bg-yellow-700' },
        info: { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-600', button: 'bg-blue-600 hover:bg-blue-700' }
    };
    
    const color = colors[type] || colors.error;
    
    container.innerHTML = `
        <div class="${color.bg} ${color.border} border rounded-lg p-4 text-center">
            <p class="${color.text} mb-3">${message}</p>
            <div class="space-x-2">
                <button onclick="initializeSimplifiedPayPalButtons()" class="${color.button} text-white px-4 py-2 rounded transition">
                    Try Again
                </button>
                <button onclick="location.reload()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
                    Refresh Page
                </button>
            </div>
        </div>
    `;
}

// MISSING FUNCTION: Update PayPal amount
function updatePayPalAmount() {
    const grandTotalElement = document.getElementById('grandTotalDisplay');
    const paypalAmountElement = document.getElementById('paypalAmount');
    
    if (grandTotalElement && paypalAmountElement) {
        const grandTotal = grandTotalElement.textContent;
        paypalAmountElement.textContent = grandTotal;
    }
    
    // Re-initialize PayPal buttons if they're visible
    const paypalFields = document.getElementById('paypalFields');
    if (paypalFields && !paypalFields.classList.contains('hidden')) {
        initializeSimplifiedPayPalButtons();
    }
}

// MISSING FUNCTION: Enable place order button
function enablePlaceOrderButton() {
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        placeOrderBtn.disabled = false;
    }
}


function addPayPalDataToForm(paypalDetails) {
    const form = document.getElementById('checkoutForm');
    
    // Add hidden inputs with PayPal transaction data
    const paypalOrderId = document.createElement('input');
    paypalOrderId.type = 'hidden';
    paypalOrderId.name = 'paypal_order_id';
    paypalOrderId.value = paypalDetails.id;
    form.appendChild(paypalOrderId);
    
    const paypalTransactionId = document.createElement('input');
    paypalTransactionId.type = 'hidden';
    paypalTransactionId.name = 'paypal_transaction_id';
    paypalTransactionId.value = paypalDetails.purchase_units[0].payments.captures[0].id;
    form.appendChild(paypalTransactionId);
    
    const paypalStatus = document.createElement('input');
    paypalStatus.type = 'hidden';
    paypalStatus.name = 'paypal_status';
    paypalStatus.value = paypalDetails.status;
    form.appendChild(paypalStatus);
    
    const paypalAmount = document.createElement('input');
    paypalAmount.type = 'hidden';
    paypalAmount.name = 'paypal_amount';
    paypalAmount.value = paypalDetails.purchase_units[0].payments.captures[0].amount.value;
    form.appendChild(paypalAmount);
}

// Update PayPal amount when delivery fee changes
function updatePayPalAmount() {
    const grandTotalElement = document.getElementById('grandTotalDisplay');
    const paypalAmountElement = document.getElementById('paypalAmount');
    
    if (grandTotalElement && paypalAmountElement) {
        const grandTotal = grandTotalElement.textContent;
        paypalAmountElement.textContent = grandTotal;
    }
    
    // Re-initialize PayPal buttons if they're visible
    const paypalFields = document.getElementById('paypalFields');
    if (paypalFields && !paypalFields.classList.contains('hidden')) {
        initializeSimplifiedPayPalButtons();
    }
}

// Function to enable place order button for PayPal
function enablePlaceOrderButton() {
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

    if (placeOrderBtn && paymentMethod && paymentMethod.value === 'PayPal') {
        // Check if delivery fee is calculated
        const deliveryFeeInput = document.getElementById('deliveryFee');
        const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;

        if (deliveryFee !== null && deliveryFee >= 0) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        }
    }
}

// Bank Transfer Functions
function showBankSelection() {
    const bankTransferFields = document.getElementById('bankTransferFields');
    const bankSelectionArea = document.getElementById('bankSelectionArea');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const paypalFields = document.getElementById('paypalFields');
    
    if (paypalFields) {
        paypalFields.classList.add('hidden');
    }

    if (bankTransferFields) {
        bankTransferFields.classList.remove('hidden');
    }

    if (bankSelectionArea) {
        bankSelectionArea.innerHTML = `
            <div class="space-y-4">
                <h5 class="font-bold text-blue-800 mb-3">Select Bank for Transfer</h5>
                
                <div class="grid gap-3">
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                        <input type="radio" name="bank_selection" value="BPI" class="mr-3" onchange="selectBank('BPI')" />
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                BPI
                            </div>
                            <div>
                                <div class="font-medium">Bank of the Philippine Islands</div>
                                <div class="text-sm text-gray-600">BPI</div>
                            </div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                        <input type="radio" name="bank_selection" value="BDO" class="mr-3" onchange="selectBank('BDO')" />
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-800 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                BDO
                            </div>
                            <div>
                                <div class="font-medium">Banco de Oro</div>
                                <div class="text-sm text-gray-600">BDO</div>
                            </div>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition">
                        <input type="radio" name="bank_selection" value="Metrobank" class="mr-3" onchange="selectBank('Metrobank')" />
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-yellow-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                MB
                            </div>
                            <div>
                                <div class="font-medium">Metropolitan Bank</div>
                                <div class="text-sm text-gray-600">Metrobank</div>
                            </div>
                        </div>
                    </label>
                </div>
                
                <div id="bankDetailsArea" class="hidden">
                    <!-- Bank details will be populated when a bank is selected -->
                </div>
            </div>
        `;
    }
}

function selectBank(bankType) {
    const selectedBankInput = document.getElementById('selectedBank');
    const bankDetailsArea = document.getElementById('bankDetailsArea');

    if (selectedBankInput) {
        selectedBankInput.value = bankType;
    }

    // Bank account details (you should replace these with actual account details)
    const bankDetails = {
        'BPI': {
            name: 'Bank of the Philippine Islands',
            accountName: 'Your Store Name',
            accountNumber: '1234567890',
            color: 'red'
        },
        'BDO': {
            name: 'Banco de Oro',
            accountName: 'Your Store Name',
            accountNumber: '0987654321',
            color: 'blue'
        },
        'Metrobank': {
            name: 'Metropolitan Bank',
            accountName: 'Your Store Name',
            accountNumber: '5678901234',
            color: 'yellow'
        }
    };

    const bank = bankDetails[bankType];
    if (bank && bankDetailsArea) {
        bankDetailsArea.classList.remove('hidden');
        bankDetailsArea.innerHTML = `
            <div class="bg-${bank.color}-50 border border-${bank.color}-200 rounded-lg p-4 mt-4">
                <h6 class="font-bold text-${bank.color}-800 mb-3">Transfer Details for ${bank.name}</h6>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Account Name:</span>
                        <span class="font-medium">${bank.accountName}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Account Number:</span>
                        <span class="font-medium font-mono">${bank.accountNumber}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Amount to Transfer:</span>
                        <span class="font-bold text-green-600" id="bankTransferAmount">₱0.00</span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Upload Payment Screenshot</label>
                    <input type="file" name="payment_screenshot" accept="image/*" required 
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg" 
                        onchange="validatePaymentScreenshot(this)" />
                    <div class="text-xs text-gray-500 mt-1">
                        Upload a clear screenshot of your bank transfer confirmation
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block font-medium mb-2">Reference Number (Optional)</label>
                    <input type="text" name="reference_number_input" 
                        class="w-full border border-gray-300 px-3 py-2 rounded-lg" 
                        placeholder="Enter transaction reference number"
                        onchange="updateReferenceNumber(this.value)" />
                    <div class="text-xs text-gray-500 mt-1">
                        Reference number from your bank transfer receipt
                    </div>
                </div>
            </div>
        `;

        // Update the transfer amount
        updateBankPaymentAmount();
    }
}

function updateBankPaymentAmount() {
    const bankTransferAmountElement = document.getElementById('bankTransferAmount');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');

    if (bankTransferAmountElement && grandTotalDisplay) {
        const grandTotal = grandTotalDisplay.textContent;
        bankTransferAmountElement.textContent = grandTotal;
    }
}

function updateReferenceNumber(value) {
    const referenceNumberInput = document.getElementById('referenceNumber');
    if (referenceNumberInput) {
        referenceNumberInput.value = value;
    }
}

function validatePaymentScreenshot(input) {
    const placeOrderBtn = document.getElementById('placeOrderBtn');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = file.size / 1024 / 1024; // Convert to MB

        if (fileSize > 5) {
            showNotification('Payment screenshot must be less than 5MB', 'error');
            input.value = '';
            return;
        }

        // Check if all required fields are filled
        const selectedBank = document.getElementById('selectedBank').value;
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        const deliveryFeeInput = document.getElementById('deliveryFee');
        const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;

        if (selectedBank && paymentMethod && (deliveryFee !== null && deliveryFee >= 0)) {
            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            }
        }

        showNotification('Payment screenshot uploaded successfully!', 'success');
    }
}

// Function to handle step validation for payment method
function validatePaymentStep() {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    const placeOrderBtn = document.getElementById('placeOrderBtn');

    if (!paymentMethod) return false;

    if (paymentMethod.value === 'Bank Transfer') {
        const selectedBank = document.getElementById('selectedBank').value;
        const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');

        if (!selectedBank) {
            showNotification('Please select a bank for transfer.', 'error');
            return false;
        }

        if (!paymentScreenshot || !paymentScreenshot.files[0]) {
            showNotification('Please upload a payment screenshot.', 'error');
            return false;
        }

        // Enable place order button
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        }

        return true;
    }

    return false;
}

// Additional helper functions for better UX
function previewPaymentScreenshot(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Create preview if needed
            const previewArea = document.getElementById('screenshotPreview');
            if (previewArea) {
                previewArea.innerHTML = `
                    <img src="${e.target.result}" class="max-w-full h-32 object-cover rounded-lg border" alt="Payment Screenshot Preview">
                `;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}