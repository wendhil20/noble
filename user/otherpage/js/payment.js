// payment.js - Payment methods and processing

// PayPal Functions
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

    // Enable place order button
    enablePlaceOrderButton();
}

// Function to update PayPal amount display
function updatePayPalAmount() {
    const paypalAmountElement = document.getElementById('paypalAmount');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');

    if (paypalAmountElement && grandTotalDisplay) {
        const grandTotal = grandTotalDisplay.textContent;
        paypalAmountElement.textContent = grandTotal;
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