<?php 
// save_quotation.php
session_name("nobleadmin");
session_start();

// Prevent any output before headers
ob_start();

// Include required files
include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .input-error {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Quotation System</h1>
            <p class="text-gray-600">Create and manage your quotations efficiently</p>
        </div>

        <!-- Main Form -->
        <form id="quotationForm" class="bg-white rounded-lg shadow-lg p-8">
            <!-- Basic Information Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-2">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quotation For: <span class="text-red-500">*</span></label>
                        <input type="text" id="quotationFor" name="quotationFor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter client name" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address: <span class="text-red-500">*</span></label>
                        <input type="text" id="address" name="address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter address" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quotation No#:</label>
                        <input type="text" id="quotationNo" name="quotationNo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100" placeholder="Auto-generated" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quotation Date:</label>
                        <input type="date" id="quotationDate" name="quotationDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Valid Gap (Days):</label>
                        <input type="number" id="validGap" name="validGap" value="30" min="1" max="365" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Valid Until:</label>
                        <input type="date" id="validUntil" name="validUntil" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person: <span class="text-red-500">*</span></label>
                        <input type="text" id="contactPerson" name="contactPerson" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter contact person" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prepared By:</label>
                        <input type="text" id="preparedBy" name="preparedBy" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly value="Loading...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee:</label>
                        <input type="text" id="employee" name="employee" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter employee name">
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800 border-b pb-2">Items</h2>
                    <button type="button" id="addItemBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Item
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 rounded-lg">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border border-gray-300 p-3 text-left min-w-[120px]">Item Identifier</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[150px]">Item <span class="text-red-500">*</span></th>
                                <th class="border border-gray-300 p-3 text-left min-w-[200px]">Description</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[100px]">Width (mm)</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[100px]">Height (mm)</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[120px]">Size</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[80px]">Unit</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[80px]">Quantity</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[120px]">Unit Material Price <span class="text-red-500">*</span></th>
                                <th class="border border-gray-300 p-3 text-left min-w-[120px]">Unit Total Material</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[100px]">Labor %</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[100px]">Unit Labor</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[100px]">Unit Total</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[100px]">Total</th>
                                <th class="border border-gray-300 p-3 text-left min-w-[80px]">Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Items will be added here dynamically -->
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-bold">
                                <td colspan="13" class="border border-gray-300 p-3 text-right">Grand Total:</td>
                                <td class="border border-gray-300 p-3" id="grandTotal">₱0.00</td>
                                <td class="border border-gray-300 p-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-4">
                <button type="button" id="previewBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition duration-200">
                    Preview
                </button>
                <button type="submit" id="saveBtn" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg transition duration-200">
                    Save Quotation
                </button>
            </div>
        </form>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
            <div class="bg-white p-6 rounded-lg">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                <p class="mt-4 text-gray-700">Processing...</p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <div id="messageContainer" class="fixed top-4 right-4 z-50"></div>
    </div>

    <script>
        $(document).ready(function() {
            let itemCounter = 0;

            // Initialize form
            initializeForm();

            function initializeForm() {
                // Set current date
                const today = new Date().toISOString().split('T')[0];
                $('#quotationDate').val(today);
                
                // Calculate valid until date
                updateValidUntilDate();
                
                // Generate quotation number
                generateQuotationNumber();
                
                // Get user info and set prepared by
                getUserInfo();
                
                // Add first item row
                addItemRow();
            }

            function getUserInfo() {
                $.ajax({
                    url: 'save_quotation.php',
                    method: 'POST',
                    data: { action: 'get_user_info' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#preparedBy').val(response.name || 'Unknown User');
                        } else {
                            $('#preparedBy').val('System User');
                        }
                    },
                    error: function() {
                        $('#preparedBy').val('System User');
                    }
                });
            }

            function generateQuotationNumber() {
                const date = new Date();
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
                $('#quotationNo').val(`QT-${year}${month}${day}-${random}`);
            }

            function updateValidUntilDate() {
                const quotationDate = $('#quotationDate').val();
                const validGap = parseInt($('#validGap').val()) || 30;
                
                if (quotationDate) {
                    const date = new Date(quotationDate);
                    date.setDate(date.getDate() + validGap);
                    $('#validUntil').val(date.toISOString().split('T')[0]);
                }
            }

            // Event listeners
            $('#quotationDate, #validGap').on('change', updateValidUntilDate);
            $('#addItemBtn').on('click', addItemRow);

            function addItemRow() {
                itemCounter++;
                
                const row = `
                    <tr class="item-row fade-in" data-item-id="${itemCounter}">
                        <td class="border border-gray-300 p-2">
                            <select class="item-identifier w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                <option value="Custom">Custom</option>
                                <option value="Mats">Mats</option>
                            </select>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="text" class="item-name w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500" placeholder="Item name" required>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <textarea class="description w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 resize-none" rows="2" placeholder="Item description"></textarea>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" min="0" class="width-mm w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500" placeholder="Width in mm">
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" min="0" class="height-mm w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500" placeholder="Height in mm">
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="text" class="size-display w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" readonly placeholder="Size/Mats">
                        </td>
                        <td class="border border-gray-300 p-2">
                            <select class="unit w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                <option value="pcs">pcs</option>
                                <option value="m²">m²</option>
                                <option value="set">set</option>
                                <option value="roll">roll</option>
                                <option value="sheet">sheet</option>
                            </select>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" min="1" class="quantity w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500" value="1">
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" min="0" class="unit-material-price w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500" placeholder="0.00" required>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" class="unit-total-material w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" readonly>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" min="0" max="100" class="labor-percentage w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500" placeholder="20">
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" class="unit-labor w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" readonly>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" class="unit-total w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" readonly>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <input type="number" step="0.01" class="total w-full px-2 py-1 border border-gray-300 rounded bg-gray-100" readonly>
                        </td>
                        <td class="border border-gray-300 p-2">
                            <button type="button" class="remove-item bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm transition duration-200">
                                Remove
                            </button>
                        </td>
                    </tr>
                `;
                
                $('#itemsTableBody').append(row);
                bindItemEvents();
            }

            function bindItemEvents() {
                // Remove item
                $('.remove-item').off('click').on('click', function() {
                    if ($('.item-row').length > 1) {
                        $(this).closest('tr').remove();
                        calculateAllTotals();
                    } else {
                        showMessage('At least one item is required', 'error');
                    }
                });

                // Calculate on input change
                $('.width-mm, .height-mm, .unit-material-price, .quantity, .labor-percentage').off('input').on('input', function() {
                    calculateRowTotal($(this).closest('tr'));
                    calculateAllTotals();
                });

                // Item identifier change
                $('.item-identifier').off('change').on('change', function() {
                    const $row = $(this).closest('tr');
                    const identifier = $(this).val();
                    const $sizeField = $row.find('.size-display');
                    
                    if (identifier === 'Mats') {
                        $sizeField.removeClass('bg-gray-100').prop('readonly', false);
                        $sizeField.attr('placeholder', 'Enter mats specification');
                    } else {
                        $sizeField.addClass('bg-gray-100').prop('readonly', true);
                        $sizeField.attr('placeholder', 'Size (m²)');
                    }
                    
                    calculateRowTotal($row);
                    calculateAllTotals();
                });
            }

            function calculateRowTotal($row) {
                const itemIdentifier = $row.find('.item-identifier').val();
                const width = parseFloat($row.find('.width-mm').val()) || 0;
                const height = parseFloat($row.find('.height-mm').val()) || 0;
                const unitMaterialPrice = parseFloat($row.find('.unit-material-price').val()) || 0;
                const quantity = parseFloat($row.find('.quantity').val()) || 1;
                const laborPercentage = parseFloat($row.find('.labor-percentage').val()) || 0;

                let size = 0;
                let unitTotalMaterial = 0;
                let unitLabor = 0;
                let unitTotal = 0;
                let total = 0;

                if (itemIdentifier === 'Custom') {
                    if (width > 0 && height > 0) {
                        size = (width / 1000) * (height / 1000); // Convert mm to m²
                        $row.find('.size-display').val(size.toFixed(4) + ' m²');
                    } else {
                        $row.find('.size-display').val('');
                    }
                    
                    unitTotalMaterial = unitMaterialPrice * size;
                } else if (itemIdentifier === 'Mats') {
                    unitTotalMaterial = unitMaterialPrice;
                }
                
                if (laborPercentage > 0 && unitTotalMaterial > 0) {
                    unitLabor = (unitTotalMaterial * laborPercentage) / 100;
                }
                
                unitTotal = unitTotalMaterial + unitLabor;
                total = unitTotal * quantity;

                // Update fields
                $row.find('.unit-total-material').val(unitTotalMaterial.toFixed(2));
                $row.find('.unit-labor').val(unitLabor.toFixed(2));
                $row.find('.unit-total').val(unitTotal.toFixed(2));
                $row.find('.total').val(total.toFixed(2));
            }

            function calculateAllTotals() {
                let grandTotal = 0;
                
                $('.item-row').each(function() {
                    const total = parseFloat($(this).find('.total').val()) || 0;
                    grandTotal += total;
                });
                
                $('#grandTotal').text('₱' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            }

            // Form submission
            $('#quotationForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!validateForm()) {
                    return;
                }
                
                const formData = collectFormData();
                saveQuotation(formData);
            });

            function validateForm() {
                let isValid = true;
                
                // Reset error states
                $('.input-error').removeClass('input-error');
                
                // Check required fields
                const requiredFields = [
                    { field: '#quotationFor', message: 'Quotation For is required' },
                    { field: '#address', message: 'Address is required' },
                    { field: '#contactPerson', message: 'Contact Person is required' }
                ];
                
                requiredFields.forEach(item => {
                    if (!$(item.field).val().trim()) {
                        $(item.field).addClass('input-error');
                        isValid = false;
                    }
                });
                
                // Check if at least one item exists
                if ($('.item-row').length === 0) {
                    showMessage('Please add at least one item to the quotation.', 'error');
                    isValid = false;
                }
                
                // Validate items
                $('.item-row').each(function(index) {
                    const itemName = $(this).find('.item-name').val().trim();
                    const unitPrice = parseFloat($(this).find('.unit-material-price').val()) || 0;
                    
                    if (!itemName) {
                        $(this).find('.item-name').addClass('input-error');
                        showMessage(`Item ${index + 1}: Name is required`, 'error');
                        isValid = false;
                    }
                    
                    if (unitPrice <= 0) {
                        $(this).find('.unit-material-price').addClass('input-error');
                        showMessage(`Item ${index + 1}: Unit Material Price must be greater than 0`, 'error');
                        isValid = false;
                    }
                });
                
                return isValid;
            }

            function collectFormData() {
                const items = [];
                
                $('.item-row').each(function() {
                    const item = {
                        identifier: $(this).find('.item-identifier').val(),
                        name: $(this).find('.item-name').val(),
                        description: $(this).find('.description').val(),
                        width: parseFloat($(this).find('.width-mm').val()) || 0,
                        height: parseFloat($(this).find('.height-mm').val()) || 0,
                        size: $(this).find('.size-display').val(),
                        unit: $(this).find('.unit').val(),
                        quantity: parseFloat($(this).find('.quantity').val()) || 1,
                        unitMaterialPrice: parseFloat($(this).find('.unit-material-price').val()) || 0,
                        unitTotalMaterial: parseFloat($(this).find('.unit-total-material').val()) || 0,
                        laborPercentage: parseFloat($(this).find('.labor-percentage').val()) || 0,
                        unitLabor: parseFloat($(this).find('.unit-labor').val()) || 0,
                        unitTotal: parseFloat($(this).find('.unit-total').val()) || 0,
                        total: parseFloat($(this).find('.total').val()) || 0
                    };
                    items.push(item);
                });
                
                return {
                    quotationFor: $('#quotationFor').val(),
                    address: $('#address').val(),
                    quotationNo: $('#quotationNo').val(),
                    quotationDate: $('#quotationDate').val(),
                    contactPerson: $('#contactPerson').val(),
                    preparedBy: $('#preparedBy').val(),
                    validGap: parseInt($('#validGap').val()) || 30,
                    validUntil: $('#validUntil').val(),
                    employee: $('#employee').val(),
                    items: items,
                    grandTotal: parseFloat($('#grandTotal').text().replace('₱', '').replace(/,/g, '')) || 0
                };
            }

            function saveQuotation(data) {
                $('#loadingSpinner').removeClass('hidden');
                $('#saveBtn').addClass('loading');
                
                $.ajax({
                    url: 'save_quotation.php',
                    method: 'POST',
                    data: {
                        action: 'save_quotation',
                        quotation_data: JSON.stringify(data)
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#loadingSpinner').addClass('hidden');
                        $('#saveBtn').removeClass('loading');
                        
                        if (response.success) {
                            showMessage('Quotation saved successfully!', 'success');
                            // Optionally redirect after delay
                            setTimeout(function() {
                                // window.location.href = 'quotations_list.php';
                            }, 2000);
                        } else {
                            showMessage('Error saving quotation: ' + response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loadingSpinner').addClass('hidden');
                        $('#saveBtn').removeClass('loading');
                        
                        let errorMessage = 'Connection error occurred';
                        if (xhr.responseText) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.message || errorMessage;
                            } catch (e) {
                                errorMessage = 'Server error occurred';
                            }
                        }
                        
                        showMessage('Error: ' + errorMessage, 'error');
                        console.error('AJAX Error:', xhr.responseText);
                    }
                });
            }

            function showMessage(message, type) {
                const alertClass = type === 'success' ? 'bg-green-500' : 'bg-red-500';
                const messageDiv = `
                    <div class="alert ${alertClass} text-white px-4 py-3 rounded mb-2 shadow-lg fade-in">
                        ${message}
                    </div>
                `;
                
                $('#messageContainer').append(messageDiv);
                
                // Auto remove after 5 seconds
                setTimeout(function() {
                    $('#messageContainer .alert').first().fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }

            $('#previewBtn').on('click', function() {
                if (!validateForm()) {
                    return;
                }
                
                const data = collectFormData();
                console.log('Preview data:', data);
                showMessage('Preview functionality - Check console for data', 'success');
            });

            // Initialize events
            bindItemEvents();
        });
    </script>
</body>
</html>