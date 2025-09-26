// AJAX PayMongo Integration - Based on your working standalone
// Replace your current PayMongo JavaScript with this

document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    
    if (!checkoutForm) return;

    // Handle payment method switching
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            handlePaymentMethodChange(this.value);
        });
    });

    // Single form submission handler
    let isSubmitting = false;
    
    checkoutForm.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }

        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        
        if (!selectedMethod) {
            e.preventDefault();
            alert('Please select a payment method');
            return false;
        }

        if (selectedMethod.value === 'PayMongo') {
            e.preventDefault(); // Prevent form submission for PayMongo
            handlePayMongoPayment();
            return false;
        }
        
        // Let other payment methods submit normally
        isSubmitting = true;
    });

    function handlePaymentMethodChange(method) {
        // Hide all payment fields
        const bankFields = document.getElementById('bankTransferFields');
        const paypalFields = document.getElementById('paypalFields'); 
        const paymongoFields = document.getElementById('paymongoFields');
        
        if (bankFields) bankFields.classList.add('hidden');
        if (paypalFields) paypalFields.classList.add('hidden');
        if (paymongoFields) paymongoFields.classList.add('hidden');

        // Show relevant fields
        if (method === 'Bank Transfer' && bankFields) {
            bankFields.classList.remove('hidden');
            showPlaceOrderButton('Place Order');
        } else if (method === 'PayPal' && paypalFields) {
            paypalFields.classList.remove('hidden');
            hidePlaceOrderButton(); // PayPal handles its own button
        } else if (method === 'PayMongo' && paymongoFields) {
            paymongoFields.classList.remove('hidden');
            showPlaceOrderButton('Pay with PayMongo');
            updatePayMongoAmount();
            renderPayMongoInterface();
        }
    }

    function showPlaceOrderButton(text) {
        if (placeOrderBtn) {
            placeOrderBtn.style.display = 'inline-block';
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = text;
        }
    }

    function hidePlaceOrderButton() {
        if (placeOrderBtn) {
            placeOrderBtn.style.display = 'none';
        }
    }

    function updatePayMongoAmount() {
        const paymongoAmount = document.getElementById('paymongoAmount');
        const grandTotal = document.getElementById('grandTotalDisplay');
        if (paymongoAmount && grandTotal) {
            paymongoAmount.textContent = grandTotal.textContent;
        }
    }

    function renderPayMongoInterface() {
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
                </div>
            </div>
        `;
    }

    async function handlePayMongoPayment() {
        if (isSubmitting) return;
        isSubmitting = true;

        if (!placeOrderBtn) {
            isSubmitting = false;
            return;
        }

        // Update button state
        placeOrderBtn.disabled = true;
        placeOrderBtn.textContent = 'Creating PayMongo session...';

        try {
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

            console.log('Creating PayMongo session for amount:', amount);

            // Create PayMongo session using your working approach
            const response = await fetch('../paymongo-create-session.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    amount: amount,
                    // Add order details if needed
                    order_details: {
                        customer_name: document.getElementById('customer_name')?.value || '',
                        email: document.getElementById('email')?.value || '',
                        mobile: document.getElementById('mobile')?.value || ''
                    }
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('PayMongo response:', data);

            if (data.error) {
                throw new Error(data.error);
            }

            if (data.data && data.data.attributes && data.data.attributes.checkout_url) {
                console.log('Redirecting to PayMongo checkout...');
                
                // Optional: Save order data to session/database before redirect
                // You can add an API call here if needed
                
                window.location.href = data.data.attributes.checkout_url;
            } else {
                throw new Error('Invalid PayMongo response - no checkout URL');
            }

        } catch (error) {
            console.error('PayMongo error:', error);
            alert('PayMongo payment failed: ' + error.message);
            
            // Reset button
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = 'Pay with PayMongo';
            isSubmitting = false;
        }
    }

    // Initialize
    console.log('PayMongo AJAX integration loaded');
});