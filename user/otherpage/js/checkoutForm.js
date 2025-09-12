// checkoutForm.js - Checkout form handling and submission

function initializeCheckoutForm() {
    const checkoutForm = document.querySelector('#checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');

    if (checkoutForm && placeOrderBtn) {
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent normal form submission

            // Final validation
            if (!validateStep(4)) {
                return;
            }

            // Check if delivery fee is calculated (considering free delivery)
            if (!selectedZone) {
                showNotification('Please select a delivery zone first.', 'error');
                return;
            }

            const deliveryFeeInput = document.getElementById('deliveryFee');
            const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;

            if (deliveryFee === null || deliveryFee === undefined) {
                if (selectedZone.zone_code === 'NCR' || selectedZone.is_free_delivery) {
                    // Auto-setup free delivery if not already done
                    setupFreeDelivery();
                } else {
                    showNotification('Please calculate delivery distance first before placing your order.', 'error');
                    return;
                }
            }

            // Check selected payment method
            const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
            const isPayPal = selectedPaymentMethod && selectedPaymentMethod.value === 'PayPal';

            // Show loading state
            const originalText = placeOrderBtn.textContent;
            placeOrderBtn.textContent = isPayPal ? 'Redirecting to PayPal...' : 'Processing Order...';
            placeOrderBtn.disabled = true;

            // For PayPal, we need to handle the redirect differently
            if (isPayPal) {
                // For PayPal, submit the form normally to allow PHP redirect
                showNotification('Redirecting to PayPal for secure payment...', 'info');

                // Add a hidden field to identify this as a PayPal submission
                const paypalFlag = document.createElement('input');
                paypalFlag.type = 'hidden';
                paypalFlag.name = 'paypal_checkout';
                paypalFlag.value = '1';
                checkoutForm.appendChild(paypalFlag);

                // Submit form normally for PayPal (allows redirect)
                checkoutForm.submit();
                return;
            }

            // For non-PayPal payments, use AJAX
            const formData = new FormData(checkoutForm);

            // Send AJAX request with proper headers
            fetch('', { // Empty string means current page
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // This identifies it as an AJAX request
                    },
                    body: formData
                })
                .then(response => {
                    // Check if response is a redirect (status 3xx)
                    if (response.redirected || (response.status >= 300 && response.status < 400)) {
                        // Handle redirect response
                        window.location.href = response.url;
                        return;
                    }

                    // Check if response is JSON or HTML
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        return response.text();
                    }
                })
                .then(data => {
                    if (typeof data === 'object' && data.success) {
                        // JSON response - success
                        showNotification('Order placed successfully! Redirecting to receipt...', 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 2000);
                    } else if (typeof data === 'string') {
                        // HTML response - check for errors
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(data, 'text/html');
                        const errorDiv = doc.querySelector('.bg-red-100, .alert-danger, .error-message');

                        if (errorDiv) {
                            let errorText = errorDiv.textContent.replace('Error:', '').trim();
                            // Clean up common error prefixes
                            errorText = errorText.replace(/^(Error|Warning|Notice):\s*/i, '');
                            showNotification('Error: ' + errorText, 'error');
                        } else {
                            // Check if the response contains "success" or similar indicators
                            if (data.includes('success') || data.includes('receipt') || data.includes('order-confirmation')) {
                                showNotification('Order placed successfully!', 'success');
                                // Try to extract redirect URL or default to a success page
                                setTimeout(() => {
                                    window.location.reload(); // or redirect to appropriate page
                                }, 2000);
                            } else {
                                showNotification('An unexpected error occurred. Please try again.', 'error');
                            }
                        }

                        // Reset button state for errors
                        if (!data.includes('success')) {
                            placeOrderBtn.textContent = originalText;
                            placeOrderBtn.disabled = false;
                        }
                    } else {
                        // Unexpected response format
                        showNotification('An unexpected error occurred. Please try again.', 'error');
                        placeOrderBtn.textContent = originalText;
                        placeOrderBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('A network error occurred. Please check your connection and try again.', 'error');
                    placeOrderBtn.textContent = originalText;
                    placeOrderBtn.disabled = false;
                });
        });
    }
}