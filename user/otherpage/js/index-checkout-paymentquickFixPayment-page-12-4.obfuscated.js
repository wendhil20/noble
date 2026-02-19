// index-checkout-paymentquickFixPayment-page-12-4.js - CLEANED (PayMongo & QRPh ONLY)
console.log('Loading Payment System with QRPh Support...');

// ============================================================
// NOTIFICATION SYSTEM
// ============================================================
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

// ============================================================
// QR PH GLOBALS
// ============================================================
let qrphPollInterval = null;
let qrphCountdownInterval = null;
let currentQRCodeId = null;
let currentOrderId = null;
let qrphPollCount = 0;
let qrphMaxPolls = 120; // 5 minutes with 2.5 second interval = 120 polls

// ============================================================
// QR PH FUNCTIONS
// ============================================================
async function generateQRPh() {
    const loadingEl = document.getElementById('qrphLoading');
    const contentEl = document.getElementById('qrphContent');
    const errorEl = document.getElementById('qrphError');
    const successEl = document.getElementById('qrphSuccess');

    if (loadingEl) loadingEl.classList.remove('hidden');
    if (contentEl) contentEl.classList.add('hidden');
    if (errorEl) errorEl.classList.add('hidden');
    if (successEl) successEl.classList.add('hidden');

    // Stop existing intervals
    if (qrphPollInterval) clearInterval(qrphPollInterval);
    if (qrphCountdownInterval) clearInterval(qrphCountdownInterval);
    qrphPollCount = 0;

    try {
        console.log('🚀 Step 1: Creating order...');
        
        let deliveryFee = 0;
        if (window.deliveryFee !== undefined) {
            deliveryFee = parseFloat(window.deliveryFee);
        }

        console.log('Sending order creation request...');
        console.log('Amount:', window.grandTotal);
        console.log('Delivery Fee:', deliveryFee);

        const orderRes = await fetch('checkout-qrph-create-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                amount: window.grandTotal,
                delivery_fee: deliveryFee
            })
        });

        console.log('Order Response Status:', orderRes.status);
        const orderData = await orderRes.json();
        console.log('Order Response:', orderData);

        if (orderData.error) {
            throw new Error('Order creation failed: ' + orderData.error);
        }
        if (!orderData.order_id) {
            throw new Error('No order ID in response');
        }

        currentOrderId = orderData.order_id;
        const reference_no = orderData.reference_no;
        console.log('✓ Order created: ID=' + currentOrderId + ', Ref=' + reference_no);

        // ============================================================
        // STEP 2: GENERATE QR CODE
        // ============================================================
        console.log('🚀 Step 2: Generating QR code...');
        
        const qrRes = await fetch('checkout-qrph-create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                amount: window.grandTotal,
                order_id: currentOrderId
            })
        });

        console.log('QR Response Status:', qrRes.status);
        const qrText = await qrRes.text();
        console.log('QR Response (raw):', qrText.substring(0, 300));

        let qrData;
        try {
            qrData = JSON.parse(qrText);
        } catch (e) {
            console.error('Failed to parse QR response:', e);
            throw new Error('Invalid QR response: ' + qrText.substring(0, 100));
        }

        console.log('QR Data:', qrData);

        if (qrData.error) {
            throw new Error('QR generation failed: ' + qrData.error);
        }

        if (!qrData.data) {
            throw new Error('No QR data in response');
        }

        currentQRCodeId = qrData.data.id;
        const qrImageUrl = qrData.data.attributes.qr_image;

        if (!qrImageUrl) {
            throw new Error('No QR image URL in response');
        }

        console.log('✓ QR generated: ' + currentQRCodeId);
        console.log('✓ QR Image URL: ' + qrImageUrl);

        // ============================================================
        // STEP 3: DISPLAY QR CODE
        // ============================================================
        console.log('🚀 Step 3: Displaying QR code...');
        
        const qrImg = document.getElementById('qrphImage');
        if (qrImg) {
            qrImg.src = qrImageUrl;
            console.log('✓ QR image set');
        }

        const amountDisplay = document.getElementById('qrphAmountDisplay');
        if (amountDisplay) {
            amountDisplay.textContent = '₱' + parseFloat(window.grandTotal).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
        }

        if (loadingEl) loadingEl.classList.add('hidden');
        if (contentEl) contentEl.classList.remove('hidden');

        showNotification('QR code ready. Please scan with your bank app.', 'success', 3000);

        // Start countdown (1 hour for QRPh)
        console.log('Starting countdown...');
        startCountdown(3600); // 1 hour = 3600 seconds

        // Start polling
        console.log('Starting polling...');
        startPolling(currentQRCodeId, currentOrderId);

    } catch (err) {
        console.error('❌ QR Ph generation error:', err);
        console.error('Stack:', err.stack);
        showNotification('QR generation failed: ' + err.message, 'error', 5000);
        
        if (loadingEl) loadingEl.classList.add('hidden');
        if (errorEl) errorEl.classList.remove('hidden');
    }
}

function startCountdown(seconds) {
    if (qrphCountdownInterval) clearInterval(qrphCountdownInterval);
    let remaining = seconds;

    const updateTimer = () => {
        const hrs = Math.floor(remaining / 3600);
        const mins = Math.floor((remaining % 3600) / 60);
        const secs = remaining % 60;
        const timerEl = document.getElementById('qrphTimer');
        if (timerEl) {
            if (hrs > 0) {
                timerEl.textContent = `${hrs}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            } else {
                timerEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
            }
        }
    };

    updateTimer();

    qrphCountdownInterval = setInterval(() => {
        remaining--;
        updateTimer();

        if (remaining <= 0) {
            clearInterval(qrphCountdownInterval);
            clearInterval(qrphPollInterval);
            console.log('⏰ QR expired, regenerating...');
            showNotification('QR code expired. Generating new one...', 'warning', 3000);
            generateQRPh();
        }
    }, 1000);
}

function startPolling(qrId, orderId) {
    if (qrphPollInterval) clearInterval(qrphPollInterval);

    console.log('🔄 Starting polling for QR payment...');
    console.log('QR ID:', qrId);
    console.log('Order ID:', orderId);

    // Poll every 2.5 seconds
    qrphPollInterval = setInterval(async () => {
        qrphPollCount++;
        
        if (qrphPollCount % 10 === 0) {
            console.log(`Poll attempt ${qrphPollCount}/${qrphMaxPolls} for order ${orderId}`);
        }

        try {
            const checkUrl = `checkout-qrph-check-status.php?qr_id=${encodeURIComponent(qrId)}&order_id=${orderId}`;
            
            const res = await fetch(checkUrl, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) {
                console.warn(`Poll error: HTTP ${res.status}`);
                return;
            }

            const data = await res.json();
            
            if (qrphPollCount % 10 === 0) {
                console.log(`Poll response:`, data);
            }

            // ✅ PAYMENT CONFIRMED!
            if (data.paid === true) {
                console.log('✅ PAYMENT CONFIRMED! Order: ' + orderId);
                clearInterval(qrphPollInterval);
                clearInterval(qrphCountdownInterval);

                const contentEl = document.getElementById('qrphContent');
                const successEl = document.getElementById('qrphSuccess');
                
                if (contentEl) contentEl.classList.add('hidden');
                if (successEl) successEl.classList.remove('hidden');

                showNotification('✅ Payment received! Loading receipt...', 'success', 2000);

                // Wait a moment, then redirect
                setTimeout(() => {
                    console.log('Redirecting to receipt: order_id=' + orderId);
                    window.location.href = `checkout-order_receipt-page-12-A.php?order_id=${orderId}`;
                }, 1500);

                return; // Stop polling
            }

        } catch (e) {
            if (qrphPollCount % 10 === 0) {
                console.error('❌ Poll error:', e);
            }
        }

        // Stop if exceeded max polls
        if (qrphPollCount >= qrphMaxPolls) {
            console.log('Max polls reached, stopping');
            clearInterval(qrphPollInterval);
            showNotification('Payment timeout. Please try again.', 'warning', 3000);
        }
    }, 2500); // Poll every 2.5 seconds
}

// ============================================================
// PAYMENT SYSTEM CLASS (CLEANED - PAYMONGO & QRPH ONLY)
// ============================================================
class PaymentSystem {
    constructor() {
        this.initialized = false;
        this.isSubmitting = false;
    }

    initialize() {
        if (this.initialized) return;

        console.log('Initializing Payment System...');

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

        // Hide ALL payment field sections
        const paymongoFields = document.getElementById('paymongoFields');
        const qrphFields = document.getElementById('qrphFields');

        if (paymongoFields) paymongoFields.classList.add('hidden');
        if (qrphFields) qrphFields.classList.add('hidden');

        // Stop QRPh polling if switching away
        if (method !== 'QRPh') {
            if (qrphPollInterval) clearInterval(qrphPollInterval);
            if (qrphCountdownInterval) clearInterval(qrphCountdownInterval);
            console.log('Stopped QRPh polling');
        }

        const placeOrderBtn = document.getElementById('placeOrderBtn');

        if (method === 'PayMongo') {
            if (paymongoFields) {
                paymongoFields.classList.remove('hidden');
                this.renderPayMongoInterface();
            }
            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                placeOrderBtn.textContent = 'Pay with PayMongo';
            }
            this.updatePayMongoAmount();

        } else if (method === 'QRPh') {
            if (qrphFields) qrphFields.classList.remove('hidden');

            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                placeOrderBtn.textContent = 'Generate QR Code';
            }
        }

        console.log(`✓ Switched to ${method}`);
    }

    updatePayMongoAmount() {
        const paymongoAmount = document.getElementById('paymongoAmount');
        if (paymongoAmount && window.grandTotal) {
            const formatted = '₱' + parseFloat(window.grandTotal).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            paymongoAmount.textContent = formatted;
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

    setupFormSubmission() {
        const paymentForm = document.getElementById('paymentForm') || document.getElementById('checkoutForm');
        if (!paymentForm) {
            console.warn('⚠️ Payment form not found');
            return;
        }

        console.log('✓ Form found:', paymentForm.id);

        paymentForm.addEventListener('submit', (e) => {
            if (this.isSubmitting) {
                e.preventDefault();
                console.log('⚠️ Already submitting, ignoring...');
                return false;
            }

            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');

            if (!selectedMethod) {
                e.preventDefault();
                showNotification('Please select a payment method', 'error');
                return false;
            }

            console.log('📝 Form submitted with method:', selectedMethod.value);

            // ✅ HANDLE QRPh - Start generation instead of normal form submit
            if (selectedMethod.value === 'QRPh') {
                e.preventDefault();
                console.log('QRPh selected - Starting QR generation...');
                generateQRPh();
                return false;
            }

            // Handle PayMongo via AJAX
            if (selectedMethod.value === 'PayMongo') {
                e.preventDefault();
                this.handlePayMongoPayment();
                return false;
            }

            this.isSubmitting = true;
            showNotification(`Processing ${selectedMethod.value}...`, 'info', 2000);
            console.log('✅ Form submission allowed for:', selectedMethod.value);
        });
    }

    async handlePayMongoPayment() {
        if (this.isSubmitting) return;
        this.isSubmitting = true;

        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) {
            placeOrderBtn.disabled = true;
            placeOrderBtn.textContent = 'Creating PayMongo session...';
        }

        try {
            const grandTotalElement = document.getElementById('grandTotalDisplay');
            if (!grandTotalElement) throw new Error('Cannot find total amount element');

            const totalText = grandTotalElement.value || grandTotalElement.textContent;
            const cleanedAmount = totalText.replace(/[₱,\s]/g, '').trim();
            const amount = parseFloat(cleanedAmount);

            console.log('Debug - Parsed Amount:', amount);

            if (isNaN(amount) || amount <= 0) {
                throw new Error('Invalid amount: ' + cleanedAmount);
            }

            let deliveryFee = 0;
            if (window.deliveryFee !== undefined) {
                deliveryFee = parseFloat(window.deliveryFee);
            }

            const vehicleData = {
                assigned_vehicle_id: parseInt(document.getElementById('assignedVehicleId')?.value || '0'),
                assigned_vehicle_type: document.getElementById('assignedVehicleType')?.value || '',
                total_cubic_meters: parseFloat(document.getElementById('totalCubicMeters')?.value || '0'),
                total_weight_kg: parseFloat(document.getElementById('totalWeightKg')?.value || '0'),
                total_width: parseFloat(document.getElementById('totalWidth')?.value || '0'),
                total_height: parseFloat(document.getElementById('totalHeight')?.value || '0'),
                total_length: parseFloat(document.getElementById('totalLength')?.value || '0')
            };

            const referralCode = document.getElementById('referralCodeHidden')?.value || '';

            const requestData = {
                amount: amount,
                delivery_fee: deliveryFee,
                order_details: {
                    ...vehicleData,
                    referral_code: referralCode
                }
            };

            console.log('✓ Sending PayMongo request:', requestData);

            const response = await fetch('checkout-paymongo-create-sessions-page-12-A.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            });

            const text = await response.text();
            console.log('PayMongo raw response:', text);

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Server returned invalid response: ' + text.substring(0, 100));
            }

            if (data.error) throw new Error(data.error);

            if (data.data && data.data.attributes && data.data.attributes.checkout_url) {
                console.log('✓ Redirecting to PayMongo:', data.data.attributes.checkout_url);
                showNotification('Redirecting to payment gateway...', 'success', 2000);
                window.location.href = data.data.attributes.checkout_url;
            } else {
                throw new Error('No checkout URL in response');
            }

        } catch (error) {
            console.error('❌ PayMongo error:', error);
            showNotification('Payment error: ' + error.message, 'error', 5000);

            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.textContent = 'Pay with PayMongo';
            }
            this.isSubmitting = false;
        }
    }
}

// ============================================================
// INITIALIZE
// ============================================================
function initializePaymentSystem() {
    if (!window.paymentSystem) {
        window.paymentSystem = new PaymentSystem();
        window.paymentSystem.initialize();
        console.log('✓ Payment system initialized');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePaymentSystem);
} else {
    initializePaymentSystem();
}

setTimeout(initializePaymentSystem, 500);