<?php
// checkout-step2.php - Delivery Address Selection
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

// Check if step 1 is completed
if (!isset($_SESSION['checkout_step1']) || !$_SESSION['checkout_step1']['completed']) {
    header('Location: index-checkout-page-12.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch billing addresses (active first, then by created date)
$billing_addresses = [];
$stmt = $conn->prepare("SELECT * FROM billing_addresses WHERE user_id = ? ORDER BY is_active DESC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $billing_addresses[] = $row;
}
$stmt->close();

// Get the active address (if any)
$active_address = null;
foreach ($billing_addresses as $addr) {
    if ($addr['is_active'] == 1) {
        $active_address = $addr;
        break;
    }
}
$has_billing_addresses = count($billing_addresses) > 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billing_address_id = trim($_POST['billing_address_id'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $zipcode = trim($_POST['zipcode'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Get coordinates from selected billing address
    $latitude = null;
    $longitude = null;

    if (!empty($billing_address_id) && is_numeric($billing_address_id)) {
        $stmt = $conn->prepare("SELECT latitude, longitude FROM billing_addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $billing_address_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $billing_data = $result->fetch_assoc();
            $latitude = $billing_data['latitude'];
            $longitude = $billing_data['longitude'];
        }
        $stmt->close();
    }

    $_SESSION['checkout_step2'] = [
        'billing_address_id' => $billing_address_id,
        'mobile' => $mobile,
        'zipcode' => $zipcode,
        'address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'completed' => true
    ];

    header('Location: index-checkout-page-12-3.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
    <title>Step 2: Delivery Address - Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap">
</head>

<body class="bg-gray-100 font-sans">
    <?php include '../navbar/top.php'; ?>

    <div class="bg-white p-6 rounded shadow mt-3 max-w-6xl mx-auto">
        <h2 class="text-3xl font-bold text-orange-700 mb-8">Checkout Process</h2>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <!-- Step 1 - Completed -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-green-500 text-white rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <div class="font-medium text-green-600">Customer Info</div>
                        <div class="text-xs text-gray-500">Completed</div>
                    </div>
                </div>

                <div class="flex-1 h-px bg-green-500 mx-4"></div>

                <!-- Step 2 - Active -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">2</div>
                    <div class="ml-3">
                        <div class="font-medium text-orange-600">Delivery Address</div>
                        <div class="text-xs text-gray-500">Current step</div>
                    </div>
                </div>

                <div class="flex-1 h-px bg-gray-300 mx-4"></div>

                <!-- Step 3 -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">3</div>
                    <div class="ml-3">
                        <div class="font-medium text-gray-400">Delivery Fee</div>
                        <div class="text-xs text-gray-400">Calculate costs</div>
                    </div>
                </div>

                <div class="flex-1 h-px bg-gray-300 mx-4"></div>

                <!-- Step 4 -->
                <div class="flex items-center flex-1">
                    <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">4</div>
                    <div class="ml-3">
                        <div class="font-medium text-gray-400">Payment</div>
                        <div class="text-xs text-gray-400">Complete order</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" id="addressForm" class="space-y-6">
            <div class="bg-blue-50 p-4 rounded-lg mb-6">
                <div class="flex items-center">
                    <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <div>
                        <h3 class="text-lg font-bold text-blue-800">Step 2: Delivery Address</h3>
                        <p class="text-blue-700 text-sm">Choose where to deliver your order</p>
                    </div>
                </div>
            </div>

            <?php if ($has_billing_addresses): ?>
    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <label class="block font-medium text-lg">Delivery Address *</label>
            <button type="button" id="openAddressModal" class="bg-orange-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-700 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
                Change Address
            </button>
        </div>

        <!-- Display Active Address -->
        <?php if ($active_address): ?>
            <div class="border-2 border-green-500 rounded-lg p-4 bg-green-50">
                <input type="hidden" name="billing_address_id" id="selectedAddressId" value="<?= $active_address['id'] ?>" 
                    data-full-name="<?= htmlspecialchars($active_address['full_name']) ?>"
                    data-phone="<?= htmlspecialchars($active_address['phone']) ?>"
                    data-address="<?= htmlspecialchars($active_address['address'] . ', ' . $active_address['city'] . ', ' . $active_address['state'] . ', ' . $active_address['country']) ?>"
                    data-postal-code="<?= htmlspecialchars($active_address['postal_code']) ?>"
                    data-latitude="<?= $active_address['latitude'] ?>"
                    data-longitude="<?= $active_address['longitude'] ?>" />
                
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-green-600 text-white text-xs px-2 py-1 rounded-full">ACTIVE</span>
                            <div class="font-bold text-lg text-gray-800"><?= htmlspecialchars($active_address['full_name']) ?></div>
                        </div>
                        <div class="text-orange-600 font-medium"><?= htmlspecialchars($active_address['phone']) ?></div>
                        <div class="text-gray-600 mt-1"><?= htmlspecialchars($active_address['address'] . ', ' . $active_address['city'] . ', ' . $active_address['state'] . ', ' . $active_address['country']) ?></div>
                        <div class="text-sm text-gray-500 mt-1">ZIP: <?= htmlspecialchars($active_address['postal_code']) ?></div>
                        
                        <?php if (!empty($active_address['latitude']) && !empty($active_address['longitude'])): ?>
                            <div class="text-xs text-gray-400 mt-1">
                                📍 <?= number_format($active_address['latitude'], 6) ?>, <?= number_format($active_address['longitude'], 6) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($active_address['latitude']) && !empty($active_address['longitude'])): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">
                            ✓ Valid Location
                        </span>
                    <?php else: ?>
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-full">
                            ⚠️ Missing Location
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="border-2 border-dashed border-orange-300 rounded-lg p-4 bg-orange-50 text-center">
                <p class="text-orange-700">No active address selected. Click "Change Address" to select one.</p>
                <input type="hidden" name="billing_address_id" id="selectedAddressId" value="" />
            </div>
        <?php endif; ?>
    </div>

    <!-- Address Selection Modal -->
    <div id="addressModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-orange-600 text-white p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold">Select Delivery Address</h3>
                <button type="button" id="closeModal" class="text-white hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                <div class="mb-4 flex justify-end">
                    <a href="profile-update_billing_add-page-7-A.php" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add New Address
                    </a>
                </div>

                <div class="space-y-3">
                    <?php foreach ($billing_addresses as $addr): ?>
                        <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:bg-orange-50 hover:border-orange-300 address-option transition <?= $addr['is_active'] ? 'border-green-500 bg-green-50' : '' ?>">
                            <input type="radio" name="modal_address_radio" value="<?= $addr['id'] ?>" class="mt-2 mr-4" <?= $addr['is_active'] ? 'checked' : '' ?>
                                data-full-name="<?= htmlspecialchars($addr['full_name']) ?>"
                                data-phone="<?= htmlspecialchars($addr['phone']) ?>"
                                data-address="<?= htmlspecialchars($addr['address'] . ', ' . $addr['city'] . ', ' . $addr['state'] . ', ' . $addr['country']) ?>"
                                data-postal-code="<?= htmlspecialchars($addr['postal_code']) ?>"
                                data-latitude="<?= $addr['latitude'] ?>"
                                data-longitude="<?= $addr['longitude'] ?>" />
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <?php if ($addr['is_active']): ?>
                                            <span class="bg-green-600 text-white text-xs px-2 py-1 rounded-full">ACTIVE</span>
                                        <?php endif; ?>
                                        <div class="font-bold text-lg text-gray-800"><?= htmlspecialchars($addr['full_name']) ?></div>
                                    </div>

                                    <?php if (!empty($addr['latitude']) && !empty($addr['longitude'])): ?>
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                            ✓ Valid Location
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-full">
                                            ⚠️ Missing Location
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="text-orange-600 font-medium"><?= htmlspecialchars($addr['phone']) ?></div>
                                <div class="text-gray-600 mt-1"><?= htmlspecialchars($addr['address'] . ', ' . $addr['city'] . ', ' . $addr['state'] . ', ' . $addr['country']) ?></div>
                                <div class="text-sm text-gray-500 mt-1">ZIP: <?= htmlspecialchars($addr['postal_code']) ?></div>

                                <?php if (!empty($addr['notes'])): ?>
                                    <div class="text-sm text-gray-500 italic mt-1"><?= htmlspecialchars($addr['notes']) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($addr['latitude']) && !empty($addr['longitude'])): ?>
                                    <div class="text-xs text-gray-400 mt-1">
                                        📍 <?= number_format($addr['latitude'], 6) ?>, <?= number_format($addr['longitude'], 6) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 p-4 flex justify-end gap-3">
                <button type="button" id="cancelModal" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">
                    Cancel
                </button>
                <button type="button" id="confirmAddress" class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700">
                    Confirm Selection
                </button>
            </div>
        </div>
    </div>

                <!-- Address Details Display -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h4 class="font-bold text-gray-800 mb-4">Selected Address Details</h4>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium mb-2">Mobile Number *</label>
                            <input type="tel" name="mobile" id="mobileInput" pattern="[0-9]{11}" required
                                class="w-full border px-4 py-3 rounded-lg bg-white" readonly />
                        </div>
                        <div>
                            <label class="block font-medium mb-2">ZIP Code *</label>
                            <input type="text" name="zipcode" id="zipcodeInput" pattern="[0-9]{4}" required
                                class="w-full border px-4 py-3 rounded-lg bg-white" readonly />
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block font-medium mb-2">Full Address *</label>
                        <textarea name="address" id="addressInput" rows="3" required
                            class="w-full border px-4 py-3 rounded-lg resize-none bg-white" readonly></textarea>
                    </div>
                </div>
            <?php else: ?>
                <div class="border-2 border-dashed border-red-300 rounded-lg p-8 text-center bg-red-50">
                    <svg class="mx-auto w-16 h-16 text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <h4 class="font-bold text-red-900 text-xl mb-2">No Delivery Address Found</h4>
                    <p class="text-red-700 mb-6">You need to set up at least one delivery address to continue with your order.</p>
                    <a href="profile-update_billing_add-page-7-A.php" class="inline-flex items-center gap-2 bg-red-600 text-white px-8 py-3 rounded-lg hover:bg-red-700 transition font-medium">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Set up your address now
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($has_billing_addresses): ?>
                <div class="flex justify-between items-center pt-4">
                    <a href="index-checkout-page-12.php" class="text-gray-600 hover:text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Customer Info
                    </a>

                    <button type="submit" id="continueBtn" class="bg-orange-600 text-white px-8 py-3 rounded-lg hover:bg-orange-700 transition font-medium flex items-center" disabled>
                        Continue to Delivery Fee
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <?php include '../navbar/footer.php'; ?>
    <script src="js/index-checkout-addressZone-page-12-2.obfuscated.js?v=<?= filemtime('js/index-checkout-addressZone-page-12-2.obfuscated.js')?>"></script>

    <script>
    console.log('🚀 Initializing Step 2 with Modal...');

    const modal = document.getElementById('addressModal');
    const openModalBtn = document.getElementById('openAddressModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');
    const confirmBtn = document.getElementById('confirmAddress');
    const continueBtn = document.getElementById('continueBtn');

    // Open modal
    openModalBtn?.addEventListener('click', () => {
        modal.classList.remove('hidden');
        console.log('📍 Modal opened');
    });

    // Close modal
    function closeModal() {
        modal.classList.add('hidden');
        console.log('❌ Modal closed');
    }

    closeModalBtn?.addEventListener('click', closeModal);
    cancelModalBtn?.addEventListener('click', closeModal);

    // Close modal when clicking outside
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Confirm address selection
    confirmBtn?.addEventListener('click', function() {
        const selectedRadio = document.querySelector('input[name="modal_address_radio"]:checked');
        
        if (!selectedRadio) {
            alert('Please select an address');
            return;
        }

        const addressId = selectedRadio.value;
        const fullName = selectedRadio.dataset.fullName;
        const phone = selectedRadio.dataset.phone;
        const address = selectedRadio.dataset.address;
        const postalCode = selectedRadio.dataset.postalCode;
        const latitude = selectedRadio.dataset.latitude;
        const longitude = selectedRadio.dataset.longitude;

        console.log('✓ Address confirmed:', {addressId, fullName, latitude, longitude});

        // Validate coordinates
        if (!latitude || !longitude || latitude === 'null' || longitude === 'null') {
            alert('This address has invalid coordinates. Please edit the address or select a different one.');
            return;
        }

        // Update hidden field
        const hiddenInput = document.getElementById('selectedAddressId');
        if (hiddenInput) {
            hiddenInput.value = addressId;
            hiddenInput.dataset.fullName = fullName;
            hiddenInput.dataset.phone = phone;
            hiddenInput.dataset.address = address;
            hiddenInput.dataset.postalCode = postalCode;
            hiddenInput.dataset.latitude = latitude;
            hiddenInput.dataset.longitude = longitude;
        }

        // Clean phone number
        let cleanPhone = phone.replace(/[\s\-\(\)\+]/g, '');
        if (cleanPhone.match(/^63([0-9]{10})$/)) {
            cleanPhone = '0' + cleanPhone.substring(2);
        }

        // Update address details display
        const mobileInput = document.getElementById('mobileInput');
        const zipcodeInput = document.getElementById('zipcodeInput');
        const addressInput = document.getElementById('addressInput');

        if (mobileInput) mobileInput.value = cleanPhone;
        if (zipcodeInput) zipcodeInput.value = postalCode;
        if (addressInput) addressInput.value = address;

        // Update active address in database
        fetch('index-checkout-page-12-2-A.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'address_id=' + addressId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✓ Active address updated in database');
                // Reload page to show updated active address
                location.reload();
            } else {
                console.error('❌ Failed to update active address');
            }
        })
        .catch(error => {
            console.error('❌ Error:', error);
        });

        closeModal();
    });

    // Initial population if active address exists
    window.addEventListener('DOMContentLoaded', function() {
        const hiddenInput = document.getElementById('selectedAddressId');
        
        if (hiddenInput && hiddenInput.value) {
            const phone = hiddenInput.dataset.phone;
            const address = hiddenInput.dataset.address;
            const postalCode = hiddenInput.dataset.postalCode;

            let cleanPhone = phone.replace(/[\s\-\(\)\+]/g, '');
            if (cleanPhone.match(/^63([0-9]{10})$/)) {
                cleanPhone = '0' + cleanPhone.substring(2);
            }

            const mobileInput = document.getElementById('mobileInput');
            const zipcodeInput = document.getElementById('zipcodeInput');
            const addressInput = document.getElementById('addressInput');

            if (mobileInput) mobileInput.value = cleanPhone;
            if (zipcodeInput) zipcodeInput.value = postalCode;
            if (addressInput) addressInput.value = address;

            // Enable continue button
            if (continueBtn) {
                continueBtn.disabled = false;
                continueBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            console.log('✓ Active address loaded on page load');
        }
    });

    // Form validation
    document.getElementById('addressForm').addEventListener('submit', function(e) {
        const selectedAddressId = document.getElementById('selectedAddressId').value;

        if (!selectedAddressId) {
            e.preventDefault();
            alert('Please select a delivery address');
            return false;
        }

        const hiddenInput = document.getElementById('selectedAddressId');
        const latitude = hiddenInput.dataset.latitude;
        const longitude = hiddenInput.dataset.longitude;

        if (!latitude || !longitude || latitude === 'null' || longitude === 'null') {
            e.preventDefault();
            alert('The selected address has invalid coordinates. Please select a different address or update it.');
            return false;
        }

        console.log('✓ Form validation passed');
        return true;
    });

    console.log('✅ Step 2 with Modal initialized');
</script>

</body>

</html>