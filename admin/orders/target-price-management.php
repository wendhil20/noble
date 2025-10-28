<?php
session_name("nobleadmin");
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// Get all products with guide status
$products_query = "SELECT id, product_name, guide_enabled FROM products ORDER BY product_name ASC";
$products_result = mysqli_query($conn, $products_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiered Pricing Management | Noble Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fafafa;
        }
        
        .card-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .card-shadow-hover {
            transition: all 0.2s ease;
        }
        
        .card-shadow-hover:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
        }
        
        .input-focus {
            transition: all 0.15s ease;
        }
        
        .input-focus:focus {
            border-color: #000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary {
            background: #000;
            color: #fff;
            transition: all 0.15s ease;
        }
        
        .btn-primary:hover {
            background: #1a1a1a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secondary {
            background: #fff;
            color: #000;
            border: 1px solid #e5e5e5;
            transition: all 0.15s ease;
        }
        
        .btn-secondary:hover {
            background: #f5f5f5;
            border-color: #000;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e5e5e5, transparent);
        }
        
        .tier-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            background: #000;
            color: #fff;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #000;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php include '../navbar/top.php'; ?>
    
    <div class="bg-white border-b border-gray-200">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-black rounded-lg flex items-center justify-center">
                        <i class='bx bx-calculator text-white text-xl'></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Tiered Pricing Management</h1>
                        <p class="text-xs text-gray-500">Volume-based discount configuration with calculator guide</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Logged in as</p>
                    <p class="text-sm font-semibold text-gray-900"><?php echo $_SESSION['noble_user']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6 py-8 max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg card-shadow p-6 sticky top-6">
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-3">
                            Product Selection
                        </label>
                        <select id="productSelect" class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg input-focus text-sm font-medium text-gray-900">
                            <option value="">Select a product...</option>
                            <?php while($product = mysqli_fetch_assoc($products_result)): ?>
                                <option value="<?php echo $product['id']; ?>" 
                                        data-guide="<?php echo $product['guide_enabled']; ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="divider my-6"></div>

                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class='bx bx-info-circle text-gray-600 text-lg'></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-900 mb-1">How It Works</p>
                                <p class="text-xs text-gray-600 leading-relaxed">
                                    Set minimum purchase thresholds and corresponding discount percentages. Discounts apply automatically when customers meet the criteria.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class='bx bx-calculator text-gray-600 text-lg'></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-900 mb-1">Calculator Guide</p>
                                <p class="text-xs text-gray-600 leading-relaxed">
                                    Enable guide feature to show area calculator for AAC blocks with auto-calculation of adhesive and brackets needed.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div id="tiersContainer" class="hidden">
                    
                    <div class="bg-white rounded-lg card-shadow p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Pricing Tiers</h2>
                                <p class="text-xs text-gray-500 mt-1">Configure discount thresholds and benefits</p>
                            </div>
                            <button onclick="addTierRow()" class="btn-primary px-4 py-2.5 rounded-lg flex items-center gap-2 font-semibold text-sm">
                                <i class='bx bx-plus text-lg'></i>
                                Add Tier
                            </button>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Calculator Guide</p>
                                <p class="text-xs text-gray-500">Show area calculator for this product</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="guideToggle" onchange="toggleGuide()">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div id="calculatorGuide" class="hidden bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg card-shadow p-6 mb-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                                <i class='bx bx-calculator text-white text-xl'></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Area Calculator</h3>
                                <p class="text-xs text-gray-600">Calculate required pieces based on area</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg p-4 mb-4">
                            <!-- Size/Variant Selection -->
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Select Size/Variant</label>
                                <select id="variantSelect" class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg input-focus text-sm font-medium text-gray-900">
                                    <option value="">Choose a size...</option>
                                </select>
                            </div>

                            <!-- Dimensions Display (shown after variant selection) -->
                            <div id="dimensionsDisplay" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Width (mm)</label>
                                    <div class="text-center text-lg font-bold text-gray-900" id="blockWidth">-</div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Height (mm)</label>
                                    <div class="text-center text-lg font-bold text-gray-900" id="blockHeight">-</div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Length (mm)</label>
                                    <div class="text-center text-lg font-bold text-gray-900" id="blockLength">-</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Area to Cover (sqm)</label>
                                <input type="number" id="areaInput" step="0.01" placeholder="Enter area in square meters" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg input-focus" oninput="calculatePieces()">
                            </div>

                            <button onclick="calculatePieces()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-all">
                                Calculate Required Pieces
                            </button>
                        </div>

                        <div id="calculationResults" class="hidden space-y-3">
                            <div class="bg-white rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
                                            <i class='bx bx-cube text-white text-xl'></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">AAC Blocks Needed</p>
                                            <p class="text-2xl font-bold text-gray-900" id="blocksNeeded">0</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">With 5% wastage</p>
                                        <p class="text-lg font-bold text-gray-900" id="blocksWithWastage">0</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="bg-white rounded-lg p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                                            <i class='bx bx-droplet text-white text-xl'></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Adhesive (30%)</p>
                                            <p class="text-xl font-bold text-gray-900"><span id="adhesiveNeeded">0</span> pcs</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white rounded-lg p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                                            <i class='bx bx-bracket text-white text-xl'></i>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Brackets (25%)</p>
                                            <p class="text-xl font-bold text-gray-900"><span id="bracketsNeeded">0</span> pcs</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tiersList" class="space-y-4 mb-6"></div>

                    <div id="emptyState" class="hidden bg-white rounded-lg card-shadow p-12 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class='bx bx-layer text-gray-400 text-4xl'></i>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 mb-2">No Pricing Tiers</h3>
                        <p class="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                            This product doesn't have any tiered pricing configured yet. Add your first tier to get started.
                        </p>
                        <button onclick="addTierRow()" class="btn-primary px-6 py-3 rounded-lg inline-flex items-center gap-2 font-semibold text-sm">
                            <i class='bx bx-plus text-lg'></i>
                            Add First Tier
                        </button>
                    </div>

                    <div class="bg-white rounded-lg card-shadow p-6">
                        <button onclick="saveTiers()" class="w-full bg-black hover:bg-gray-900 text-white font-bold py-4 rounded-lg flex items-center justify-center gap-3 transition-all">
                            <i class='bx bx-save text-xl'></i>
                            Save All Changes
                        </button>
                    </div>
                </div>

                <div id="initialState" class="bg-white rounded-lg card-shadow p-16 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class='bx bx-calculator text-gray-600 text-5xl'></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Ready to Configure Pricing</h3>
                    <p class="text-gray-500 max-w-md mx-auto leading-relaxed">
                        Select a product from the sidebar to start configuring volume-based discounts and pricing tiers.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let tierCounter = 0;
        let currentProductId = null;
        let currentProductDimensions = {
            width: 0,
            height: 0,
            length: 0
        };

        const swalConfig = {
            customClass: {
                confirmButton: 'btn-primary px-6 py-3 rounded-lg font-semibold',
                cancelButton: 'btn-secondary px-6 py-3 rounded-lg font-semibold'
            },
            buttonsStyling: false
        };

        $('#productSelect').change(function() {
            const productId = $(this).val();
            const selectedOption = $(this).find('option:selected');
            
            if (productId) {
                currentProductId = productId;
                
                // Reset dimensions
                currentProductDimensions = {
                    width: 0,
                    height: 0,
                    length: 0
                };
                
                // Load variants for this product
                loadVariantsForCalculator(productId);
                
                // Get guide status
                const guideEnabled = selectedOption.data('guide') == 1;
                $('#guideToggle').prop('checked', guideEnabled);
                
                if (guideEnabled) {
                    $('#calculatorGuide').removeClass('hidden');
                } else {
                    $('#calculatorGuide').addClass('hidden');
                }
                
                loadTiers(productId);
                $('#tiersContainer').removeClass('hidden');
                $('#initialState').addClass('hidden');
            } else {
                $('#tiersContainer').addClass('hidden');
                $('#initialState').removeClass('hidden');
            }
        });

        function loadVariantsForCalculator(productId) {
            $.ajax({
                url: 'target-price-management-get-product-variants.php',
                method: 'GET',
                data: { product_id: productId },
                dataType: 'json',
                success: function(response) {
                    const variantSelect = $('#variantSelect');
                    variantSelect.empty().append('<option value="">Choose a size...</option>');
                    
                    if (response.success && response.variants.length > 0) {
                        response.variants.forEach(variant => {
                            const option = $('<option></option>')
                                .val(variant.id)
                                .text(variant.size)
                                .data('width', variant.width)
                                .data('height', variant.height)
                                .data('length', variant.length);
                            variantSelect.append(option);
                        });
                    } else {
                        variantSelect.append('<option value="">No variants available</option>');
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load variants.',
                        ...swalConfig
                    });
                }
            });
        }

        // When variant is selected, show dimensions
        $(document).on('change', '#variantSelect', function() {
            const selectedOption = $(this).find('option:selected');
            const width = parseFloat(selectedOption.data('width')) || 0;
            const height = parseFloat(selectedOption.data('height')) || 0;
            const length = parseFloat(selectedOption.data('length')) || 0;
            
            if (width && height && length) {
                currentProductDimensions = { width, height, length };
                
                $('#blockWidth').text(width);
                $('#blockHeight').text(height);
                $('#blockLength').text(length);
                
                $('#dimensionsDisplay').removeClass('hidden').addClass('fade-in');
            } else {
                $('#dimensionsDisplay').addClass('hidden');
                currentProductDimensions = { width: 0, height: 0, length: 0 };
            }
            
            // Clear previous calculations
            $('#calculationResults').addClass('hidden');
            $('#areaInput').val('');
        });

        function toggleGuide() {
            const isEnabled = $('#guideToggle').is(':checked');
            
            if (!currentProductId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Product Selected',
                    text: 'Please select a product first.',
                    ...swalConfig
                });
                $('#guideToggle').prop('checked', false);
                return;
            }
            
            $.ajax({
                url: 'target-price-management-on-and-off.php',
                method: 'POST',
                data: {
                    product_id: currentProductId,
                    guide_enabled: isEnabled ? 1 : 0
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (isEnabled) {
                            $('#calculatorGuide').removeClass('hidden').addClass('fade-in');
                        } else {
                            $('#calculatorGuide').addClass('hidden');
                            $('#calculationResults').addClass('hidden');
                            $('#areaInput').val('');
                        }
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Guide Status Updated',
                            text: `Calculator guide ${isEnabled ? 'enabled' : 'disabled'} successfully.`,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Update Failed',
                            text: response.message || 'Unable to update guide status.',
                            ...swalConfig
                        });
                        $('#guideToggle').prop('checked', !isEnabled);
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to update guide status. Please try again.',
                        ...swalConfig
                    });
                    $('#guideToggle').prop('checked', !isEnabled);
                }
            });
        }

        function calculatePieces() {
            const area = parseFloat($('#areaInput').val());
            
            if (!area || area <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Input',
                    text: 'Please enter a valid area value.',
                    ...swalConfig
                });
                return;
            }
            
            const width = currentProductDimensions.width / 1000;
            const height = currentProductDimensions.height / 1000;
            
            if (!width || !height) {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Dimensions',
                    text: 'Product dimensions are not set properly.',
                    ...swalConfig
                });
                return;
            }
            
            const areaPerBlock = width * height;
            const blocksNeeded = Math.ceil(area / areaPerBlock);
            const blocksWithWastage = Math.ceil(blocksNeeded * 1.05);
            const adhesiveNeeded = Math.ceil(blocksWithWastage * 0.30);
            const bracketsNeeded = Math.ceil(blocksWithWastage * 0.25);
            
            $('#blocksNeeded').text(blocksNeeded);
            $('#blocksWithWastage').text(blocksWithWastage);
            $('#adhesiveNeeded').text(adhesiveNeeded);
            $('#bracketsNeeded').text(bracketsNeeded);
            
            $('#calculationResults').removeClass('hidden').addClass('fade-in');
        }

        function loadTiers(productId) {
            $.ajax({
                url: 'target-price-management-get-tier.php',
                method: 'GET',
                data: { product_id: productId },
                dataType: 'json',
                success: function(response) {
                    $('#tiersList').empty();
                    tierCounter = 0;
                    
                    if (response.success && response.tiers.length > 0) {
                        response.tiers.forEach(tier => {
                            addTierRow(tier);
                        });
                        $('#emptyState').addClass('hidden');
                    } else {
                        $('#emptyState').removeClass('hidden');
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Loading Tiers',
                        text: 'Unable to load pricing tiers. Please try again.',
                        ...swalConfig
                    });
                }
            });
        }

        function addTierRow(data = null) {
            const id = data ? data.id : '';
            const minAmount = data ? data.min_amount : '';
            const discount = data ? data.discount_percent : '';
            const freeShipping = data ? data.free_shipping : 0;
            
            const html = `
                <div class="bg-white rounded-lg card-shadow card-shadow-hover p-6" data-tier-id="${tierCounter}">
                    <input type="hidden" name="tier_id[]" value="${id}">
                    
                    <div class="flex items-center justify-between mb-4">
                        <div class="tier-badge">
                            Tier ${tierCounter + 1}
                        </div>
                        <button type="button" onclick="removeTier(${tierCounter}, '${id}')"
                                class="text-gray-400 hover:text-red-600 transition-colors">
                            <i class='bx bx-trash text-xl'></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">
                                Minimum Purchase
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-medium text-sm">₱</span>
                                <input type="number" name="min_amount[]" value="${minAmount}" 
                                       placeholder="500000" step="0.01" required
                                       class="w-full pl-8 pr-4 py-3 bg-white border border-gray-300 rounded-lg input-focus font-semibold text-gray-900">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">
                                Discount Rate
                            </label>
                            <div class="relative">
                                <input type="number" name="discount[]" value="${discount}" 
                                       placeholder="4.00" step="0.01" min="0" max="100"
                                       class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg input-focus font-semibold text-gray-900">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-medium text-sm">%</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">
                                Free Shipping
                            </label>
                            <select name="free_shipping[]" 
                                    class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg input-focus font-semibold text-gray-900">
                                <option value="0" ${freeShipping == 0 ? 'selected' : ''}>No</option>
                                <option value="1" ${freeShipping == 1 ? 'selected' : ''}>Yes</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            
            $('#tiersList').append(html);
            $('#emptyState').addClass('hidden');
            tierCounter++;
        }

        function removeTier(tierId, dbId) {
            if (dbId) {
                Swal.fire({
                    title: 'Delete Pricing Tier?',
                    text: "This action cannot be undone. The tier will be permanently removed.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Delete',
                    cancelButtonText: 'Cancel',
                    ...swalConfig
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'target-price-management-delete.php',
                            method: 'POST',
                            data: { tier_id: dbId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $(`[data-tier-id="${tierId}"]`).fadeOut(300, function() {
                                        $(this).remove();
                                        checkEmptyState();
                                    });
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Deleted Successfully',
                                        text: 'The pricing tier has been removed.',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Deletion Failed',
                                        text: response.message || 'Unable to delete tier.',
                                        ...swalConfig
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Failed to delete tier. Please try again.',
                                    ...swalConfig
                                });
                            }
                        });
                    }
                });
            } else {
                $(`[data-tier-id="${tierId}"]`).fadeOut(300, function() {
                    $(this).remove();
                    checkEmptyState();
                });
            }
        }

        function checkEmptyState() {
            if ($('#tiersList > div').length === 0) {
                $('#emptyState').removeClass('hidden');
            }
        }

        function saveTiers() {
            const productId = $('#productSelect').val();
            if (!productId) {
                Swal.fire({
                    icon: 'error',
                    title: 'No Product Selected',
                    text: 'Please select a product before saving.',
                    ...swalConfig
                });
                return;
            }

            const tiers = [];
            $('#tiersList > div').each(function() {
                const tier = {
                    id: $(this).find('input[name="tier_id[]"]').val(),
                    min_amount: $(this).find('input[name="min_amount[]"]').val(),
                    discount: $(this).find('input[name="discount[]"]').val(),
                    free_shipping: $(this).find('select[name="free_shipping[]"]').val()
                };
                
                if (tier.min_amount) {
                    tiers.push(tier);
                }
            });

            if (tiers.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Tiers to Save',
                    text: 'Please add at least one pricing tier.',
                    ...swalConfig
                });
                return;
            }

            Swal.fire({
                title: 'Saving Changes',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'target-price-management-save.php',
                method: 'POST',
                data: {
                    product_id: productId,
                    tiers: JSON.stringify(tiers)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Changes Saved',
                            text: 'All pricing tiers have been updated successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            loadTiers(productId);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Save Failed',
                            text: response.message || 'Unable to save changes.',
                            ...swalConfig
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to save changes. Please try again.',
                        ...swalConfig
                    });
                }
            });
        }
    </script>
</body>
</html>