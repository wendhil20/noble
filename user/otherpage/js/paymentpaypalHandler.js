// paypalHandler.js - Handles PayPal payment method

class PayPalHandler extends PaymentHandler {
    constructor() {
        super('PayPal');
        this.paypalConfig = null;
        this.isPayPalReady = false;
        this.currentAmount = 0;
    }

    // Show PayPal payment UI
    show() {
        const bankTransferFields = document.getElementById('bankTransferFields');
        const paypalFields = document.getElementById('paypalFields');
        
        // Hide bank transfer fields
        if (bankTransferFields) {
            bankTransferFields.classList.add('hidden');
        }

        // Show PayPal fields
        if (paypalFields) {
            paypalFields.classList.remove('hidden');
            this.initializePayPalInterface();
        }
    }

    // Hide PayPal payment UI
    hide() {
        const paypalFields = document.getElementById('paypalFields');
        if (paypalFields) {
            paypalFields.classList.add('hidden');
        }
    }

    // Initialize PayPal interface
    initializePayPalInterface() {
        this.updatePayPalAmount();
        this.renderPayPalInterface();
    }

    // Render PayPal payment interface
    renderPayPalInterface() {
        const paypalFields = document.getElementById('paypalFields');
        if (!paypalFields) return;

        // Check if PayPal SDK is loaded
        if (typeof paypal === 'undefined') {
            this.renderPayPalError('PayPal SDK not loaded. Please refresh the page.');
            return;
        }

        paypalFields.innerHTML = `
            ${this.renderPayPalHeader()}
            ${this.renderPayPalDetails()}
            ${this.renderPayPalButtonContainer()}
        `;

        // Initialize PayPal buttons
        this.initializePayPalButtons();
    }

    // Render PayPal header section
    renderPayPalHeader() {
        return `
            <div class="flex items-center gap-3 mb-4">
                <div class="text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8">
                        <path fill="#003087" d="M15.7 4.2h6.5c2.2 0 3.9.5 5.1 1.5 1.1.9 1.6 2.3 1.3 4.2-.7 4.8-3.6 6.8-8.5 6.8h-2.2c-.5 0-.9.3-1 .8l-1 6.6c0 .3-.3.5-.6.5H11c-.5 0-.9-.4-.8-.9L13.5 5c.1-.4.4-.8.9-.8h1.3z" />
                        <path fill="#009cde" d="M26.8 10.6c-.3 2-1.2 3.6-2.6 4.6-1.4 1-3.3 1.5-5.7 1.5h-2.4c-.5 0-.9.3-1 .8l-1.1 7.1c0 .3-.3.5-.6.5h-3.4c-.5 0-.9-.4-.8-.9l2.4-15.6c.1-.4.4-.8.9-.8h7.2c1.4 0 2.6.2 3.6.6 1.5.6 2.1 2 1.9 3.2z" />
                    </svg>
                </div>
                <div>
                    <h5 class="font-bold text-blue-800">PayPal Payment</h5>
                    <p class="text-sm text-blue-600">Secure payment with PayPal - No account required</p>
                </div>
            </div>
        `;
    }

    // Render PayPal payment details
    renderPayPalDetails() {
        return `
            <div class="bg-blue-100 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="font-bold text-blue-800 text-lg" id="paypalAmount">₱0.00</span>
                    </div>
                </div>
                
                <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded">
                    <div class="text-xs text-blue-700">
                        <div class="font-medium mb-2">PayPal Benefits:</div>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Safe and secure payment processing</li>
                            <li>Pay with PayPal balance, bank account, or credit card</li>
                            <li>No need to share financial details with merchant</li>
                            <li>Instant payment confirmation</li>
                            <li>Buyer protection included</li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    }

    // Render PayPal button container
    renderPayPalButtonContainer() {
        return `
            <div id="paypal-button-container" class="min-h-[50px]">
                <div class="flex items-center justify-center p-4 bg-gray-50 border border-gray-200 rounded">
                    <div class="text-gray-500">Loading PayPal...</div>
                </div>
            </div>
            
            <div class="mt-3 text-center text-sm text-gray-500">
                You will be redirected to PayPal to complete your payment securely
            </div>
        `;
    }

    // Render PayPal error message
    renderPayPalError(message) {
        const paypalFields = document.getElementById('paypalFields');
        if (!paypalFields) return;

        paypalFields.innerHTML = `
            ${this.renderPayPalHeader()}
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                <svg class="mx-auto w-12 h-12 text-red-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h6 class="font-bold text-red-800 mb-2">PayPal Error</h6>
                <p class="text-red-700">${message}</p>
                <button type="button" onclick="window.location.reload()" 
                        class="mt-3 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    Refresh Page
                </button>
            </div>
        `;
    }

    // Initialize PayPal buttons
    initializePayPalButtons() {
        const container = document.getElementById('paypal-button-container');
        if (!container) return;

        try {
            // Clear existing content
            container.innerHTML = '';

            // Get current amount
            const amount = this.getCurrentAmount();
            if (!amount || amount <= 0) {
                this.renderPayPalError('Invalid order amount. Please refresh and try again.');
                return;
            }

            // Initialize PayPal buttons
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'paypal',
                    height: 40
                },

                // Create order function
                createOrder: (data, actions) => {
                    console.log('PayPal createOrder called with amount:', amount);
                    
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: amount.toString(),
                                currency_code: 'PHP'
                            },
                            description: `Noble Home Construction - Order Total: ₱${amount}`
                        }],
                        application_context: {
                            shipping_preference: 'NO_SHIPPING',
                            user_action: 'PAY_NOW',
                            brand_name: 'Noble Home Construction'
                        }
                    });
                },

                // Handle successful payment approval
                onApprove: (data, actions) => {
                    console.log('PayPal payment approved:', data);
                    
                    return actions.order.capture().then((details) => {
                        console.log('PayPal payment captured:', details);
                        this.handlePayPalSuccess(details);
                    });
                },

                // Handle payment errors
                onError: (err) => {
                    console.error('PayPal payment error:', err);
                    this.handlePayPalError(err);
                },

                // Handle payment cancellation
                onCancel: (data) => {
                    console.log('PayPal payment cancelled:', data);
                    this.handlePayPalCancel();
                }

            }).render('#paypal-button-container');

            this.isPayPalReady = true;
            console.log('PayPal buttons initialized successfully');

        } catch (error) {
            console.error('Error initializing PayPal buttons:', error);
            this.renderPayPalError('Failed to initialize PayPal. Please refresh the page.');
        }
    }

    // Get current order amount
    getCurrentAmount() {
        const grandTotalElement = document.getElementById('grandTotalDisplay');
        if (!grandTotalElement) return 0;

        // Extract numeric value from the display text (remove ₱ and commas)
        const totalText = grandTotalElement.textContent.replace(/[₱,]/g, '');
        const amount = parseFloat(totalText);
        
        return isNaN(amount) ? 0 : amount;
    }

    // Handle successful PayPal payment
    handlePayPalSuccess(details) {
        try {
            // Show success message
            this.showNotification('PayPal payment completed successfully! Processing order...', 'success');

            // Add PayPal transaction data to the form
            this.addPayPalDataToForm(details);

            // Submit the form to complete the order
            this.submitPayPalOrder();

        } catch (error) {
            console.error('Error handling PayPal success:', error);
            this.showNotification('Payment completed but order processing failed. Please contact support.', 'error');
        }
    }

    // Handle PayPal payment errors
    handlePayPalError(error) {
        console.error('PayPal error details:', error);
        
        let errorMessage = 'PayPal payment failed. ';
        
        if (error.message) {
            errorMessage += error.message;
        } else {
            errorMessage += 'Please try again or use a different payment method.';
        }

        this.showNotification(errorMessage, 'error');

        // Re-enable the place order button
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
        }
    }

    // Handle PayPal payment cancellation
    handlePayPalCancel() {
        this.showNotification('PayPal payment was cancelled. You can try again or choose a different payment method.', 'info');

        // Re-enable the place order button
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
        }
    }

    // Add PayPal transaction data to form
    addPayPalDataToForm(details) {
        const form = document.getElementById('checkoutForm');
        if (!form) {
            throw new Error('Checkout form not found');
        }

        // Remove existing PayPal inputs
        const existingInputs = form.querySelectorAll('input[name^="paypal_"]');
        existingInputs.forEach(input => input.remove());

        // Extract transaction details
        const transaction = details.purchase_units[0].payments.captures[0];
        const payer = details.payer;

        // Add PayPal data as hidden inputs
        const paypalData = {
            paypal_order_id: details.id,
            paypal_transaction_id: transaction.id,
            paypal_status: details.status,
            paypal_amount: transaction.amount.value,
            paypal_currency: transaction.amount.currency_code,
            paypal_payer_id: payer.payer_id,
            paypal_payer_email: payer.email_address || '',
            paypal_payer_name: `${payer.name.given_name || ''} ${payer.name.surname || ''}`.trim()
        };

        // Create hidden inputs for each piece of PayPal data
        Object.entries(paypalData).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value || '';
            form.appendChild(input);
        });

        console.log('PayPal data added to form:', paypalData);
    }

    // Submit PayPal order to server
    submitPayPalOrder() {
        const form = document.getElementById('checkoutForm');
        if (!form) {
            this.showNotification('Form not found. Please refresh the page.', 'error');
            return;
        }

        // Add PayPal checkout flag
        const paypalFlag = document.createElement('input');
        paypalFlag.type = 'hidden';
        paypalFlag.name = 'paypal_checkout';
        paypalFlag.value = '1';
        form.appendChild(paypalFlag);

        // Show processing message
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.textContent = 'Processing PayPal Order...';
            placeOrderBtn.disabled = true;
        }

        // Submit form normally (allows PHP to handle redirect)
        form.submit();
    }

    // Update PayPal amount display
    updatePayPalAmount() {
        const grandTotal = this.updateTotalAmount();
        const paypalAmountElement = document.getElementById('paypalAmount');

        if (paypalAmountElement) {
            paypalAmountElement.textContent = grandTotal;
        }

        // Store current amount
        this.currentAmount = this.getCurrentAmount();

        // Reinitialize PayPal buttons if amount changed and PayPal is ready
        if (this.isPayPalReady && this.currentAmount > 0) {
            this.initializePayPalButtons();
        }
    }

    // Validate PayPal payment method
    validatePaymentMethod() {
        // Check if PayPal SDK is loaded
        if (typeof paypal === 'undefined') {
            console.error('PayPal SDK not loaded');
            return false;
        }

        // Check if amount is valid
        const amount = this.getCurrentAmount();
        if (!amount || amount <= 0) {
            console.error('Invalid PayPal amount:', amount);
            return false;
        }

        // Check if delivery fee is calculated
        const deliveryFeeInput = document.getElementById('deliveryFee');
        const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;

        if (deliveryFee === null || deliveryFee === undefined) {
            console.error('Delivery fee not calculated');
            return false;
        }

        return true;
    }

    // Process PayPal payment (called by form submission)
    processPayment(event) {
        // For PayPal, we don't prevent the default form submission
        // because we need to allow server-side redirect handling
        
        // Just do final validation
        if (!this.validatePaymentMethod()) {
            event.preventDefault();
            this.showNotification('Please wait for PayPal to load completely, then try again.', 'error');
            return;
        }

        // Show processing message
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.textContent = 'Redirecting to PayPal...';
            placeOrderBtn.disabled = true;
        }

        this.showNotification('Redirecting to PayPal for secure payment...', 'info');

        // Let the form submit normally - server will handle PayPal order creation and redirect
    }

    // Method called when delivery fee changes
    onDeliveryFeeUpdate() {
        this.updatePayPalAmount();
    }

    // Method called when order total changes
    onOrderTotalUpdate() {
        this.updatePayPalAmount();
    }
}

// Export for use in other modules (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PayPalHandler;
}