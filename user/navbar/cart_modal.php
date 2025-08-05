<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
?>

<!-- Cart Link with Hover Modal -->
<div class="relative" id="cart-container">
    <a href="javascript:void(0)" 
       onclick="navigateWithLoading('../otherpage/cart_view')"
       class="<?= $current_page == 'cart/cart_view' ? 'text-orange-600 underline font-bold' : 'text-black' ?> hover:text-orange-500 transition inline-flex items-center gap-1 relative font-mont p-2 rounded-lg hover:bg-orange-50"
       id="cart-link">
        <img src="../img/ecommerce.png" alt="Cart Icon" class="w-5 h-5 object-contain" />
        Cart
        <span id="cart-count-bubble" class="cart-count absolute -top-1 -right-2 bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold leading-none <?= $total_cart_items > 0 ? '' : 'hidden' ?>">
            <span class="cart-count" data-cart-count><?= $total_cart_items ?></span>
        </span>
    </a>

    <!-- Cart Hover Modal -->
    <div id="cart-modal" class="cart-modal fixed right-4 top-16 w-80 sm:w-96 bg-white rounded-xl shadow-2xl border border-gray-200 z-[9999] max-h-[80vh] overflow-hidden max-w-[calc(100vw-2rem)] opacity-0 invisible">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-4 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-shopping-cart"></i>
                    Your Cart
                </h3>
                <span class="bg-white/20 px-2 py-1 rounded-full text-sm font-medium" id="modal-cart-count">
                    <?= $total_cart_items ?> items
                </span>
            </div>
        </div>

        <!-- Cart Items -->
        <div class="max-h-60 sm:max-h-64 overflow-y-auto p-3 sm:p-4" id="cart-items-container">
            <?php if ($total_cart_items > 0): ?>
                <div class="space-y-3">
                    <?php 
                    // Fetch cart items for modal display
                    $modal_stmt = $conn->prepare("
                        SELECT c.*, t.type_image, v.descrip6, v.descrip7
                        FROM user_cart_items c
                        LEFT JOIN product_types t ON t.product_id = c.product_id AND t.type_name = c.type_name
                        LEFT JOIN product_variants v ON c.variant_id = v.id
                        WHERE c.user_id = ?
                    ");
                    $modal_stmt->bind_param("i", $user_id);
                    $modal_stmt->execute();
                    $modal_result = $modal_stmt->get_result();
                    
                    while ($item = $modal_result->fetch_assoc()):
                        $unit_price = floatval($item['price']);
                        $quantity = intval($item['quantity']);
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
                                <h4 class="font-medium text-xs sm:text-sm text-gray-800 truncate"><?= htmlspecialchars($item['codename']) ?></h4>
                                <p class="text-[10px] sm:text-xs text-gray-500 truncate">
                                    <?= htmlspecialchars($item['variant_name'] ?: '') ?>
                                    <?= !empty($item['color_name']) ? ', ' . htmlspecialchars($item['color_name']) : '' ?>
                                    <?= !empty($item['size']) ? ', ' . htmlspecialchars($item['size']) : '' ?>
                                </p>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs sm:text-sm font-semibold text-orange-600">₱<?= number_format($unit_price, 2) ?></span>
                                    <span class="text-[10px] sm:text-xs text-gray-500">Qty: <?= $quantity ?></span>
                                </div>
                            </div>
                            
                            <a href="../cart/remove_from_cart.php?key=<?= $item['id'] ?>" class="text-red-500 hover:text-red-700 transition p-1 flex-shrink-0">
                                <i class="fas fa-times text-xs"></i>
                            </a>
                        </div>
                    <?php endwhile; 
                    $modal_stmt->close(); 
                    ?>
                </div>
            <?php else: ?>
                <!-- Empty Cart -->
                <div class="text-center py-8">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500 text-sm">Your cart is empty</p>
                    <a href="shop.php" class="inline-block mt-3 text-orange-600 hover:text-orange-700 text-sm font-medium">
                        Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal Footer -->
        <?php if ($total_cart_items > 0): ?>
            <div class="border-t border-gray-200 p-3 sm:p-4 bg-gray-50 rounded-b-xl">
                <!-- Total Price -->
                <div class="flex justify-between items-center mb-3">
                    <span class="font-medium text-sm text-gray-700">Total:</span>
                    <span class="font-bold text-base sm:text-lg text-orange-600">
                        ₱<?php 
                        // Calculate total for modal
                        $total_stmt = $conn->prepare("SELECT SUM(price * quantity) as total FROM user_cart_items WHERE user_id = ?");
                        $total_stmt->bind_param("i", $user_id);
                        $total_stmt->execute();
                        $total_result = $total_stmt->get_result();
                        $total_row = $total_result->fetch_assoc();
                        echo number_format($total_row['total'] ?? 0, 2);
                        $total_stmt->close();
                        ?>
                    </span>
                </div>
                
                <!-- Action Buttons -->
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
            </div>
        <?php endif; ?>
    </div>
</div>