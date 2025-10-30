// main.js - Main initialization and global variables

console.log('📦 Loading main.js...');

// Global variables
let deliverySettings = window.checkoutConfig?.deliverySettings || null;
let subtotal = window.checkoutConfig?.totalPrice || 0;
let selectedAddress = null;

// Transportify vehicles data
window.transportifyVehicles = window.checkoutConfig?.transportifyVehicles || [];
window.couriersGrouped = window.checkoutConfig?.couriersGrouped || {};

// Global variables for the map modal
let deliveryMap = null;
let routingControl = null;
let storeMarker = null;
let customerMarker = null;
let currentRouteData = null;

// Step management variables
let currentStep = 1;
const totalSteps = 4;

// ✅ FIXED: Safe initialization wrapper
function safeInitialize(functionName, initFunction) {
    try {
        if (typeof initFunction === 'function') {
            initFunction();
            console.log(`✓ ${functionName} initialized`);
            return true;
        } else {
            console.log(`⚠️ ${functionName} not available (optional)`);
            return false;
        }
    } catch (error) {
        console.error(`❌ Error initializing ${functionName}:`, error);
        return false;
    }
}

// Main initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Starting checkout system initialization...');
    
    // Validate essential data
    if (!deliverySettings) {
        console.warn('⚠️ Warning: Delivery settings not loaded');
        // Don't stop initialization - might be Step 4
    }
    
    if (!window.transportifyVehicles || window.transportifyVehicles.length === 0) {
        console.warn('⚠️ Warning: No Transportify vehicles loaded');
        // Don't stop initialization - might be Step 4
    } else {
        console.log(`✓ Loaded ${window.transportifyVehicles.length} Transportify vehicles`);
    }
    
    // ✅ FIXED: Initialize all modules with error handling (all optional)
    let initCount = 0;
    let failCount = 0;
    
    // Step Navigation (optional)
    if (safeInitialize('Step Navigation', typeof initializeStepNavigation !== 'undefined' ? initializeStepNavigation : null)) {
        initCount++;
    }
    
    // Address Selection (Step 2 & 3 only)
    if (safeInitialize('Address Selection', typeof initializeAddressSelection !== 'undefined' ? initializeAddressSelection : null)) {
        initCount++;
    }
    
    // Distance Calculation (Step 3 only)
    if (safeInitialize('Distance Calculation', typeof initializeDistanceCalculation !== 'undefined' ? initializeDistanceCalculation : null)) {
        initCount++;
    }
    
    // Delivery Type Selection (Step 3 only)
    if (safeInitialize('Delivery Type Selection', typeof initializeDeliveryTypeSelection !== 'undefined' ? initializeDeliveryTypeSelection : null)) {
        initCount++;
    }
    
    // Map Modal (Step 3 only)
    if (safeInitialize('Map Modal', typeof initializeMapModal !== 'undefined' ? initializeMapModal : null)) {
        initCount++;
    }
    
    // ✅ FIXED: Checkout Form (Step 4 only) - Make it optional
    if (safeInitialize('Checkout Form', typeof initializeCheckoutForm !== 'undefined' ? initializeCheckoutForm : null)) {
        initCount++;
    }
    
    // Show first step by default (if step navigation exists)
    if (typeof showStep === 'function') {
        showStep(1);
    }
    
    // Load routing library (if Leaflet exists)
    if (typeof L !== 'undefined') {
        loadLeafletRouting();
    }
    
    console.log(`✅ Initialization complete: ${initCount} modules loaded`);
    
    if (initCount > 0) {
        showNotification('Checkout system ready!', 'success', 2000);
    }
});

/**
 * Calculate VAT and totals
 * VAT is only applied to items subtotal, not delivery fee
 */
function calculateTotalsWithVAT(itemsSubtotal, deliveryCost) {
    const vatAmount = itemsSubtotal * 0.12; // 12% VAT only on items
    const grandTotal = itemsSubtotal + vatAmount + deliveryCost;

    return {
        subtotalWithDelivery: itemsSubtotal + deliveryCost,
        vatAmount: vatAmount,
        grandTotal: grandTotal
    };
}

/**
 * Load Leaflet Routing Machine library when needed
 */
function loadLeafletRouting() {
    if (typeof L !== 'undefined' && !window.leafletRoutingLoaded) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
        script.onload = function() {
            window.leafletRoutingLoaded = true;
            console.log('✓ Leaflet Routing Machine loaded');
        };
        script.onerror = function() {
            console.warn('⚠️ Failed to load Leaflet Routing Machine');
        };
        document.head.appendChild(script);
    }
}

/**
 * Show notification helper
 */
function showNotification(message, type = 'info', duration = 5000) {
    console.log(`${type.toUpperCase()}: ${message}`);
    
    // Remove existing notifications
    const existingNotification = document.getElementById('globalNotification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.id = 'globalNotification';
    notification.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 9999;
        padding: 12px 20px; border-radius: 8px; color: white;
        font-weight: bold; max-width: 300px; word-wrap: break-word;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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

/**
 * Format currency for display
 */
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Parse currency string to number
 */
function parseCurrency(currencyString) {
    if (typeof currencyString === 'number') {
        return currencyString;
    }
    return parseFloat(String(currencyString).replace(/[₱,]/g, '')) || 0;
}

// Export global functions
window.formatCurrency = formatCurrency;
window.parseCurrency = parseCurrency;
window.showNotification = showNotification;
window.calculateTotalsWithVAT = calculateTotalsWithVAT;

console.log('✅ main.js loaded successfully');