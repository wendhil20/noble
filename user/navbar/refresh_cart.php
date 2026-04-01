<?php
// refresh_cart.php
session_name("nobleuser");
if (session_status() === PHP_SESSION_NONE) session_start();

if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

function sendEmptyCart() {
    $empty_html = '<div class="text-center py-8">
        <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-3"></i>
        <p class="text-gray-500 text-sm">Your cart is empty</p>
        <a href="index-shop-page-2.php" class="inline-block mt-3 text-orange-600 hover:text-orange-700 text-sm font-medium">Start Shopping</a>
    </div>';
    if (ob_get_level()) ob_end_clean();
    echo json_encode([
        'success'     => true,
        'total_items' => 0,
        'cart_html'   => $empty_html,
        'footer_html' => '',
        'total'       => '0.00'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) sendEmptyCart();

$possible_paths = [
    '../../connection/connect.php',
    '../connection/connect.php',
    'connection/connect.php',
    '../../../connection/connect.php',
];

$conn_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $conn_loaded = true;
        break;
    }
}

if (!$conn_loaded) {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB connection not found']);
    exit;
}

try {
    // Quick count check
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total_qty FROM user_cart_items WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_row = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();

    $total_qty_quick = (int)($count_row['total_qty'] ?? 0);
    if ($total_qty_quick === 0) sendEmptyCart();

    // ✅ FIX: Join only what's needed for display
    // price column in user_cart_items is already the correct final price
    // saved by add_to_cart.php — no need to recalculate
    $stmt = $conn->prepare("
        SELECT
            c.*,
            t.type_image,
            p.descrip6,
            p.descrip7,
            p.product_name,
            p.main_image,
            pc.image as pc_image,
            -- ✅ For timer discount check only (to keep price fresh if timer expires)
            pv.price                  AS raw_price,
            pv.timer_discount_percent,
            pv.timer_discount_active,
            pv.timer_discount_start,
            pv.timer_discount_end,
            pc2.price                 AS color_addon_price
        FROM user_cart_items c
        LEFT JOIN product_types t   ON t.product_id = c.product_id AND t.type_name = c.type_name
        LEFT JOIN products p        ON c.product_id = p.id
        LEFT JOIN product_colors pc ON pc.id = c.color_id
        LEFT JOIN product_variants pv ON pv.id = c.variant_id
        LEFT JOIN product_colors pc2  ON pc2.id = c.color_id
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cart_items = [];
    $total      = 0;
    $total_qty  = 0;
    $now        = time();

    while ($row = $result->fetch_assoc()) {
        // ✅ FIX: Use stored price directly — it was saved correctly by add_to_cart.php
        // The price column already holds the final discounted price (e.g. 114.95)
        $unit_price = floatval($row['price']);

        // ✅ ONLY re-check if timer discount has EXPIRED since cart was last updated
        // If timer was active when added but now expired, restore to non-timer price
        $raw_price = floatval($row['raw_price'] ?? 0);
        $color_addon = floatval($row['color_addon_price'] ?? 0);

        if ($raw_price > 0) {
            $timer_active = isset($row['timer_discount_active']) && (int)$row['timer_discount_active'] === 1;
            $timer_end    = !empty($row['timer_discount_end']) ? strtotime($row['timer_discount_end']) : 0;
            $timer_start  = !empty($row['timer_discount_start']) ? strtotime($row['timer_discount_start']) : 0;

            if ($timer_active && $timer_end > 0) {
                if ($now >= $timer_start && $now <= $timer_end) {
                    // ✅ Timer still active — use stored price (already has timer discount)
                    // No change needed
                } else {
                    // ✅ Timer EXPIRED — recalculate without timer discount
                    // raw_price (price column) is already post-regular-discount
                    // just use it directly + color addon
                    $unit_price = round($raw_price + $color_addon, 2);

                    // Update stored price to reflect expiry
                    if (abs($unit_price - floatval($row['price'])) > 0.01) {
                        $fix_stmt = $conn->prepare("UPDATE user_cart_items SET price = ? WHERE id = ?");
                        $fix_stmt->bind_param("di", $unit_price, $row['id']);
                        $fix_stmt->execute();
                        $fix_stmt->close();
                        $row['price'] = $unit_price;
                    }
                }
            }
            // No timer — use stored price as-is
        }

        $row['price'] = $unit_price;
        $cart_items[] = $row;

        $qty    = intval($row['quantity']);
        $total += $unit_price * $qty;
        $total_qty += 1;
    }
    $stmt->close();

    // Generate cart items HTML
    ob_start();
    if (count($cart_items) > 0) {
        echo '<div class="space-y-3">';
        foreach ($cart_items as $item) {
            $unit_price   = floatval($item['price']);
            $quantity     = intval($item['quantity']);
            $display_name = htmlspecialchars($item['product_name'] ?? $item['codename'] ?? 'Product');

            if (!empty($item['pc_image'])) {
                $img_html = '<img src="../../' . htmlspecialchars($item['pc_image']) . '" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg flex-shrink-0">';
            } elseif (!empty($item['type_image'])) {
                $img_html = '<img src="../../' . htmlspecialchars($item['type_image']) . '" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg flex-shrink-0">';
            } elseif (!empty($item['main_image'])) {
                $img_html = '<img src="../../' . htmlspecialchars($item['main_image']) . '" alt="Product" class="w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-lg flex-shrink-0">';
            } else {
                $img_html = '<div class="w-10 h-10 sm:w-12 sm:h-12 bg-gray-200 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-image text-gray-400 text-xs"></i></div>';
            }

            $variant_parts = [];
            if (!empty($item['variant_name'])) $variant_parts[] = htmlspecialchars($item['variant_name']);
            if (!empty($item['color_name']))   $variant_parts[] = htmlspecialchars($item['color_name']);
            if (!empty($item['size']))         $variant_parts[] = htmlspecialchars($item['size']);
            $variant_text = implode(', ', $variant_parts);

            $descrip_parts = [];
            if (!empty($item['descrip6'])) $descrip_parts[] = htmlspecialchars($item['descrip6']);
            if (!empty($item['descrip7'])) $descrip_parts[] = htmlspecialchars($item['descrip7']);
            $descrip_html = !empty($descrip_parts)
                ? '<p class="text-[9px] sm:text-[10px] text-gray-400 truncate mt-1">' . implode(' &bull; ', $descrip_parts) . '</p>'
                : '';

            echo '
<div class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition cart-item-slide">
    ' . $img_html . '
    <div class="flex-1 min-w-0">
        <h4 class="font-medium text-xs sm:text-sm text-gray-800 truncate">' . $display_name . '</h4>
        <p class="text-[10px] sm:text-xs text-gray-500 truncate">' . $variant_text . '</p>
        ' . $descrip_html . '
        <div class="flex items-center justify-between mt-1">
            <span class="text-xs sm:text-sm text-orange-600">&#8369;' . number_format($unit_price, 2) . '</span>
            <span class="text-[10px] sm:text-xs text-gray-500">Qty: ' . $quantity . '</span>
        </div>
    </div>
    <a href="javascript:void(0)" onclick="removeFromCart(' . intval($item['id']) . ')" class="text-red-500 hover:text-red-700 transition p-1 flex-shrink-0">
        <i class="fas fa-times text-xs"></i>
    </a>
</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="text-center py-8">
            <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 text-sm">Your cart is empty</p>
            <a href="index-shop-page-2.php" class="inline-block mt-3 text-orange-600 hover:text-orange-700 text-sm font-medium">Start Shopping</a>
        </div>';
    }
    $cart_html = ob_get_clean();

    // Generate footer HTML
    $footer_html = '';
    if (count($cart_items) > 0) {
        $footer_html = '
<div class="flex justify-between items-center mb-3">
    <span class="text-sm text-gray-700">Total:</span>
    <span class="text-base sm:text-lg text-orange-600" id="cart-total">&#8369;' . number_format($total, 2) . '</span>
</div>
<div class="grid grid-cols-2 gap-2">
    <a href="../otherpage/index-cart_view-page-8.php" class="bg-black hover:bg-gray-800 text-white px-3 py-2 text-xs sm:text-sm text-center rounded transition">View Cart</a>
    <a href="javascript:void(0)" onclick="proceedToCheckout()" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 text-xs sm:text-sm text-center rounded transition">Checkout</a>
</div>';
    }

    $stray = ob_get_clean();
    if (!empty(trim($stray))) error_log("refresh_cart stray: " . substr($stray, 0, 200));

    echo json_encode([
        'success'     => true,
        'total_items' => $total_qty,
        'cart_html'   => $cart_html,
        'footer_html' => $footer_html,
        'total'       => number_format($total, 2)
    ]);

} catch (Exception $e) {
    error_log("Cart refresh error: " . $e->getMessage());
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>