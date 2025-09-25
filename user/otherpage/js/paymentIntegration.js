// paymentIntegration.js - Master integration file for all payment modules

// This file coordinates the loading and integration of all payment-related modules
// It ensures proper initialization order and handles dependencies

class PaymentSystemIntegrator {
    constructor() {
        this.modulesLoaded = new Set();
        this.requiredModules = ['NotificationManager', 'PaymentHandler', 'BankTransferHandler', 'PayPalHandler', 'PaymentManager'];
        this.isReady = false;
        this.readyCallbacks = [];
    }

    // Initialize the payment system integration
    init() {
        console.log('Payment system integration starting...');
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.startInitialization());
        } else {
            this.startInitialization();
        }
    }

    // Start the initialization process
    startInitialization() {
        try {
            this.checkDependencies();
            this.initializeModules();
            this.setupGlobalEventListeners();
            this.finalizeInitialization();
        } catch (error) {
            console.error('Payment system initialization failed:', error);
            this.showFallbackError();
        }
    }

    // Check if all required dependencies are available
    checkDependencies() {
        const missing = [];
        const warnings = [];

        // Check for required classes/objects with fallback handling
        if (typeof NotificationManager === 'undefined') {
            warnings.push('NotificationManager');
        } else {
            this.modulesLoaded.add('NotificationManager');
        }

        if (typeof PaymentHandler === 'undefined') {
            warnings.push('PaymentHandler (base class)');
        }

        if (typeof BankTransferHandler === 'undefined') {
            warnings.push('BankTransferHandler');
        }

        if (typeof PayPalHandler === 'undefined') {
            warnings.push('PayPalHandler');
        }

        if (typeof PaymentManager === 'undefined') {
            missing.push('PaymentManager');
        } else {
            this.modulesLoaded.add('PaymentManager');
        }

        // Only throw error for critical missing components
        if (missing.length > 0) {
            throw new Error(`Critical payment modules missing: ${missing.join(', ')}`);
        }

        // Log warnings for optional components
        if (warnings.length > 0) {
            console.warn(`Optional payment modules not loaded: ${warnings.join(', ')}`);
            console.warn('Some payment methods may not be available');
        }

        // Check for required DOM elements
        const requiredElements = ['checkoutForm'];
        const missingElements = [];

        requiredElements.forEach(id => {
            if (!document.getElementById(id)) {
                missingElements.push(id);
            }
        });

        if (missingElements.length > 0) {
            console.warn(`Missing DOM elements: ${missingElements.join(', ')}`);
            // Don't throw error for missing elements, just warn
        }
    }

    // Initialize all payment modules in the correct order
    initializeModules() {
        console.log('Initializing payment modules...');

        // 1. Initialize notification system first (others depend on it)
        if (!window.notificationManager) {
            window.notificationManager = new NotificationManager();
        }

        // 2. Initialize payment manager (coordinates everything)
        if (!window.paymentManager) {
            window.paymentManager = new PaymentManager();
        }

        // Mark modules as loaded
        this.modulesLoaded.add('NotificationManager');
        this.modulesLoaded.add('PaymentManager');

        console.log('Payment modules initialized successfully');
    }

    // Set up global event listeners for payment system
    setupGlobalEventListeners() {
        // Listen for delivery fee updates to refresh payment amounts
        document.addEventListener('deliveryFeeUpdated', (event) => {
            this.handleDeliveryFeeUpdate(event.detail);
        });

        // Listen for order total changes
        document.addEventListener('orderTotalUpdated', (event) => {
            this.handleOrderTotalUpdate(event.detail);
        });

        // Listen for payment method changes
        document.addEventListener('paymentMethodChanged', (event) => {
            this.handlePaymentMethodChange(event.detail);
        });

        // Listen for form validation events
        document.addEventListener('formValidationRequired', (event) => {
            this.handleFormValidation(event.detail);
        });

        console.log('Global payment event listeners set up');
    }

    // Finalize initialization
    finalizeInitialization() {
        this.isReady = true;
        
        // Execute any queued callbacks
        this.readyCallbacks.forEach(callback => {
            try {
                callback();
            } catch (error) {
                console.error('Error in ready callback:', error);
            }
        });
        
        this.readyCallbacks = [];
        
        // Dispatch ready event
        document.dispatchEvent(new CustomEvent('paymentSystemReady', {
            detail: { integrator: this }
        }));

        console.log('Payment system integration completed successfully');
        
        // Show success notification if possible
        if (window.showNotification) {
            window.showNotification('Payment system loaded successfully', 'success', 2000);
        }
    }

    // Handle delivery fee updates
    handleDeliveryFeeUpdate(details) {
        console.log('Delivery fee updated:', details);

        if (window.paymentManager && window.paymentManager.isInitialized) {
            const currentMethod = window.paymentManager.getCurrentPaymentMethod();
            const handler = window.paymentManager.paymentHandlers[currentMethod];
            
            if (handler && typeof handler.onDeliveryFeeUpdate === 'function') {
                handler.onDeliveryFeeUpdate(details);
            }
        }
    }

    // Handle order total updates
    handleOrderTotalUpdate(details) {
        console.log('Order total updated:', details);

        if (window.paymentManager && window.paymentManager.isInitialized) {
            const currentMethod = window.paymentManager.getCurrentPaymentMethod();
            const handler = window.paymentManager.paymentHandlers[currentMethod];
            
            if (handler && typeof handler.onOrderTotalUpdate === 'function') {
                handler.onOrderTotalUpdate(details);
            }
        }
    }

    // Handle payment method changes
    handlePaymentMethodChange(details) {
        console.log('Payment method changed:', details);

        if (window.paymentManager && window.paymentManager.isInitialized) {
            // Payment manager handles this automatically through its event listeners
            // This is just for additional coordination if needed
        }
    }

    // Handle form validation requests
    handleFormValidation(details) {
        console.log('Form validation requested:', details);

        if (window.paymentManager && window.paymentManager.isInitialized) {
            const currentMethod = window.paymentManager.getCurrentPaymentMethod();
            const handler = window.paymentManager.paymentHandlers[currentMethod];
            
            if (handler && typeof handler.validatePaymentMethod === 'function') {
                return handler.validatePaymentMethod();
            }
        }

        return false;
    }

    // Show fallback error when initialization fails
    showFallbackError() {
        const errorHtml = `
            <div id="payment-error-fallback" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-md mx-4 text-center">
                    <svg class="mx-auto w-16 h-16 text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <h3 class="text-lg font-bold text-red-800 mb-2">Payment System Error</h3>
                    <p class="text-red-700 mb-4">Unable to initialize payment system. Please refresh the page to try again.</p>
                    <button onclick="window.location.reload()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        Refresh Page
                    </button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', errorHtml);
    }

    // Register a callback to be executed when system is ready
    onReady(callback) {
        if (this.isReady) {
            callback();
        } else {
            this.readyCallbacks.push(callback);
        }
    }

    // Get system status
    getStatus() {
        return {
            isReady: this.isReady,
            modulesLoaded: Array.from(this.modulesLoaded),
            hasNotificationManager: !!window.notificationManager,
            hasPaymentManager: !!window.paymentManager
        };
    }

    // Utility method to trigger delivery fee update event
    triggerDeliveryFeeUpdate(fee, distance, zone) {
        document.dispatchEvent(new CustomEvent('deliveryFeeUpdated', {
            detail: { fee, distance, zone }
        }));
    }

    // Utility method to trigger order total update event
    triggerOrderTotalUpdate(subtotal, deliveryFee, vat, grandTotal) {
        document.dispatchEvent(new CustomEvent('orderTotalUpdated', {
            detail: { subtotal, deliveryFee, vat, grandTotal }
        }));
    }

    // Utility method to check if a specific payment method is available
    isPaymentMethodAvailable(method) {
        if (!window.paymentManager || !window.paymentManager.isInitialized) {
            return false;
        }
        
        return window.paymentManager.isPaymentMethodAvailable(method);
    }

    // Emergency cleanup method
    cleanup() {
        console.log('Cleaning up payment system...');
        
        // Remove event listeners
        document.removeEventListener('deliveryFeeUpdated', this.handleDeliveryFeeUpdate);
        document.removeEventListener('orderTotalUpdated', this.handleOrderTotalUpdate);
        document.removeEventListener('paymentMethodChanged', this.handlePaymentMethodChange);
        document.removeEventListener('formValidationRequired', this.handleFormValidation);
        
        // Clear callbacks
        this.readyCallbacks = [];
        
        // Reset state
        this.isReady = false;
        this.modulesLoaded.clear();
        
        console.log('Payment system cleanup completed');
    }
}

// Global utility functions for easy integration with existing code

// Function to safely update payment amounts when delivery changes
function updatePaymentAmounts() {
    if (window.paymentSystemIntegrator) {
        const grandTotalElement = document.getElementById('grandTotalDisplay');
        const deliveryFeeElement = document.getElementById('deliveryFee');
        const vatElement = document.getElementById('vatAmount');
        
        if (grandTotalElement && deliveryFeeElement) {
            const deliveryFee = parseFloat(deliveryFeeElement.value) || 0;
            const grandTotal = grandTotalElement.textContent.replace(/[₱,]/g, '');
            
            window.paymentSystemIntegrator.triggerOrderTotalUpdate(
                null, // subtotal calculated elsewhere
                deliveryFee,
                null, // vat calculated elsewhere  
                parseFloat(grandTotal) || 0
            );
        }
    }
}

// Function to validate current payment method
function validateCurrentPaymentMethod() {
    if (window.paymentSystemIntegrator && window.paymentSystemIntegrator.isReady) {
        return window.paymentSystemIntegrator.handleFormValidation({});
    }
    return false;
}

// Function to check if payment system is ready
function isPaymentSystemReady() {
    return window.paymentSystemIntegrator && window.paymentSystemIntegrator.isReady;
}

// Initialize the payment system integrator
window.paymentSystemIntegrator = new PaymentSystemIntegrator();
window.paymentSystemIntegrator.init();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        PaymentSystemIntegrator,
        updatePaymentAmounts,
        validateCurrentPaymentMethod,
        isPaymentSystemReady
    };
}