// main.js - Main initialization and global variables
// Global variables
let deliverySettings = window.checkoutConfig?.deliverySettings || null;
let deliveryZones = window.checkoutConfig?.deliveryZones || [];
let selectedZone = null;
let subtotal = window.checkoutConfig?.totalPrice || 0;
let selectedAddress = null;

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
    // Initialize all event listeners
    initializeStepNavigation();
    initializeAddressSelection();
    initializeDistanceCalculation();
    initializeMapModal();
    initializeCheckoutForm();

    // Show first step by default
    showStep(1);
    
    // Load routing library
    loadLeafletRouting();
});

// Function to calculate VAT and totals (UPDATED - VAT only on items, not delivery)
function calculateTotalsWithVAT(itemsSubtotal, deliveryCost) {
    const vatAmount = itemsSubtotal * 0.12; // VAT only on items, not delivery
    const grandTotal = itemsSubtotal + vatAmount + deliveryCost; // Add delivery after VAT calculation

    return {
        subtotalWithDelivery: itemsSubtotal + deliveryCost,
        vatAmount: vatAmount,
        grandTotal: grandTotal
    };
}

// Load Leaflet Routing Machine when needed
function loadLeafletRouting() {
    if (typeof L !== 'undefined' && !window.leafletRoutingLoaded) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js';
        script.onload = function() {
            window.leafletRoutingLoaded = true;
        };
        document.head.appendChild(script);
    }
}