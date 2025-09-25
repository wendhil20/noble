// paymentManager.js - Main payment controller that coordinates all payment methods

class PaymentManager {
    constructor() {
        this.currentPaymentMethod = null;
        this.paymentHandlers = {};
        this.isInitialized = false;
    }

    // Initialize all payment methods
    initialize() {
        if (this.isInitialized) return;

        try {
            // Register payment handlers with existence checks
            if (typeof BankTransferHandler !== 'undefined') {
                this.registerHandler('Bank Transfer', new BankTransferHandler());
                console.log('BankTransferHandler registered successfully');
            } else {
                console.warn('BankTransferHandler not available');
            }
            
            if (typeof PayPalHandler !== 'undefined') {
                this.registerHandler('PayPal', new PayPalHandler());
                console.log('PayPalHandler registered successfully');
            } else {
                console.warn('PayPalHandler not available');
            }
            
            // Set up event listeners
            this.setupPaymentMethodListeners();
            this.setupFormSubmission();
            
            this.isInitialized = true;
            console.log('PaymentManager initialized successfully');
        } catch (error) {
            console.error('Error initializing PaymentManager:', error);
        }
    }

    // Register a payment handler
    registerHandler(method, handler) {
        this.paymentHandlers[method] = handler;
        console.log(`Registered payment handler for: ${method}`);
    }

    // Set up payment method selection listeners
    setupPaymentMethodListeners() {
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.handlePaymentMethodChange(e.target.value);
            });
        });
    }

    // Handle payment method selection change
    handlePaymentMethodChange(paymentMethod) {
        // Hide all payment method specific fields
        this.hideAllPaymentFields();
        
        // Set current payment method
        this.currentPaymentMethod = paymentMethod;
        
        // Get the handler for this payment method
        const handler = this.paymentHandlers[paymentMethod];
        
        if (handler) {
            try {
                // Show the payment method specific UI
                handler.show();
                
                // Update place order button state
                this.updatePlaceOrderButton();
                
                console.log(`Switched to payment method: ${paymentMethod}`);
            } catch (error) {
                console.error(`Error switching to ${paymentMethod}:`, error);
                this.showNotification(`Error loading ${paymentMethod} payment method`, 'error');
            }
        } else {
            console.warn(`No handler found for payment method: ${paymentMethod}`);
        }
    }

    // Hide all payment-specific fields
    hideAllPaymentFields() {
        const paymentFields = ['bankTransferFields', 'paypalFields'];
        
        paymentFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.add('hidden');
            }
        });
    }

    // Update place order button visibility and state
    updatePlaceOrderButton() {
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        const handler = this.paymentHandlers[this.currentPaymentMethod];
        
        if (placeOrderBtn && handler) {
            // Show the button
            placeOrderBtn.style.display = 'inline-block';
            
            // Let the handler determine if it should be enabled
            const shouldEnable = handler.validatePaymentMethod();
            placeOrderBtn.disabled = !shouldEnable;
            
            // Update button styling
            if (shouldEnable) {
                placeOrderBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            } else {
                placeOrderBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                placeOrderBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            }
        }
    }

    // Set up form submission handling
    setupFormSubmission() {
        const checkoutForm = document.getElementById('checkoutForm');
        const placeOrderBtn = document.getElementById('placeOrderBtn');

        if (checkoutForm && placeOrderBtn) {
            checkoutForm.addEventListener('submit', (e) => {
                this.handleFormSubmission(e);
            });
        }
    }

    // Handle form submission
    handleFormSubmission(event) {
        event.preventDefault();

        const handler = this.paymentHandlers[this.currentPaymentMethod];
        
        if (!handler) {
            this.showNotification('Please select a payment method', 'error');
            return;
        }

        // Validate the payment method
        if (!handler.validatePaymentMethod()) {
            this.showNotification('Please complete all payment requirements', 'error');
            return;
        }

        // Let the handler process the submission
        try {
            handler.processPayment(event);
        } catch (error) {
            console.error('Payment processing error:', error);
            this.showNotification('Payment processing failed. Please try again.', 'error');
        }
    }

    // Utility method to show notifications
    showNotification(message, type = 'info') {
        // This should match your existing notification system
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
            alert(message); // Fallback
        }
    }

    // Get current payment method
    getCurrentPaymentMethod() {
        return this.currentPaymentMethod;
    }

    // Check if a specific payment method is available
    isPaymentMethodAvailable(method) {
        return this.paymentHandlers.hasOwnProperty(method);
    }
}

// Base class for payment handlers
class PaymentHandler {
    constructor(methodName) {
        this.methodName = methodName;
    }

    // Show payment method specific UI (to be implemented by subclasses)
    show() {
        throw new Error('show() method must be implemented by subclass');
    }

    // Hide payment method specific UI (to be implemented by subclasses)
    hide() {
        throw new Error('hide() method must be implemented by subclass');
    }

    // Validate payment method requirements (to be implemented by subclasses)
    validatePaymentMethod() {
        throw new Error('validatePaymentMethod() method must be implemented by subclass');
    }

    // Process payment (to be implemented by subclasses)
    processPayment(event) {
        throw new Error('processPayment() method must be implemented by subclass');
    }

    // Utility methods available to all payment handlers
    updateTotalAmount() {
        const grandTotalElement = document.getElementById('grandTotalDisplay');
        return grandTotalElement ? grandTotalElement.textContent : '₱0.00';
    }

    showNotification(message, type = 'info') {
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }
}

// Initialize payment manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the global payment manager
    window.paymentManager = new PaymentManager();
    window.paymentManager.initialize();
});