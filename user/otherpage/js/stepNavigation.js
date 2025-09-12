// stepNavigation.js - Step management functionality

function initializeStepNavigation() {
    // Add CSS for step indicators
    if (!document.getElementById('stepStyles')) {
        const style = document.createElement('style');
        style.id = 'stepStyles';
        style.textContent = `
            .step-circle {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                transition: all 0.3s ease;
            }
            
            .step-circle:not(.active):not(.completed) {
                background-color: #e5e7eb;
                color: #6b7280;
                border: 2px solid #d1d5db;
            }
            
            .step-circle.active {
                background-color: #f97316;
                color: white;
                border: 2px solid #ea580c;
                box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.2);
            }
            
            .step-circle.completed {
                background-color: #10b981;
                color: white;
                border: 2px solid #059669;
            }
            
            .step-indicator.active .step-title {
                color: #f97316;
                font-weight: 600;
            }
            
            .step-indicator.completed .step-title {
                color: #10b981;
                font-weight: 500;
            }
        `;
        document.head.appendChild(style);
    }
}

function goToStep(stepNumber) {
    if (stepNumber < 1 || stepNumber > totalSteps) return;

    // Validate current step before moving
    if (!validateStep(currentStep) && stepNumber > currentStep) {
        return;
    }

    currentStep = stepNumber;
    showStep(stepNumber);
    updateStepIndicators();

    // Scroll to top of form
    document.querySelector('.bg-white.p-6').scrollIntoView({
        behavior: 'smooth'
    });
}

function showStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(step => {
        step.classList.add('hidden');
    });

    // Show current step
    const currentStepElement = document.getElementById(`step${stepNumber}`);
    if (currentStepElement) {
        currentStepElement.classList.remove('hidden');
    }
}

function updateStepIndicators() {
    document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
        const stepNumber = index + 1;
        const circle = indicator.querySelector('.step-circle');

        // Reset classes
        indicator.classList.remove('active', 'completed');
        circle.classList.remove('active', 'completed');

        if (stepNumber < currentStep) {
            // Completed steps
            indicator.classList.add('completed');
            circle.classList.add('completed');
            circle.innerHTML = `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
            </svg>`;
        } else if (stepNumber === currentStep) {
            // Current active step
            indicator.classList.add('active');
            circle.classList.add('active');
            circle.textContent = stepNumber;
        } else {
            // Future steps
            circle.textContent = stepNumber;
        }
    });
}

function validateStep(stepNumber) {
    switch (stepNumber) {
        case 1:
            // Customer info is readonly, always valid
            return true;

        case 2:
            // Check if address is selected
            const selectedRadio = document.querySelector('input[name="billing_address_id"]:checked');
            if (!selectedRadio) {
                showNotification('Please select a delivery address to continue.', 'error');
                return false;
            }
            return true;

        case 3:
            // FIXED: Check delivery calculation based on zone type
            if (!selectedZone) {
                showNotification('Please select a delivery zone to continue.', 'error');
                return false;
            }

            // For free delivery zones (NCR), no distance calculation needed
            if (selectedZone.zone_code === 'NCR' || selectedZone.is_free_delivery) {
                // Auto-calculate free delivery if not already done
                const deliveryFeeInput = document.getElementById('deliveryFee');
                const deliveryDistanceInput = document.getElementById('deliveryDistance');

                if (!deliveryFeeInput.value || parseFloat(deliveryFeeInput.value) !== 0) {
                    // Set free delivery values
                    deliveryFeeInput.value = '0.00';
                    deliveryDistanceInput.value = '0.00';
                    updateTotalsDisplay(0);

                    // Update delivery display for free zones
                    const distanceResultElement = document.getElementById('distanceResult');
                    if (distanceResultElement) {
                        distanceResultElement.innerHTML = `
                            <div class="bg-green-100 border border-green-300 rounded p-3">
                                <div class="font-medium text-green-800">FREE DELIVERY!</div>
                                <div class="font-medium text-green-800">Zone: ${selectedZone.zone_name}</div>
                                <div class="text-sm text-green-600 mt-1">No delivery charges for this area</div>
                            </div>
                        `;
                    }

                    // Enable continue to payment button
                    const continueToPaymentBtn = document.getElementById('continueToPayment');
                    if (continueToPaymentBtn) {
                        continueToPaymentBtn.disabled = false;
                        continueToPaymentBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                        continueToPaymentBtn.classList.add('bg-orange-600', 'hover:bg-orange-700');
                    }
                }
                return true;
            } else {
                // For paid delivery zones, check if distance is calculated
                const deliveryDistance = parseFloat(document.getElementById('deliveryDistance')?.value || '0');
                if (deliveryDistance <= 0) {
                    showNotification('Please calculate delivery distance and fee to continue.', 'error');
                    return false;
                }
                return true;
            }

        case 4:
            // Check payment method selection
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                showNotification('Please select a payment method to continue.', 'error');
                return false;
            }

            if (paymentMethod.value === 'Bank Transfer') {
                const selectedBank = document.getElementById('selectedBank').value;
                const paymentScreenshot = document.querySelector('input[name="payment_screenshot"]');

                if (!selectedBank) {
                    showNotification('Please select a bank for transfer.', 'error');
                    return false;
                }

                if (!paymentScreenshot || !paymentScreenshot.files[0]) {
                    showNotification('Please upload a payment screenshot.', 'error');
                    return false;
                }
            }
            // PayPal handles all validation on their side
            if (paymentMethod.value === 'PayPal') {
                // Just ensure delivery fee is calculated
                const deliveryFeeInput = document.getElementById('deliveryFee');
                const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) : null;

                if (deliveryFee === null || deliveryFee === undefined) {
                    showNotification('Please calculate delivery costs first.', 'error');
                    return false;
                }
            }

            return true;

        default:
            return true;
    }
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotification = document.getElementById('stepNotification');
    if (existingNotification) {
        existingNotification.remove();
    }

    const colors = {
        'error': 'bg-red-100 border-red-400 text-red-700',
        'success': 'bg-green-100 border-green-400 text-green-700',
        'info': 'bg-blue-100 border-blue-400 text-blue-700',
        'warning': 'bg-yellow-100 border-yellow-400 text-yellow-700'
    };

    const notification = document.createElement('div');
    notification.id = 'stepNotification';
    notification.className = `fixed top-4 right-4 ${colors[type]} px-6 py-4 rounded-lg border shadow-lg z-50 max-w-md`;
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="flex-1">${message}</div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}