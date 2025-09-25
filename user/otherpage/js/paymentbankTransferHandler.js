// bankTransferHandler.js - Handles bank transfer payment method

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
                color: 'red',
                logo: 'BPI'
            },
            'BDO': {
                name: 'Banco de Oro',
                accountName: 'Noble Home Construction', 
                accountNumber: '0987-6543-21',
                color: 'blue',
                logo: 'BDO'
            },
            'Metrobank': {
                name: 'Metropolitan Bank',
                accountName: 'Noble Home Construction',
                accountNumber: '5678-9012-34',
                color: 'yellow',
                logo: 'MB'
            }
        };
    }

    // Show bank transfer payment UI
    show() {
        const paypalFields = document.getElementById('paypalFields');
        const bankTransferFields = document.getElementById('bankTransferFields');
        
        // Hide PayPal fields
        if (paypalFields) {
            paypalFields.classList.add('hidden');
        }

        // Show bank transfer fields
        if (bankTransferFields) {
            bankTransferFields.classList.remove('hidden');
            this.renderBankSelection();
        }
    }

    // Hide bank transfer payment UI
    hide() {
        const bankTransferFields = document.getElementById('bankTransferFields');
        if (bankTransferFields) {
            bankTransferFields.classList.add('hidden');
        }
    }

    // Render bank selection interface
    renderBankSelection() {
        const bankSelectionArea = document.getElementById('bankSelectionArea');
        if (!bankSelectionArea) return;

        bankSelectionArea.innerHTML = `
            <div class="space-y-4">
                <h5 class="font-bold text-blue-800 mb-3">Select Bank for Transfer</h5>
                
                <div class="grid gap-3">
                    ${this.renderBankOptions()}
                </div>
                
                <div id="bankDetailsArea" class="hidden">
                    <!-- Bank details will be populated when a bank is selected -->
                </div>
            </div>
        `;

        // Set up event listeners for bank selection
        this.setupBankSelectionListeners();
    }

    // Render individual bank option HTML
    renderBankOptions() {
        return Object.entries(this.bankAccounts).map(([bankCode, bankInfo]) => `
            <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition bank-option" data-bank="${bankCode}">
                <input type="radio" name="bank_selection" value="${bankCode}" class="mr-3" />
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-${bankInfo.color}-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                        ${bankInfo.logo}
                    </div>
                    <div>
                        <div class="font-medium">${bankInfo.name}</div>
                        <div class="text-sm text-gray-600">${bankCode}</div>
                    </div>
                </div>
            </label>
        `).join('');
    }

    // Set up event listeners for bank selection
    setupBankSelectionListeners() {
        const bankOptions = document.querySelectorAll('.bank-option');
        
        bankOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            if (radio) {
                radio.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        this.selectBank(e.target.value);
                    }
                });
            }
        });
    }

    // Handle bank selection
    selectBank(bankCode) {
        this.selectedBank = bankCode;
        const bankInfo = this.bankAccounts[bankCode];
        
        if (!bankInfo) return;

        // Update hidden form field
        const selectedBankInput = document.getElementById('selectedBank');
        if (selectedBankInput) {
            selectedBankInput.value = bankCode;
        }

        // Show bank details
        this.showBankDetails(bankInfo);

        // Update payment manager button state
        if (window.paymentManager) {
            window.paymentManager.updatePlaceOrderButton();
        }
    }

    // Show selected bank details and upload interface
    showBankDetails(bankInfo) {
        const bankDetailsArea = document.getElementById('bankDetailsArea');
        if (!bankDetailsArea) return;

        bankDetailsArea.classList.remove('hidden');
        bankDetailsArea.innerHTML = `
            <div class="bg-${bankInfo.color}-50 border border-${bankInfo.color}-200 rounded-lg p-4 mt-4">
                <h6 class="font-bold text-${bankInfo.color}-800 mb-3">Transfer Details for ${bankInfo.name}</h6>
                <div class="space-y-3">
                    ${this.renderBankAccountDetails(bankInfo)}
                    ${this.renderPaymentScreenshotUpload()}
                    ${this.renderReferenceNumberInput()}
                </div>
                
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="text-sm">
                            <div class="font-medium text-yellow-800">Important Instructions:</div>
                            <ul class="mt-1 text-yellow-700 list-disc list-inside space-y-1">
                                <li>Transfer the exact amount shown above</li>
                                <li>Upload a clear screenshot of your transfer confirmation</li>
                                <li>Your order will be processed after payment verification</li>
                                <li>Keep your reference number for tracking</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Set up upload event listener
        this.setupFileUploadListener();

        // Update the transfer amount display
        this.updateTransferAmount();
    }

    // Render bank account details
    renderBankAccountDetails(bankInfo) {
        return `
            <div class="bg-white rounded-lg p-4 border border-gray-200">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Account Name:</span>
                        <span class="font-medium">${bankInfo.accountName}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Account Number:</span>
                        <span class="font-medium font-mono">${bankInfo.accountNumber}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <span class="text-gray-600">Amount to Transfer:</span>
                        <span class="font-bold text-green-600 text-lg" id="bankTransferAmount">₱0.00</span>
                    </div>
                </div>
            </div>
        `;
    }

    // Render payment screenshot upload section
    renderPaymentScreenshotUpload() {
        return `
            <div>
                <label class="block font-medium mb-2">Payment Screenshot <span class="text-red-500">*</span></label>
                <input type="file" 
                       name="payment_screenshot" 
                       id="paymentScreenshotInput"
                       accept="image/*" 
                       required 
                       class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                <div class="text-xs text-gray-500 mt-1">
                    Upload a clear screenshot of your bank transfer confirmation (Max: 5MB)
                </div>
                <div id="screenshotPreview" class="mt-2 hidden">
                    <!-- Preview will be shown here -->
                </div>
                <div id="uploadStatus" class="mt-2">
                    <!-- Upload status messages -->
                </div>
            </div>
        `;
    }

    // Render reference number input
    renderReferenceNumberInput() {
        return `
            <div>
                <label class="block font-medium mb-2">Reference Number (Optional)</label>
                <input type="text" 
                       name="reference_number_input" 
                       id="referenceNumberInput"
                       class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500" 
                       placeholder="Enter transaction reference number" />
                <div class="text-xs text-gray-500 mt-1">
                    Reference number from your bank transfer receipt (helps with faster processing)
                </div>
            </div>
        `;
    }

    // Set up file upload event listener
    setupFileUploadListener() {
        const fileInput = document.getElementById('paymentScreenshotInput');
        const referenceInput = document.getElementById('referenceNumberInput');

        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                this.handleFileUpload(e);
            });
        }

        if (referenceInput) {
            referenceInput.addEventListener('input', (e) => {
                this.updateReferenceNumber(e.target.value);
            });
        }
    }

    // Handle file upload
    handleFileUpload(event) {
        const file = event.target.files[0];
        const uploadStatus = document.getElementById('uploadStatus');

        if (!file) {
            this.screenshotUploaded = false;
            this.updateUploadStatus('No file selected', 'error');
            this.updatePaymentButtonState();
            return;
        }

        // Validate file
        const validationResult = this.validateUploadedFile(file);
        if (!validationResult.valid) {
            this.screenshotUploaded = false;
            this.updateUploadStatus(validationResult.message, 'error');
            event.target.value = ''; // Clear the input
            this.updatePaymentButtonState();
            return;
        }

        // File is valid, show preview
        this.screenshotUploaded = true;
        this.showFilePreview(file);
        this.updateUploadStatus('Screenshot uploaded successfully!', 'success');
        
        // Update payment button state
        this.updatePaymentButtonState();
    }

    // Validate uploaded file
    validateUploadedFile(file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: 'Invalid file type. Please upload JPG, PNG, or GIF files only.'
            };
        }

        if (file.size > maxSize) {
            return {
                valid: false,
                message: 'File size too large. Please upload a file smaller than 5MB.'
            };
        }

        return { valid: true };
    }

    // Show file preview
    showFilePreview(file) {
        const previewContainer = document.getElementById('screenshotPreview');
        if (!previewContainer) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.classList.remove('hidden');
            previewContainer.innerHTML = `
                <div class="border border-gray-300 rounded-lg p-2">
                    <img src="${e.target.result}" 
                         class="max-w-full h-32 object-cover rounded-lg mx-auto" 
                         alt="Payment Screenshot Preview">
                    <div class="text-center text-xs text-gray-500 mt-1">Preview</div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    }

    // Update upload status message
    updateUploadStatus(message, type) {
        const uploadStatus = document.getElementById('uploadStatus');
        if (!uploadStatus) return;

        const colors = {
            success: 'text-green-600 bg-green-50 border-green-200',
            error: 'text-red-600 bg-red-50 border-red-200',
            info: 'text-blue-600 bg-blue-50 border-blue-200'
        };

        uploadStatus.innerHTML = `
            <div class="text-sm p-2 border rounded ${colors[type] || colors.info}">
                ${message}
            </div>
        `;
    }

    // Update reference number in hidden field
    updateReferenceNumber(value) {
        const referenceNumberInput = document.getElementById('referenceNumber');
        if (referenceNumberInput) {
            referenceNumberInput.value = value;
        }
    }

    // Update transfer amount display
    updateTransferAmount() {
        const bankTransferAmountElement = document.getElementById('bankTransferAmount');
        const grandTotal = this.updateTotalAmount();

        if (bankTransferAmountElement) {
            bankTransferAmountElement.textContent = grandTotal;
        }
    }

    // Update payment button state
    updatePaymentButtonState() {
        if (window.paymentManager) {
            window.paymentManager.updatePlaceOrderButton();
        }
    }

    // Validate payment method requirements
    validatePaymentMethod() {
        // Check if bank is selected
        if (!this.selectedBank) {
            return false;
        }

        // Check if screenshot is uploaded
        if (!this.screenshotUploaded) {
            return false;
        }

        // Check if delivery fee is calculated
        const deliveryFeeInput = document.getElementById('deliveryFee');
        const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;

        return (deliveryFee !== null && deliveryFee >= 0);
    }

    // Process bank transfer payment
    processPayment(event) {
        const form = event.target;

        // Final validation
        if (!this.validatePaymentMethod()) {
            this.showNotification('Please complete all bank transfer requirements', 'error');
            return;
        }

        // Show loading state
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            const originalText = placeOrderBtn.textContent;
            placeOrderBtn.textContent = 'Processing Bank Transfer Order...';
            placeOrderBtn.disabled = true;

            // Submit form using AJAX
            this.submitBankTransferForm(form, placeOrderBtn, originalText);
        }
    }

    // Submit bank transfer form via AJAX
    submitBankTransferForm(form, button, originalButtonText) {
        const formData = new FormData(form);

        fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text();
                }
            })
            .then(data => {
                if (typeof data === 'object' && data.success) {
                    this.showNotification('Bank transfer order placed successfully! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 2000);
                } else {
                    this.handleFormError(data, button, originalButtonText);
                }
            })
            .catch(error => {
                console.error('Bank transfer error:', error);
                this.showNotification('A network error occurred. Please try again.', 'error');
                if (button) {
                    button.textContent = originalButtonText;
                    button.disabled = false;
                }
            });
    }

    // Handle form submission errors
    handleFormError(data, button, originalButtonText) {
        if (typeof data === 'string') {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const errorDiv = doc.querySelector('.bg-red-100, .alert-danger, .error-message');

            if (errorDiv) {
                let errorText = errorDiv.textContent.replace(/^(Error|Warning|Notice):\s*/i, '').trim();
                this.showNotification(errorText, 'error');
            } else {
                this.showNotification('An unexpected error occurred. Please try again.', 'error');
            }
        }

        // Reset button state
        if (button) {
            button.textContent = originalButtonText;
            button.disabled = false;
        }
    }
}

// Export for use in other modules (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BankTransferHandler;
}