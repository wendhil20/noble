// main.js - Main initialization and global variables

// Global variables
let deliverySettings = window.checkoutConfig?.deliverySettings || null;
let subtotal = window.checkoutConfig?.totalPrice || 0;
let selectedAddress = null;

// Transportify vehicles data
window.transportifyVehicles = window.checkoutConfig?.transportifyVehicles || [];

// Global variables for the map modal
let deliveryMap = null;
let routingControl = null;
let storeMarker = null;
let customerMarker = null;
let currentRouteData = null;

// Step management variables
let currentStep = 1;
const totalSteps = 4;

// Main initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing checkout system...');
    
    // Validate essential data
    if (!deliverySettings) {
        console.warn('Warning: Delivery settings not loaded');
    }
    
    if (!window.transportifyVehicles || window.transportifyVehicles.length === 0) {
        console.error('Critical: No Transportify vehicles loaded!');
    } else {
        console.log(`Loaded ${window.transportifyVehicles.length} Transportify vehicles`);
    }
    
    // Initialize all modules
    try {
        initializeStepNavigation();
        initializeAddressSelection();
        initializeDistanceCalculation();
        initializeMapModal();
        initializeCheckoutForm();
        
        // Show first step by default
        showStep(1);
        
        // Load routing library
        loadLeafletRouting();
        
        console.log('Checkout system initialized successfully');
    } catch (error) {
        console.error('Initialization error:', error);
        showNotification('System initialization error. Please refresh the page.', 'error');
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
            console.log('Leaflet Routing Machine loaded');
        };
        script.onerror = function() {
            console.warn('Failed to load Leaflet Routing Machine');
        };
        document.head.appendChild(script);
    }
}

/**
 * Validate that all required data is loaded
 */
function validateSystemData() {
    const issues = [];
    
    if (!deliverySettings) {
        issues.push('Delivery settings missing');
    }
    
    if (!window.transportifyVehicles || window.transportifyVehicles.length === 0) {
        issues.push('No delivery vehicles available');
    }
    
    if (subtotal <= 0) {
        issues.push('Invalid cart total');
    }
    
    if (issues.length > 0) {
        console.error('System validation failed:', issues);
        return false;
    }
    
    return true;
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

/**
 * Debounce function for performance optimization
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Global error handler for debugging
 */
window.addEventListener('error', function(event) {
    console.error('Global error caught:', {
        message: event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        error: event.error
    });
    
    // Don't show notification for every error to avoid spam
    // Only log to console for debugging
});

/**
 * Handle unhandled promise rejections
 */
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', {
        reason: event.reason,
        promise: event.promise
    });
    
    // Prevent default to avoid console spam
    event.preventDefault();
    
    // Only show notification for critical errors
    if (event.reason && event.reason.message && 
        !event.reason.message.includes('Not JSON')) {
        showNotification('An unexpected error occurred. Please try again.', 'error');
    }
});

/**
 * Check if user is on a mobile device
 */
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

/**
 * Scroll to element smoothly
 */
function scrollToElement(elementId, offset = 0) {
    const element = document.getElementById(elementId);
    if (element) {
        const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
        const offsetPosition = elementPosition - offset;
        
        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        });
    }
}

/**
 * Check if element is in viewport
 */
function isInViewport(element) {
    if (!element) return false;
    
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Local storage helper with error handling
 */
const StorageHelper = {
    set: function(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.warn('LocalStorage set failed:', error);
            return false;
        }
    },
    
    get: function(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.warn('LocalStorage get failed:', error);
            return defaultValue;
        }
    },
    
    remove: function(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.warn('LocalStorage remove failed:', error);
            return false;
        }
    }
};

/**
 * Session storage helper
 */
const SessionHelper = {
    set: function(key, value) {
        try {
            sessionStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.warn('SessionStorage set failed:', error);
            return false;
        }
    },
    
    get: function(key, defaultValue = null) {
        try {
            const item = sessionStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.warn('SessionStorage get failed:', error);
            return defaultValue;
        }
    },
    
    remove: function(key) {
        try {
            sessionStorage.removeItem(key);
            return true;
        } catch (error) {
            console.warn('SessionStorage remove failed:', error);
            return false;
        }
    }
};

// Export global functions for use in other modules
window.formatCurrency = formatCurrency;
window.parseCurrency = parseCurrency;
window.debounce = debounce;
window.isMobileDevice = isMobileDevice;
window.scrollToElement = scrollToElement;
window.isInViewport = isInViewport;
window.StorageHelper = StorageHelper;
window.SessionHelper = SessionHelper;

console.log('Main.js loaded successfully');