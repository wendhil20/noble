<?php
session_name("nobleuser");
session_start();

// Prevent any HTML output
ob_start();
ob_clean();

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Database connection - adjust path as needed
require_once '../../connection/connect.php';

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    // Get cart items using the exact same query structure as your original modal
    // Based on your table structure: id, user_id, product_id, color_id, variant_id, quantity, price, type_name, variant_name, color_name, size, codename, descrip6, descrip7, added_at
    $stmt = $conn->prepare("
        SELECT c.*, t.type_image, v.descrip6 as variant_descrip6, v.descrip7 as variant_descrip7
        FROM user_cart_items c
        LEFT JOIN product_types t ON t.product_id = c.product_id AND t.type_name = c.type_name
        LEFT JOIN product_variants v ON c.variant_id = v.id
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cart_items = [];
    $total = 0;
    
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total += floatval($row['price']) * intval($row['quantity']);
    }
    
    $stmt->close();
    
    // Generate cart HTML
    ob_start();
    
    if (count($cart_items) > 0) {
        echo '<div class="space-y-3">';
        foreach ($cart_items as $item) {
            $unit_price = floatval($item['price']);
            $quantity = intval($item['quantity']);
            // Use codename from user_cart_items table (this column exists based on your screenshot)
            $display_name = htmlspecialchars($item['codename'] ?? 'Product');
            ?>
            <div class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition cart-item-slide">
                <?php if (!empty($item['type_image'])): ?>
                    <img src="../../<?= htmlspecialchars($item['type_image']) ?>" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-cover rounded-lg flex-shrink-0">
                <?php else: ?>
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-image text-gray-400 text-xs"></i>
                    </div>
                <?php endif; ?>

                <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-xs sm:text-sm text-gray-800 truncate"><?= $display_name ?></h4>
                    <p class="text-[10px] sm:text-xs text-gray-500 truncate">
                        <?= htmlspecialchars($item['variant_name'] ?? '') ?>
                        <?= !empty($item['color_name']) ? ', ' . htmlspecialchars($item['color_name']) : '' ?>
                        <?= !empty($item['size']) ? ', ' . htmlspecialchars($item['size']) : '' ?>
                    </p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs sm:text-sm font-semibold text-orange-600">₱<?= number_format($unit_price, 2) ?></span>
                        <span class="text-[10px] sm:text-xs text-gray-500">Qty: <?= $quantity ?></span>
                    </div>
                </div>

                <a href="javascript:void(0)" onclick="removeFromCart(<?= intval($item['id']) ?>)" class="text-red-500 hover:text-red-700 transition p-1 flex-shrink-0">
                    <i class="fas fa-times text-xs"></i>
                </a>
            </div>
            <?php
        }
        echo '</div>';
    } else {
        ?>
        <div class="text-center py-8">
            <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 text-sm">Your cart is empty</p>
            <a href="shop.php" class="inline-block mt-3 text-orange-600 hover:text-orange-700 text-sm font-medium">
                Start Shopping
            </a>
        </div>
        <?php
    }
    
    $cart_html = ob_get_clean();
    
    // Generate footer HTML
    $footer_html = '';
    if (count($cart_items) > 0) {
        ob_start();
        ?>
        <div class="flex justify-between items-center mb-3">
            <span class="font-medium text-sm text-gray-700">Total:</span>
            <span class="font-bold text-base sm:text-lg text-orange-600" id="cart-total">
                ₱<?= number_format($total, 2) ?>
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <a href="../otherpage/cart_view.php"
                class="bg-white border border-orange-500 text-orange-600 px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-center hover:bg-orange-50 transition">
                View Cart
            </a>
            <a href="checkout.php"
                class="bg-orange-500 text-white px-3 py-2 rounded-lg text-xs sm:text-sm font-medium text-center hover:bg-orange-600 transition">
                Checkout
            </a>
        </div>
        <?php
        $footer_html = ob_get_clean();
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'total_items' => count($cart_items),
        'cart_html' => $cart_html,
        'footer_html' => $footer_html,
        'total' => number_format($total, 2),
        'debug' => [
            'user_id' => $user_id,
            'items_found' => count($cart_items)
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Cart refresh error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'error_line' => $e->getLine(),
            'error_file' => basename($e->getFile())
        ]
    ]);
}
?>