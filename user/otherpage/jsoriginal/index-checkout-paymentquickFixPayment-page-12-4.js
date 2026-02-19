// index-checkout-paymentquickFixPayment-page-12-4.js - UPDATED (QRPh now uses Checkout Session)
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
    const colors = { success: '#10B981', error: '#EF4444', warning: '#F59E0B', info: '#3B82F6' };
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.textContent = message;
    document.body.appendChild(notification);
    if (duration > 0) {
        setTimeout(() => { if (notification.parentNode) notification.parentNode.removeChild(notification); }, duration);
    }
}
window.showNotification = showNotification;

// ============================================================
// QR PH GLOBALS
// ============================================================
let qrphPollInterval    = null;
let qrphCountdownInterval = null;
let currentQRCodeId     = null;
let currentOrderId      = null;
let qrphPollCount       = 0;
let qrphMaxPolls        = 120;

// ============================================================
// QR PH - NOW USES CHECKOUT SESSION (fixed amount, no typing!)
// ============================================================
async function generateQRPh() {
    const loadingEl = document.getElementById('qrphLoading');
    const contentEl = document.getElementById('qrphContent');
    const errorEl   = document.getElementById('qrphError');
    const successEl = document.getElementById('qrphSuccess');

    if (loadingEl) loadingEl.classList.remove('hidden');
    if (contentEl) contentEl.classList.add('hidden');
    if (errorEl)   errorEl.classList.add('hidden');
    if (successEl) successEl.classList.add('hidden');

    if (qrphPollInterval)      clearInterval(qrphPollInterval);
    if (qrphCountdownInterval) clearInterval(qrphCountdownInterval);
    qrphPollCount = 0;

    try {
        // STEP 1: Create order
        console.log('🚀 Step 1: Creating order...');
        const deliveryFee = parseFloat(window.deliveryFee) || 0;

        const orderRes  = await fetch('checkout-qrph-create-order.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ amount: window.grandTotal, delivery_fee: deliveryFee })
        });

        const orderData = await orderRes.json();
        console.log('Order Response:', orderData);

        if (orderData.error || !orderData.order_id) {
            throw new Error(orderData.error || 'Order creation failed');
        }

        currentOrderId = orderData.order_id;
        console.log('✓ Order created: ID=' + currentOrderId);

        // STEP 2: Create Checkout Session (QRPh only - FIXED AMOUNT)
        console.log('🚀 Step 2: Creating QRPh Checkout Session...');

        const qrRes  = await fetch('checkout-qrph-create.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ amount: window.grandTotal, order_id: currentOrderId })
        });

        const qrData = await qrRes.json();
        console.log('QRPh Session:', qrData);

        if (qrData.error || !qrData.checkout_url) {
            throw new Error(qrData.error || 'Failed to create QRPh session');
        }

        // STEP 3: Redirect to PayMongo QRPh checkout page
        // Fixed amount na — hindi na mag-type ang user!
        console.log('✓ Redirecting to PayMongo QRPh checkout...');
        showNotification('Redirecting to payment page...', 'info', 2000);

        if (loadingEl) loadingEl.classList.add('hidden');

        setTimeout(() => {
            window.location.href = qrData.checkout_url;
        }, 1000);

    } catch (err) {
        console.error('❌ QRPh error:', err);
        showNotification('Error: ' + err.message, 'error', 5000);
        if (loadingEl) loadingEl.classList.add('hidden');
        if (errorEl)   errorEl.classList.remove('hidden');
    }
}

// Kept for compatibility but no longer used in new flow
function startCountdown(seconds) {}
function startPolling(qrId, orderId) {}

// ============================================================
// PAYMENT SYSTEM CLASS
// ============================================================
class PaymentSystem {
    constructor() {
        this.initialized  = false;
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
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', (e) => this.switchPaymentMethod(e.target.value));
        });
    }

    switchPaymentMethod(method) {
        console.log('Switching to:', method);

        const paymongoFields = document.getElementById('paymongoFields');
        const qrphFields     = document.getElementById('qrphFields');

        if (paymongoFields) paymongoFields.classList.add('hidden');
        if (qrphFields)     qrphFields.classList.add('hidden');

        if (method !== 'QRPh') {
            if (qrphPollInterval)      clearInterval(qrphPollInterval);
            if (qrphCountdownInterval) clearInterval(qrphCountdownInterval);
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
                placeOrderBtn.textContent = 'Pay with QR Ph';
            }
        }
    }

    updatePayMongoAmount() {
        const el = document.getElementById('paymongoAmount');
        if (el && window.grandTotal) {
            el.textContent = '₱' + parseFloat(window.grandTotal).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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
                    <div class="text-xs text-green-600 mt-2">Available: GCash, Maya, Credit/Debit Cards, GrabPay</div>
                </div>
            </div>`;
    }

    setupFormSubmission() {
        const paymentForm = document.getElementById('paymentForm') || document.getElementById('checkoutForm');
        if (!paymentForm) { console.warn('⚠️ Payment form not found'); return; }

        paymentForm.addEventListener('submit', (e) => {
            if (this.isSubmitting) { e.preventDefault(); return false; }

            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                e.preventDefault();
                showNotification('Please select a payment method', 'error');
                return false;
            }

            console.log('Form submitted with method:', selectedMethod.value);

            if (selectedMethod.value === 'QRPh') {
                e.preventDefault();
                generateQRPh();
                return false;
            }

            if (selectedMethod.value === 'PayMongo') {
                e.preventDefault();
                this.handlePayMongoPayment();
                return false;
            }

            this.isSubmitting = true;
        });
    }

    async handlePayMongoPayment() {
        if (this.isSubmitting) return;
        this.isSubmitting = true;

        const placeOrderBtn = document.getElementById('placeOrderBtn');
        if (placeOrderBtn) { placeOrderBtn.disabled = true; placeOrderBtn.textContent = 'Creating session...'; }

        try {
            const grandTotalElement = document.getElementById('grandTotalDisplay');
            if (!grandTotalElement) throw new Error('Cannot find total amount element');

            const amount = parseFloat((grandTotalElement.value || grandTotalElement.textContent).replace(/[₱,\s]/g, '').trim());
            if (isNaN(amount) || amount <= 0) throw new Error('Invalid amount');

            const deliveryFee = parseFloat(window.deliveryFee) || 0;

            const requestData = {
                amount:        amount,
                delivery_fee:  deliveryFee,
                order_details: {
                    assigned_vehicle_id:   parseInt(document.getElementById('assignedVehicleId')?.value || '0'),
                    assigned_vehicle_type: document.getElementById('assignedVehicleType')?.value || '',
                    total_cubic_meters:    parseFloat(document.getElementById('totalCubicMeters')?.value || '0'),
                    total_weight_kg:       parseFloat(document.getElementById('totalWeightKg')?.value || '0'),
                    total_width:           parseFloat(document.getElementById('totalWidth')?.value || '0'),
                    total_height:          parseFloat(document.getElementById('totalHeight')?.value || '0'),
                    total_length:          parseFloat(document.getElementById('totalLength')?.value || '0'),
                    referral_code:         document.getElementById('referralCodeHidden')?.value || ''
                }
            };

            const response = await fetch('checkout-paymongo-create-sessions-page-12-A.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(requestData)
            });

            const data = await response.json();
            if (data.error) throw new Error(data.error);

            if (data.data?.attributes?.checkout_url) {
                showNotification('Redirecting to payment gateway...', 'success', 2000);
                window.location.href = data.data.attributes.checkout_url;
            } else {
                throw new Error('No checkout URL in response');
            }

        } catch (error) {
            console.error('❌ PayMongo error:', error);
            showNotification('Payment error: ' + error.message, 'error', 5000);
            if (placeOrderBtn) { placeOrderBtn.disabled = false; placeOrderBtn.textContent = 'Pay with PayMongo'; }
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