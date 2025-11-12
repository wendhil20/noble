<?php
session_name("nobleuser");
session_start();

ob_start();
ob_clean();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../../connection/connect.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    // UPDATED: Added product_colors join for color image
    $stmt = $conn->prepare("
        SELECT 
            c.*, 
            t.type_image, 
            p.descrip6, 
            p.descrip7, 
            p.product_name, 
            p.main_image,
            pc.image as pc_image
        FROM user_cart_items c
        LEFT JOIN product_types t ON t.product_id = c.product_id AND t.type_name = c.type_name
        LEFT JOIN products p ON c.product_id = p.id
        LEFT JOIN product_colors pc ON pc.id = c.color_id
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
    echo '<div class="space-y-3" style="/* Better cart scroll fix */
#cart-items-container {
    max-height: 400px; /* Increase from 240px/256px */
    overflow-y: auto;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db #f3f4f6;
}

/* WebKit browsers scrollbar */
#cart-items-container::-webkit-scrollbar {
    width: 6px;
}

#cart-items-container::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 10px;
}

#cart-items-container::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}

#cart-items-container::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Mobile responsive */
@media (max-width: 640px) {
    #cart-items-container {
        max-height: 350px;
    }
}

@media (max-width: 480px) {
    #cart-items-container {
        max-height: 300px;
    }
}

@media (max-width: 375px) {
    #cart-items-container {
        max-height: 250px;
    }
}">';
        foreach ($cart_items as $item) {
            $unit_price = floatval($item['price']);
            $quantity = intval($item['quantity']);
            $display_name = htmlspecialchars($item['product_name'] ?? $item['codename'] ?? 'Product');
            ?>
            <div class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition cart-item-slide">
                <?php if (!empty($item['pc_image'])): ?>
                    <img src="../../<?= htmlspecialchars($item['pc_image']) ?>" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg flex-shrink-0">
                <?php elseif (!empty($item['type_image'])): ?>
                    <img src="../../<?= htmlspecialchars($item['type_image']) ?>" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg flex-shrink-0">
                <?php elseif (!empty($item['main_image'])): ?>
                    <img src="../../<?= htmlspecialchars($item['main_image']) ?>" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg flex-shrink-0">
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
                    
                    <!-- Display descrip6 and descrip7 if available -->
                    <?php if (!empty($item['descrip6']) || !empty($item['descrip7'])): ?>
                        <p class="text-[9px] sm:text-[10px] text-gray-400 truncate mt-1">
                            <?= htmlspecialchars($item['descrip6'] ?: '') ?>
                            <?= !empty($item['descrip6']) && !empty($item['descrip7']) ? ' • ' : '' ?>
                            <?= htmlspecialchars($item['descrip7'] ?: '') ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs sm:text-sm text-black">₱<?= number_format($unit_price, 2) ?></span>
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
            <a href="index-shop-page-2.php" class="inline-block mt-3 text-orange-600 hover:text-orange-700 text-sm font-medium">
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
            <span class="text-sm text-gray-700">Total:</span>
            <span class="text-base sm:text-lg text-black" id="cart-total">
                ₱<?= number_format($total, 2) ?>
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <a href="../otherpage/index-cart_view-page-8.php"
                class="text-white px-3 py-2 text-xs sm:text-sm text-center transition bg-black">
                View Cart
            </a>
            <a href="../otherpage/index-checkout-page-12.php"
                class="text-white px-3 py-2 text-xs sm:text-sm text-center transition bg-black" >
                Checkout
            </a>
        </div>
        <?php
        $footer_html = ob_get_clean();
    }
    
    echo json_encode([
        'success' => true,
        'total_items' => count($cart_items),
        'cart_html' => $cart_html,
        'footer_html' => $footer_html,
        'total' => number_format($total, 2)
    ]);
    
} catch (Exception $e) {
    error_log("Cart refresh error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>