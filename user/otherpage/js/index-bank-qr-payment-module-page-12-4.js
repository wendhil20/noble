// bank-qr-payment-module.js - Bank Transfer & QR Payment Functions
console.log('Loading Bank Transfer & QR Payment Module...');

class BankQRPaymentModule {
    constructor() {
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
                color: 'orange'
            }
        };
    }

    // ==================== BANK TRANSFER FUNCTIONS ====================
    
    renderBankTransferInterface() {
        const bankFields = document.getElementById('bankTransferFields');
        const bankSelectionArea = document.getElementById('bankSelectionArea');
        if (!bankSelectionArea) {
            console.warn('⚠️ bankSelectionArea not found');
            return;
        }

        bankSelectionArea.innerHTML = `
            <div class="space-y-4">
                <h5 class="font-bold text-blue-800 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                        <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                    </svg>
                    Select Bank for Transfer <span class="text-red-600">*</span>
                </h5>
                <div class="grid gap-3">
                    ${Object.entries(this.bankAccounts).map(([bankCode, bankInfo]) => `
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-400 transition-all duration-200 bank-option">
                            <input type="radio" name="bank_selection" value="${bankCode}" class="mr-3 w-4 h-4 bank-radio" required />
                            <div class="flex items-center flex-1">
                                <div class="w-10 h-10 bg-${bankInfo.color}-600 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                    ${bankCode.substring(0, 2)}
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-800">${bankInfo.name}</div>
                                    <div class="text-sm text-gray-600">${bankCode}</div>
                                </div>
                            </div>
                        </label>
                    `).join('')}
                </div>
                <div id="bankDetailsArea" class="hidden"></div>
            </div>
        `;

        // Add event listeners for bank selection
        const bankRadios = bankSelectionArea.querySelectorAll('.bank-radio');
        bankRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.checked) {
                    const bankCode = e.target.value;
                    const bankInfo = this.bankAccounts[bankCode];
                    this.selectBank(bankCode, bankInfo);
                }
            });
        });

        console.log('✓ Bank Transfer interface rendered');
    }

    selectBank(bankCode, bankInfo) {
        console.log('🏦 Bank selected:', bankCode);
        
        const selectedBankInput = document.getElementById('selectedBank');
        if (selectedBankInput) {
            selectedBankInput.value = bankCode;
        }

        this.showBankDetails(bankInfo);
        
        // ✅ Set vehicle data to NULL for bank transfer (no delivery calculation yet)
        const vehicleInputs = [
            'assigned_vehicle_id',
            'assigned_vehicle_type',
            'total_cubic_meters',
            'total_weight_kg',
            'total_width',
            'total_height',
            'total_length'
        ];
        
        vehicleInputs.forEach(inputName => {
            let input = document.getElementById(inputName);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.id = inputName;
                input.name = inputName;
                document.querySelector('form').appendChild(input);
            }
            input.value = ''; // Empty = NULL in database
        });
        
        console.log('✅ Vehicle data cleared for bank transfer');
        
        // Enable button after bank selection (before screenshot upload)
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.disabled = true; // Still disabled until screenshot
            placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            console.log('🏦 Bank selected, waiting for screenshot...');
        }
    }

    showBankDetails(bankInfo) {
        const bankDetailsArea = document.getElementById('bankDetailsArea');
        if (!bankDetailsArea) {
            console.warn('⚠️ bankDetailsArea not found');
            return;
        }

        const grandTotalElement = document.getElementById('grandTotalDisplay');
        const totalAmount = grandTotalElement ? grandTotalElement.textContent : '₱0.00';

        bankDetailsArea.classList.remove('hidden');
        bankDetailsArea.innerHTML = `
            <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 mt-4 animate-fadeIn">
                <h6 class="font-bold text-blue-800 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    Bank Transfer Details
                </h6>
                
                <div class="bg-white rounded-lg p-4 space-y-3 mb-4">
                    <div class="flex justify-between items-center border-b pb-2">
                        <span class="text-gray-600 font-medium">Account Name:</span>
                        <span class="font-semibold text-gray-800">${bankInfo.accountName}</span>
                    </div>
                    <div class="flex justify-between items-center border-b pb-2">
                        <span class="text-gray-600 font-medium">Account Number:</span>
                        <span class="font-semibold text-gray-800">${bankInfo.accountNumber}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2">
                        <span class="text-gray-700 font-semibold">Amount to Transfer:</span>
                        <span class="font-bold text-green-600 text-xl">${totalAmount}</span>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-yellow-800 flex items-start">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>Transfer the exact amount, then upload your payment screenshot below</span>
                    </p>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block font-semibold mb-2 text-gray-800">
                            Payment Screenshot <span class="text-red-600">*</span>
                        </label>
                        <input type="file" 
                               name="payment_screenshot" 
                               accept="image/*" 
                               required 
                               class="w-full border-2 border-gray-300 px-3 py-2 rounded-lg file-input focus:border-blue-500 focus:ring-2 focus:ring-blue-200" 
                               onchange="window.bankQRModule.validateBankTransferForm()" />
                        <p class="text-xs text-gray-600 mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                            </svg>
                            Upload proof of bank transfer (screenshot or photo)
                        </p>
                    </div>
                    
                    <div>
                        <label class="block font-semibold mb-2 text-gray-800">Reference Number (Optional)</label>
                        <input type="text" 
                               name="reference_number_input" 
                               id="reference_number_input"
                               class="w-full border-2 border-gray-300 px-3 py-2 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200" 
                               placeholder="Enter bank reference number"
                               onchange="document.getElementById('referenceNumber').value = this.value" />
                        <p class="text-xs text-gray-600 mt-1">Enter transaction reference for faster verification</p>
                    </div>
                </div>
            </div>
        `;

        console.log('✓ Bank details displayed');
    }

    validateBankTransferForm() {
        const selectedBank = document.getElementById('selectedBank');
        const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');
        
        const bankSelected = selectedBank && selectedBank.value;
        const screenshotUploaded = paymentScreenshot && paymentScreenshot.files && paymentScreenshot.files.length > 0;
        
        console.log('🏦 Bank Transfer Validation:', { bankSelected, screenshot: screenshotUploaded });
        
        // Direct button manipulation
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            if (bankSelected && screenshotUploaded) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                console.log('✅ Place Order button ENABLED');
                return true;
            } else {
                placeOrderBtn.disabled = true;
                placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                console.log('❌ Place Order button DISABLED');
                return false;
            }
        }
        
        return bankSelected && screenshotUploaded;
    }

    // ==================== QR PAYMENT FUNCTIONS ====================
    
    renderQRPaymentInterface() {
        const qrFields = document.getElementById('qrPaymentFields');
        if (!qrFields) {
            console.warn('⚠️ qrPaymentFields not found');
            return;
        }

        // Setup QR method selection listeners
        const qrRadios = document.querySelectorAll('.qr-method-radio');
        qrRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.checked) {
                    const methodInfo = {
                        methodName: e.target.dataset.methodName,
                        accountName: e.target.dataset.accountName,
                        accountNumber: e.target.dataset.accountNumber,
                        qrImage: e.target.dataset.qrImage,
                        instructions: e.target.dataset.instructions
                    };
                    this.selectQRMethod(e.target.value, methodInfo);
                }
            });
        });

        console.log('✓ QR Payment interface listeners setup');
    }

    selectQRMethod(methodId, methodInfo) {
        console.log('📱 QR method selected:', methodId);
        
        const selectedQRInput = document.getElementById('selectedQRMethod');
        if (selectedQRInput) {
            selectedQRInput.value = methodId;
        }

        // ✅ Set vehicle data to NULL for QR payment (no delivery calculation yet)
        const vehicleInputs = [
            'assigned_vehicle_id',
            'assigned_vehicle_type',
            'total_cubic_meters',
            'total_weight_kg',
            'total_width',
            'total_height',
            'total_length'
        ];
        
        vehicleInputs.forEach(inputName => {
            let input = document.getElementById(inputName);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.id = inputName;
                input.name = inputName;
                document.querySelector('form').appendChild(input);
            }
            input.value = ''; // Empty = NULL in database
        });
        
        console.log('✅ Vehicle data cleared for QR payment');

        this.showQRDetails(methodInfo);
    }

    showQRDetails(methodInfo) {
        const qrDetailsArea = document.getElementById('qrDetailsArea');
        if (!qrDetailsArea) {
            console.warn('⚠️ qrDetailsArea not found');
            return;
        }

        const grandTotalElement = document.getElementById('grandTotalDisplay');
        const totalAmount = grandTotalElement ? grandTotalElement.textContent : '₱0.00';

        qrDetailsArea.classList.remove('hidden');
        qrDetailsArea.innerHTML = `
            <div class="bg-indigo-50 border-2 border-indigo-300 rounded-lg p-4 mt-4 animate-fadeIn">
                <h6 class="font-bold text-indigo-800 mb-3 text-base flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z" clip-rule="evenodd"/>
                        <path d="M11 4a1 1 0 10-2 0v1a1 1 0 002 0V4zM10 7a1 1 0 011 1v1h2a1 1 0 110 2h-3a1 1 0 01-1-1V8a1 1 0 011-1zM16 9a1 1 0 100 2 1 1 0 000-2zM9 13a1 1 0 011-1h1a1 1 0 110 2v2a1 1 0 11-2 0v-3zM7 11a1 1 0 100-2H4a1 1 0 100 2h3zM17 13a1 1 0 01-1 1h-2a1 1 0 110-2h2a1 1 0 011 1zM16 17a1 1 0 100-2h-3a1 1 0 100 2h3z"/>
                    </svg>
                    ${methodInfo.methodName} Payment Details
                </h6>
                
                <div class="grid md:grid-cols-2 gap-4">
                    <!-- QR Code Display -->
                    <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                        <img src="${methodInfo.qrImage}" 
                             alt="${methodInfo.methodName} QR Code" 
                             class="max-w-[200px] mx-auto rounded border-2 border-indigo-300 shadow-md cursor-pointer hover:scale-105 transition-transform"
                             onclick="window.open('${methodInfo.qrImage}', '_blank')">
                        <p class="text-sm text-gray-600 mt-3 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                            </svg>
                            Click to enlarge
                        </p>
                        <a href="${methodInfo.qrImage}" 
                           download 
                           class="inline-flex items-center justify-center w-full px-4 py-2 mt-3 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download QR Code
                        </a>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="space-y-3">
                        <div class="bg-white rounded-lg p-3 shadow-sm">
                            <div class="text-gray-500 text-sm font-medium">Account Name</div>
                            <div class="font-semibold text-gray-800">${methodInfo.accountName}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 shadow-sm">
                            <div class="text-gray-500 text-sm font-medium">Account Number</div>
                            <div class="font-semibold text-gray-800">${methodInfo.accountNumber}</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 shadow-sm border-t-4 border-green-500">
                            <div class="text-gray-700 font-semibold text-sm">Amount to Pay</div>
                            <div class="font-bold text-green-600 text-2xl">${totalAmount}</div>
                        </div>
                        
                        ${methodInfo.instructions ? `
                            <div class="bg-blue-50 border border-blue-300 rounded-lg p-3">
                                <p class="text-sm text-blue-800 flex items-start">
                                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>${methodInfo.instructions}</span>
                                </p>
                            </div>
                        ` : ''}
                    </div>
                </div>
                
                <!-- Payment Screenshot Upload -->
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block font-semibold mb-2 text-gray-800">
                            Payment Screenshot <span class="text-red-600">*</span>
                        </label>
                        <input type="file" 
                               name="qr_payment_screenshot" 
                               accept="image/*" 
                               required 
                               class="w-full border-2 border-gray-300 px-3 py-2 rounded-lg file-input focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" 
                               onchange="window.bankQRModule.validateQRPaymentForm()" />
                        <p class="text-sm text-gray-600 mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                            </svg>
                            Upload your payment confirmation screenshot
                        </p>
                    </div>
                    
                    <div>
                        <label class="block font-semibold mb-2 text-gray-800">Reference Number (Optional)</label>
                        <input type="text" 
                               name="qr_reference_number_input" 
                               id="qr_reference_number_input"
                               class="w-full border-2 border-gray-300 px-3 py-2 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" 
                               placeholder="Enter transaction reference" 
                               onchange="document.getElementById('qrReferenceNumber').value = this.value" />
                        <p class="text-xs text-gray-600 mt-1">Enter transaction ID for faster verification</p>
                    </div>
                </div>
            </div>
        `;

        console.log('✓ QR details displayed');
    }

    validateQRPaymentForm() {
        const selectedQR = document.getElementById('selectedQRMethod');
        const qrScreenshot = document.querySelector('input[name="qr_payment_screenshot"]');
        
        const qrSelected = selectedQR && selectedQR.value;
        const screenshotUploaded = qrScreenshot && qrScreenshot.files && qrScreenshot.files.length > 0;
        
        console.log('📱 QR Payment Validation:', { qrSelected, screenshot: screenshotUploaded });
        
        // Direct button manipulation
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            if (qrSelected && screenshotUploaded) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                console.log('✅ Place Order button ENABLED');
                return true;
            } else {
                placeOrderBtn.disabled = true;
                placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                console.log('❌ Place Order button DISABLED');
                return false;
            }
        }
        
        return qrSelected && screenshotUploaded;
    }
}

// Initialize and expose globally
window.bankQRModule = new BankQRPaymentModule();
console.log('✓ Bank & QR Payment Module loaded successfully');