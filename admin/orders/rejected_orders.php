<?php
include '../../connection/connect.php';

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}


// Function to display all rejected orders
function displayRejectedOrders($conn) {
    // Updated query to handle NULL rejection_date properly
    $query = "SELECT * FROM orders WHERE status = 'Rejected' ORDER BY 
              CASE 
                  WHEN rejection_date IS NULL THEN created_at 
                  ELSE rejection_date 
              END DESC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo '<div class="space-y-6">';
        
        while ($order = $result->fetch_assoc()) {
            renderRejectedOrderCard($order, $conn);
        }
        
        echo '</div>';
    } else {
        echo '<div class="text-center py-12">';
        echo '<div class="text-gray-500 text-lg mb-4">No rejected orders found</div>';
        echo '<p class="text-gray-400">All orders are either pending, confirmed, or completed.</p>';
        echo '</div>';
    }
}

// Function to render individual rejected order card
function renderRejectedOrderCard($order, $conn) {
    $orderId = $order['id'];
    $customerName = htmlspecialchars($order['customer_name']);
    $email = htmlspecialchars($order['email']);
    $mobile = htmlspecialchars($order['mobile']);
    $address = htmlspecialchars($order['address']);
    $zipcode = isset($order['zipcode']) ? htmlspecialchars($order['zipcode']) : '';
    $total = floatval($order['total']);
    $finalTotal = isset($order['final_total']) ? floatval($order['final_total']) : $total;
    $discount = isset($order['discount']) ? floatval($order['discount']) : 0;
    $shippingFee = isset($order['shipping_fee']) ? floatval($order['shipping_fee']) : 0;
    $deliveryFee = isset($order['delivery_fee']) ? floatval($order['delivery_fee']) : 0;
    $vatAmount = isset($order['vat_amount']) ? floatval($order['vat_amount']) : 0;
    $createdAt = date('M j, Y g:i A', strtotime($order['created_at']));
    
    // Handle NULL rejection_date
    $rejectionDate = $order['rejection_date'] ? date('M j, Y g:i A', strtotime($order['rejection_date'])) : 'Not recorded';
    $rejectionReason = !empty($order['rejection_reason']) ? htmlspecialchars($order['rejection_reason']) : 'No reason provided';
    $modePayment = isset($order['mode_payment']) && !empty($order['mode_payment']) ? htmlspecialchars($order['mode_payment']) : 'Not specified';
    
    // Get order items
    $items_result = $conn->query("SELECT * FROM order_items WHERE order_id = $orderId");
    $items = [];
    if ($items_result) {
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
    }
    
    echo '<div class="border border-red-200 rounded-lg p-6 hover:shadow-md transition-shadow duration-200">';
    
    // Order Header
    echo '<div class="flex justify-between items-start mb-4">';
    echo '<div>';
    echo '<h3 class="text-xl font-semibold text-gray-900">Order #' . $orderId . '</h3>';
    echo '<p class="text-sm text-gray-600">Ordered: ' . $createdAt . '</p>';
    echo '<p class="text-sm text-red-600 font-medium">Rejected: ' . $rejectionDate . '</p>';
    echo '</div>';
    echo '<div class="flex flex-col items-end space-y-2">';
    echo '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">';
    echo '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
    echo '</svg>';
    echo 'Rejected';
    echo '</span>';
    echo '</div>';
    echo '</div>';
    
    
    // Customer Information
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';
    echo '<div>';
    echo '<h4 class="font-medium text-gray-900 mb-2">Customer Information</h4>';
    echo '<div class="text-sm text-gray-600 space-y-1">';
    echo '<p><strong>Name:</strong> ' . $customerName . '</p>';
    echo '<p><strong>Email:</strong> ' . $email . '</p>';
    echo '<p><strong>Mobile:</strong> ' . $mobile . '</p>';
    echo '<p><strong>Address:</strong> ' . $address . ($zipcode ? ' ' . $zipcode : '') . '</p>';
    echo '<p><strong>Payment Method:</strong> ' . $modePayment . '</p>';
    echo '</div>';
    echo '</div>';
    
    // Order Items
    echo '<div>';
    echo '<h4 class="font-medium text-gray-900 mb-2">Order Items</h4>';
    echo '<div class="text-sm text-gray-600 space-y-3">';
    
    if (!empty($items)) {
        foreach ($items as $item) {
            $productName = isset($item['product_name']) ? htmlspecialchars($item['product_name']) : 'Unknown Product';
            $codename = isset($item['codename']) ? htmlspecialchars($item['codename']) : '';
            $typename = isset($item['type_name']) ? htmlspecialchars($item['type_name']) : '';
            $variantColor = isset($item['variant_color']) && !empty($item['variant_color']) ? htmlspecialchars($item['variant_color']) : null;
            $size = isset($item['size']) && !empty($item['size']) ? htmlspecialchars($item['size']) : null;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
            $price = isset($item['price']) ? floatval($item['price']) : 0;
            $subtotal = isset($item['subtotal']) ? floatval($item['subtotal']) : ($quantity * $price);
            $descrip6 = isset($item['descrip6']) ? htmlspecialchars($item['descrip6']) : '';
            $descrip7 = isset($item['descrip7']) ? htmlspecialchars($item['descrip7']) : '';
            
            echo '<div class="bg-white border border-gray-200 rounded-md p-3">';
            echo '<div class="flex justify-between items-start">';
            echo '<div class="flex-1">';
            echo '<div class="font-medium text-gray-900">' . $productName . '</div>';
            
            // Product details
            echo '<div class="text-xs text-gray-600 mt-1 space-y-1">';
            if ($codename) {
                echo '<div><span class="font-medium">Code:</span> ' . $codename . '</div>';
            }
            if ($typename) {
                echo '<div><span class="font-medium">Type:</span> ' . $typename . '</div>';
            }
            if ($variantColor) {
                echo '<div><span class="font-medium">Color:</span> ' . $variantColor . '</div>';
            }
            if ($size) {
                echo '<div><span class="font-medium">Size:</span> ' . $size . '</div>';
            }
            if ($descrip6) {
                echo '<div><span class="font-medium">Details:</span> ' . $descrip6 . '</div>';
            }
            if ($descrip7) {
                echo '<div><span class="font-medium">Notes:</span> ' . $descrip7 . '</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '<div class="text-right ml-4">';
            echo '<div class="font-medium text-gray-900">Qty: ' . $quantity . '</div>';
            echo '<div class="text-sm text-gray-600">₱' . number_format($price, 2) . ' each</div>';
            echo '<div class="font-semibold text-red-600">₱' . number_format($subtotal, 2) . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<div class="bg-white border border-gray-200 rounded-md p-3">';
        echo '<p class="text-gray-500 text-center">No items found for this order</p>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Order Total with breakdown
    echo '<div class="bg-gray-100 rounded-lg p-4 mb-4">';
    echo '<div class="space-y-2">';
    
    // Subtotal
    echo '<div class="flex justify-between items-center text-sm">';
    echo '<span class="text-gray-600">Subtotal:</span>';
    echo '<span class="text-gray-900">₱' . number_format($total, 2) . '</span>';
    echo '</div>';
    
    // Discount (if any)
    if ($discount > 0) {
        echo '<div class="flex justify-between items-center text-sm">';
        echo '<span class="text-gray-600">Discount:</span>';
        echo '<span class="text-green-600">-₱' . number_format($discount, 2) . '</span>';
        echo '</div>';
    }
    
    // Shipping fee (if any)
    if ($shippingFee > 0) {
        echo '<div class="flex justify-between items-center text-sm">';
        echo '<span class="text-gray-600">Shipping Fee:</span>';
        echo '<span class="text-gray-900">₱' . number_format($shippingFee, 2) . '</span>';
        echo '</div>';
    }
    
    // Delivery fee (if any)
    if ($deliveryFee > 0) {
        echo '<div class="flex justify-between items-center text-sm">';
        echo '<span class="text-gray-600">Delivery Fee:</span>';
        echo '<span class="text-gray-900">₱' . number_format($deliveryFee, 2) . '</span>';
        echo '</div>';
    }
    
    // VAT (if any)
    if ($vatAmount > 0) {
        echo '<div class="flex justify-between items-center text-sm">';
        echo '<span class="text-gray-600">VAT (12%):</span>';
        echo '<span class="text-gray-900">₱' . number_format($vatAmount, 2) . '</span>';
        echo '</div>';
    }
    
    // Final total
    echo '<div class="border-t pt-2 mt-2">';
    echo '<div class="flex justify-between items-center">';
    echo '<span class="text-lg font-semibold text-gray-900">Total Amount:</span>';
    echo '<span class="text-xl font-bold text-red-600">₱' . number_format($finalTotal, 2) . '</span>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
}



// Then display the orders
displayRejectedOrders($conn);
?>