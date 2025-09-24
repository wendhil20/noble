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
    
    // SHOW the place order button for server-side processing
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        placeOrderBtn.style.display = 'inline-block'; // Show the button
        placeOrderBtn.disabled = false; // Enable it
    }
    
    // Update PayPal amount
    updatePayPalAmount();
    
    // DON'T initialize PayPal buttons - let server handle it
    // initializeSimplifiedPayPalButtons(); // REMOVE THIS LINE
    
    // Show simple message instead
    const paypalContainer = document.getElementById('paypal-button-container');
    if (paypalContainer) {
        paypalContainer.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                <div class="flex items-center justify-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8">
                        <path fill="#003087" d="M15.7 4.2h6.5c2.2 0 3.9.5 5.1 1.5 1.1.9 1.6 2.3 1.3 4.2-.7 4.8-3.6 6.8-8.5 6.8h-2.2c-.5 0-.9.3-1 .8l-1 6.6c0 .3-.3.5-.6.5H11c-.5 0-.9-.4-.8-.9L13.5 5c.1-.4.4-.8.9-.8h1.3z" />
                        <path fill="#009cde" d="M26.8 10.6c-.3 2-1.2 3.6-2.6 4.6-1.4 1-3.3 1.5-5.7 1.5h-2.4c-.5 0-.9.3-1 .8l-1.1 7.1c0 .3-.3.5-.6.5h-3.4c-.5 0-.9-.4-.8-.9l2.4-15.6c.1-.4.4-.8.9-.8h7.2c1.4 0 2.6.2 3.6.6 1.5.6 2.1 2 1.9 3.2z" />
                    </svg>
                </div>
                <p class="text-blue-600 font-medium">PayPal Payment Selected</p>
                <p class="text-sm text-blue-500 mt-1">Click "Place Order" to proceed to PayPal for secure payment</p>
                <div class="mt-3">
                    <span class="text-lg font-bold text-blue-800" id="paypalAmount">₱0.00</span>
                </div>
            </div>
        `;
    }
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

// FIXED: showBankSelection function - add the missing Place Order button show logic
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

    // ADD THESE LINES - Show the Place Order button for Bank Transfer
    if (placeOrderBtn) {
        placeOrderBtn.style.display = 'inline-block';  // Show the button
        placeOrderBtn.disabled = true;  // Keep disabled until all requirements are met
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

// ADD THIS: Function to initialize the button state when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initially hide the place order button
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn) {
        placeOrderBtn.style.display = 'none';
        placeOrderBtn.disabled = true;
    }
    
    // Add event listeners to payment method radio buttons
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'PayPal') {
                showPayPalOption();
            } else if (this.value === 'Bank Transfer') {
                showBankSelection();
            }
        });
    });
});

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