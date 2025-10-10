// checkoutForm.js - Checkout form handling and submission

function initializeCheckoutForm() {
    console.log('Initializing checkout form...');
    
    const checkoutForm = document.querySelector('#checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');

    if (!checkoutForm) {
        console.error('Checkout form not found');
        return;
    }

    if (!placeOrderBtn) {
        console.error('Place order button not found');
        return;
    }

    // Form submission handler
    checkoutForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Always prevent default form submission
        
        console.log('Form submission initiated');

        // Validate all steps before submission
        if (!validateAllSteps()) {
            return;
        }

        // Validate delivery calculation
        if (!validateDeliveryCalculation()) {
            return;
        }

        // Check selected payment method
        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
        
        if (!selectedPaymentMethod) {
            showNotification('Please select a payment method.', 'error');
            return;
        }

        const paymentMethod = selectedPaymentMethod.value;
        console.log('Payment method selected:', paymentMethod);

        // Handle different payment methods
        if (paymentMethod === 'PayPal') {
            handlePayPalSubmission(checkoutForm, placeOrderBtn);
        } else if (paymentMethod === 'PayMongo') {
            handlePayMongoSubmission(checkoutForm, placeOrderBtn);
        } else if (paymentMethod === 'Bank Transfer') {
            handleBankTransferSubmission(checkoutForm, placeOrderBtn);
        } else {
            showNotification('Invalid payment method selected.', 'error');
        }
    });

    console.log('Checkout form initialized');
}

/**
 * Validate all checkout steps
 */
function validateAllSteps() {
    console.log('Validating all steps...');
    
    // Step 1: Customer info (readonly, always valid)
    const customerName = document.querySelector('input[name="customer_name"]');
    const email = document.querySelector('input[name="email"]');
    
    if (!customerName || !customerName.value.trim()) {
        showNotification('Customer name is required.', 'error');
        goToStep(1);
        return false;
    }
    
    if (!email || !email.value.trim()) {
        showNotification('Email is required.', 'error');
        goToStep(1);
        return false;
    }

    // Step 2: Address selection
    const selectedBillingAddress = document.querySelector('input[name="billing_address_id"]:checked');
    
    if (!selectedBillingAddress) {
        showNotification('Please select a delivery address.', 'error');
        goToStep(2);
        return false;
    }

    // Step 3: Delivery calculation
    if (!validateDeliveryCalculation()) {
        goToStep(3);
        return false;
    }

    // Step 4: Payment method
    const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
    
    if (!selectedPayment) {
        showNotification('Please select a payment method.', 'error');
        goToStep(4);
        return false;
    }

    // Validate Bank Transfer specific requirements
    if (selectedPayment.value === 'Bank Transfer') {
        const selectedBank = document.getElementById('selectedBank');
        const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');
        
        if (!selectedBank || !selectedBank.value) {
            showNotification('Please select a bank for transfer.', 'error');
            return false;
        }
        
        if (!paymentScreenshot || !paymentScreenshot.files || !paymentScreenshot.files[0]) {
            showNotification('Please upload a payment screenshot.', 'error');
            return false;
        }
    }

    console.log('All steps validated successfully');
    return true;
}

/**
 * Validate delivery calculation
 */
function validateDeliveryCalculation() {
    console.log('Validating delivery calculation...');
    
    const deliveryDistanceInput = document.getElementById('deliveryDistance');
    const deliveryFeeInput = document.getElementById('deliveryFee');
    
    if (!deliveryDistanceInput || !deliveryFeeInput) {
        console.error('Delivery inputs not found:', {
            distance: !!deliveryDistanceInput,
            fee: !!deliveryFeeInput
        });
        showNotification('Delivery calculation fields not found. Please refresh the page.', 'error');
        return false;
    }
    
    const deliveryDistance = parseFloat(deliveryDistanceInput.value || '0');
    const deliveryFee = parseFloat(deliveryFeeInput.value || '0');
    
    console.log('Delivery values:', {
        distance: deliveryDistance,
        fee: deliveryFee,
        distanceRaw: deliveryDistanceInput.value,
        feeRaw: deliveryFeeInput.value
    });
    
    if (deliveryDistance <= 0) {
        console.error('Invalid distance:', deliveryDistance);
        showNotification('Please calculate delivery distance before placing your order (Step 3).', 'error');
        return false;
    }
    
    if (deliveryFee < 0) {
        console.error('Invalid fee:', deliveryFee);
        showNotification('Invalid delivery fee. Please recalculate.', 'error');
        return false;
    }
    
    console.log('✓ Delivery validated successfully:', { distance: deliveryDistance, fee: deliveryFee });
    return true;
}

/**
 * Handle PayPal submission
 */
function handlePayPalSubmission(form, button) {
    console.log('Processing PayPal payment...');
    
    const originalText = button.textContent;
    button.textContent = 'Redirecting to PayPal...';
    button.disabled = true;

    showNotification('Redirecting to PayPal for secure payment...', 'info');

    // Add hidden field to identify PayPal submission
    let paypalFlag = form.querySelector('input[name="paypal_checkout"]');
    if (!paypalFlag) {
        paypalFlag = document.createElement('input');
        paypalFlag.type = 'hidden';
        paypalFlag.name = 'paypal_checkout';
        paypalFlag.value = '1';
        form.appendChild(paypalFlag);
    }

    // Submit form normally (allows PHP to handle PayPal redirect)
    form.submit();
}

/**
 * Handle PayMongo submission
 */
function handlePayMongoSubmission(form, button) {
    console.log('Processing PayMongo payment...');
    
    // PayMongo is handled by the payment system
    // Just ensure delivery is calculated
    const deliveryFeeInput = document.getElementById('deliveryFee');
    const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : 0;

    if (deliveryFee < 0 || isNaN(deliveryFee)) {
        showNotification('Please calculate delivery fee first.', 'error');
        return;
    }

    // Let the payment system handle PayMongo
    if (window.paymentSystem && typeof window.paymentSystem.handlePayMongoPayment === 'function') {
        window.paymentSystem.handlePayMongoPayment();
    } else {
        console.error('PayMongo payment handler not found');
        showNotification('PayMongo payment system not initialized. Please refresh the page.', 'error');
    }
}

/**
 * Handle Bank Transfer submission
 */
function handleBankTransferSubmission(form, button) {
    console.log('Processing Bank Transfer order...');
    
    const originalText = button.textContent;
    button.textContent = 'Processing Order...';
    button.disabled = true;

    // Validate delivery calculation one more time
    if (!validateDeliveryCalculation()) {
        button.textContent = originalText;
        button.disabled = false;
        return;
    }

    const formData = new FormData(form);

    // Log form data for debugging
    console.log('Submitting form data...');

    // Send AJAX request
    fetch('', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Check if redirected
        if (response.redirected || (response.status >= 300 && response.status < 400)) {
            console.log('Redirect detected:', response.url);
            window.location.href = response.url;
            return null;
        }

        // Check content type
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text();
        }
    })
    .then(data => {
        // Handle null response (from redirects)
        if (data === null) {
            return;
        }

        // Handle JSON response
        if (typeof data === 'object' && data !== null) {
            if (data.success) {
                showNotification('Order placed successfully! Redirecting...', 'success');
                
                if (data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 1500);
                } else {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                const errorMessage = data.message || data.error || 'An error occurred while processing your order.';
                showNotification(errorMessage, 'error');
                button.textContent = originalText;
                button.disabled = false;
            }
            return;
        }

        // Handle HTML/text response
        if (typeof data === 'string') {
            // Check for success indicators
            if (data.includes('order_receipt.php') || 
                data.includes('success') || 
                data.includes('Order placed')) {
                
                showNotification('Order placed successfully!', 'success');
                
                // Try to extract redirect URL
                const urlMatch = data.match(/order_receipt\.php\?order_id=(\d+)/);
                if (urlMatch) {
                    setTimeout(() => {
                        window.location.href = 'order_receipt.php?order_id=' + urlMatch[1];
                    }, 1500);
                } else {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
                return;
            }

            // Check for error messages in HTML
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const errorDiv = doc.querySelector('.bg-red-100, .alert-danger, .error-message');

            if (errorDiv) {
                let errorText = errorDiv.textContent
                    .replace(/Error:/gi, '')
                    .replace(/Warning:/gi, '')
                    .trim();
                
                showNotification(errorText || 'An error occurred.', 'error');
            } else {
                showNotification('An unexpected error occurred. Please try again.', 'error');
            }

            button.textContent = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Form submission error:', error);
        
        let errorMessage = 'A network error occurred. Please check your connection and try again.';
        
        if (error.message.includes('HTTP')) {
            errorMessage = 'Server error: ' + error.message;
        } else if (error.message) {
            errorMessage = error.message;
        }
        
        showNotification(errorMessage, 'error');
        button.textContent = originalText;
        button.disabled = false;
    });
}

/**
 * Helper function to extract error message from HTML
 */
function extractErrorFromHTML(htmlString) {
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlString, 'text/html');
        
        // Look for common error containers
        const selectors = [
            '.bg-red-100',
            '.alert-danger',
            '.error-message',
            '.error',
            '[class*="error"]'
        ];
        
        for (const selector of selectors) {
            const element = doc.querySelector(selector);
            if (element) {
                return element.textContent
                    .replace(/Error:/gi, '')
                    .replace(/Warning:/gi, '')
                    .trim();
            }
        }
        
        return null;
    } catch (error) {
        console.error('Error parsing HTML:', error);
        return null;
    }
}

console.log('checkoutForm.js loaded successfully');