<?php
// main-update-auto_deactivate_timer-page-2-A.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

// SET TIMEZONE
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['noble_user'])) {
        throw new Exception("Unauthorized access");
    }

    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $action = $_POST['action'] ?? ''; // 'deactivate', 'activate', or 'check'

    if (!$variant_id) {
        throw new Exception("Invalid variant ID");
    }

    // Get FULL variant info - kasama ang lahat ng pricing data
    $variantQuery = "SELECT 
                        id, 
                        timer_discount_start, 
                        timer_discount_end, 
                        timer_discount_active,
                        timer_discount_percent,
                        original_price,
                        percent,
                        discount,
                        price
                    FROM product_variants 
                    WHERE id = $variant_id";
    $variantResult = $conn->query($variantQuery);

    if (!$variantResult || $variantResult->num_rows === 0) {
        throw new Exception("Variant not found");
    }

    $variant = $variantResult->fetch_assoc();
    $timerStart = $variant['timer_discount_start'];
    $timerEnd = $variant['timer_discount_end'];
    $isCurrentlyActive = $variant['timer_discount_active'];
    $timerDiscount = (float)$variant['timer_discount_percent'];
    $originalPrice = (float)$variant['original_price'];
    $markupPercent = (float)$variant['percent'];
    $regularDiscount = (float)$variant['discount'];
    $currentPrice = (float)$variant['price'];

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $nowTimestamp = $now->getTimestamp();

    // Parse dates
    $startDateTime = !empty($timerStart) ? new DateTime($timerStart, new DateTimeZone('Asia/Manila')) : null;
    $endDateTime = !empty($timerEnd) ? new DateTime($timerEnd, new DateTimeZone('Asia/Manila')) : null;

    $startTimestamp = $startDateTime ? $startDateTime->getTimestamp() : null;
    $endTimestamp = $endDateTime ? $endDateTime->getTimestamp() : null;

    // ✅ HELPER: Calculate correct price based on all discounts
    function calculateCorrectPrice($originalPrice, $markupPercent, $regularDiscount, $timerDiscount, $applyTimer = false) {
        // Step 1: Apply markup
        $afterMarkup = $originalPrice + ($originalPrice * $markupPercent / 100);
        
        // Step 2: Apply regular discount
        $afterRegularDiscount = $afterMarkup - ($afterMarkup * $regularDiscount / 100);
        
        // Step 3: Apply timer discount ONLY if enabled
        $finalPrice = $afterRegularDiscount;
        if ($applyTimer) {
            $finalPrice = $afterRegularDiscount - ($afterRegularDiscount * $timerDiscount / 100);
        }
        
        return round($finalPrice, 2);
    }

    if ($action === 'deactivate') {
        // DEACTIVATE - recalculate price WITHOUT timer discount
        if ($isCurrentlyActive) {
            // ✅ Calculate price WITHOUT timer discount
            $newPrice = calculateCorrectPrice($originalPrice, $markupPercent, $regularDiscount, $timerDiscount, false);
            
            error_log("🔄 DEACTIVATING Timer for Variant $variant_id");
            error_log("   Original: ₱" . number_format($originalPrice, 2));
            error_log("   After Markup ($markupPercent%): ₱" . number_format($originalPrice + ($originalPrice * $markupPercent / 100), 2));
            error_log("   After Regular Discount ($regularDiscount%): ₱" . number_format(calculateCorrectPrice($originalPrice, $markupPercent, $regularDiscount, $timerDiscount, false), 2));
            error_log("   New Price (NO timer): ₱" . number_format($newPrice, 2));
            
            // Update database with new price
            $updateSQL = "UPDATE product_variants 
                         SET timer_discount_active = 0,
                             price = $newPrice
                         WHERE id = $variant_id";
            
            if (!$conn->query($updateSQL)) {
                throw new Exception("Failed to update variant: " . $conn->error);
            }
            
            error_log("✅ Timer DEACTIVATED and price updated to ₱" . number_format($newPrice, 2));
            
            echo json_encode([
                'success' => true,
                'action' => 'deactivated',
                'message' => 'Timer discount deactivated',
                'old_price' => $currentPrice,
                'new_price' => $newPrice
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'action' => 'already_inactive',
                'message' => 'Timer was already inactive'
            ]);
        }
    } elseif ($action === 'activate') {
        // ACTIVATE - recalculate price WITH timer discount
        if (!$isCurrentlyActive && $startTimestamp && $nowTimestamp >= $startTimestamp && $nowTimestamp < $endTimestamp) {
            // ✅ Calculate price WITH timer discount
            $newPrice = calculateCorrectPrice($originalPrice, $markupPercent, $regularDiscount, $timerDiscount, true);
            
            error_log("🔄 ACTIVATING Timer for Variant $variant_id");
            error_log("   Original: ₱" . number_format($originalPrice, 2));
            error_log("   Timer Discount: $timerDiscount%");
            error_log("   New Price (WITH timer): ₱" . number_format($newPrice, 2));
            
            // Update database with new price
            $updateSQL = "UPDATE product_variants 
                         SET timer_discount_active = 1,
                             price = $newPrice
                         WHERE id = $variant_id";
            
            if (!$conn->query($updateSQL)) {
                throw new Exception("Failed to update variant: " . $conn->error);
            }
            
            error_log("✅ Timer ACTIVATED and price updated to ₱" . number_format($newPrice, 2));
            
            echo json_encode([
                'success' => true,
                'action' => 'activated',
                'message' => 'Timer discount activated',
                'old_price' => $currentPrice,
                'new_price' => $newPrice
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'action' => 'not_activated',
                'message' => 'Timer conditions not met for activation'
            ]);
        }
    } elseif ($action === 'check') {
        // CHECK - periodic check kung kailangan mag-auto-deactivate o activate
        $autoAction = null;
        $reason = '';
        $newPrice = $currentPrice;

        if ($isCurrentlyActive && $endTimestamp && $nowTimestamp >= $endTimestamp) {
            // ❌ TIMER EXPIRED - AUTO DEACTIVATE
            $newPrice = calculateCorrectPrice($originalPrice, $markupPercent, $regularDiscount, $timerDiscount, false);
            
            $updateSQL = "UPDATE product_variants 
                         SET timer_discount_active = 0,
                             price = $newPrice
                         WHERE id = $variant_id";
            
            $conn->query($updateSQL);
            
            $autoAction = 'auto_deactivated';
            $reason = 'Timer expired';
            error_log("⏰ AUTO-DEACTIVATED variant $variant_id - Timer expired");
            error_log("   Price updated from ₱" . number_format($currentPrice, 2) . " to ₱" . number_format($newPrice, 2));
            
        } elseif (!$isCurrentlyActive && $startTimestamp && $endTimestamp && $nowTimestamp >= $startTimestamp && $nowTimestamp < $endTimestamp) {
            // ✅ START TIME REACHED - AUTO ACTIVATE
            $newPrice = calculateCorrectPrice($originalPrice, $markupPercent, $regularDiscount, $timerDiscount, true);
            
            $updateSQL = "UPDATE product_variants 
                         SET timer_discount_active = 1,
                             price = $newPrice
                         WHERE id = $variant_id";
            
            $conn->query($updateSQL);
            
            $autoAction = 'auto_activated';
            $reason = 'Start time reached';
            error_log("⏰ AUTO-ACTIVATED variant $variant_id - Timer started");
            error_log("   Price updated from ₱" . number_format($currentPrice, 2) . " to ₱" . number_format($newPrice, 2));
        }

        echo json_encode([
            'success' => true,
            'action' => $autoAction ?? 'no_change',
            'reason' => $reason,
            'is_currently_active' => (bool)$isCurrentlyActive,
            'now' => date('Y-m-d H:i:s', $nowTimestamp),
            'start' => $timerStart,
            'end' => $timerEnd,
            'old_price' => $currentPrice,
            'new_price' => $newPrice
        ]);
    } else {
        throw new Exception("Invalid action: $action");
    }

} catch (Exception $e) {
    error_log("❌ Error in auto_deactivate_timer: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>