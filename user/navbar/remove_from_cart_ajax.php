<?php 
session_name("nobleuser");
session_start(); 

// Set content type to JSON early
header('Content-Type: application/json; charset=utf-8');
// Prevent caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

try {
    // Include database connection
    $connection_paths = [
        '../../connection/connect.php',
        '../../../connection/connect.php',
        '../../connect.php',
        '../connect.php',
        'connect.php'
    ];
    
    $conn = null;
    foreach ($connection_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            error_log("✅ Database connection loaded from: " . $path);
            break;
        }
    }
    
    if (!isset($conn) || $conn === null) {
        throw new Exception('❌ Database connection file not found');
    }

    // ✅ Restore session from remember_token
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'] ?? '';
            $_SESSION['user_mobile'] = $user['mobile'] ?? '';
            
            if (!empty($user['google_id'])) {
                $_SESSION['google_logged_in'] = true;
                $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
            }
            
            error_log("✅ Session restored for user ID: " . $user['id']);
        }
        $stmt->close();
    }

    // Determine if user is logged in or guest
    $is_guest = !isset($_SESSION['user_id']) || empty($_SESSION['user_id']);
    $user_id = $is_guest ? null : intval($_SESSION['user_id']);
    
    error_log("🔍 REMOVE CART DEBUG:");
    error_log("   - Guest mode: " . ($is_guest ? 'YES' : 'NO'));
    error_log("   - User ID: " . ($user_id ?? 'N/A'));
    error_log("   - Session ID: " . session_id());
    error_log("   - Request Method: " . $_SERVER['REQUEST_METHOD']);

    // Get item ID/key from various sources
    $cart_key = null;

    // 1. Try GET parameter (most common for direct links)
    if (isset($_GET['key']) && !empty($_GET['key'])) {
        $cart_key = $_GET['key'];
        error_log("   - Cart key from GET: " . $cart_key);
    }
    // 2. Try POST parameter
    elseif (isset($_POST['key']) && !empty($_POST['key'])) {
        $cart_key = $_POST['key'];
        error_log("   - Cart key from POST: " . $cart_key);
    }
    // 3. Try JSON input
    else {
        $raw_input = file_get_contents('php://input');
        if (!empty($raw_input)) {
            $input = json_decode($raw_input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $cart_key = $input['key'] ?? $input['item_id'] ?? null;
                error_log("   - Cart key from JSON: " . $cart_key);
            }
        }
    }

    error_log("   - Final cart_key: " . ($cart_key ?? 'NULL'));

    // Validate cart key
    if ($cart_key === null || $cart_key === '') {
        error_log("❌ No cart key provided!");
        error_log("   GET: " . print_r($_GET, true));
        error_log("   POST: " . print_r($_POST, true));
        
        echo json_encode([
            'success' => false,
            'message' => 'No item key provided',
            'debug' => [
                'get' => $_GET,
                'post' => $_POST,
                'is_guest' => $is_guest
            ]
        ]);
        exit;
    }

    // ====================================================
    // GUEST MODE: Remove from session
    // ====================================================
    if ($is_guest) {
        error_log("👤 GUEST MODE - Removing item");
        
        // Initialize guest cart if not exists
        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
            error_log("   - Initialized empty guest cart");
        }

        error_log("   - Current guest cart keys: " . json_encode(array_keys($_SESSION['guest_cart'])));
        error_log("   - Trying to remove key: " . $cart_key);

        // Check if item exists in guest cart
        if (isset($_SESSION['guest_cart'][$cart_key])) {
            $removed_item = $_SESSION['guest_cart'][$cart_key];
            unset($_SESSION['guest_cart'][$cart_key]);
            
            error_log("✅ GUEST: Successfully removed item");
            error_log("   - Removed item: " . json_encode($removed_item));

            // Calculate updated totals
            $total_items = 0;
            $total_amount = 0;
            
            foreach ($_SESSION['guest_cart'] as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $total_items += $qty;
                $total_amount += ($qty * $price);
            }

            error_log("   - Remaining items: " . count($_SESSION['guest_cart']));
            error_log("   - Total quantity: " . $total_items);

            echo json_encode([
                'success' => true,
                'message' => 'Item removed from cart',
                'total_items' => $total_items,
                'total_amount' => $total_amount,
                'item_count' => count($_SESSION['guest_cart']),
                'cart_key' => $cart_key,
                'is_guest' => true,
                'removed_item' => $removed_item
            ]);
            exit;
            
        } else {
            error_log("❌ GUEST: Item not found in cart");
            error_log("   - Looking for: " . $cart_key);
            error_log("   - Available keys: " . json_encode(array_keys($_SESSION['guest_cart'])));
            
            echo json_encode([
                'success' => false,
                'message' => 'Item not found in guest cart',
                'cart_key' => $cart_key,
                'is_guest' => true,
                'available_keys' => array_keys($_SESSION['guest_cart']),
                'guest_cart_content' => $_SESSION['guest_cart']
            ]);
            exit;
        }
    }

    // ====================================================
    // LOGGED-IN USER MODE: Remove from database
    // ====================================================
    
    if (!is_numeric($cart_key)) {
        error_log("❌ LOGGED-IN: Invalid item ID format (not numeric): " . $cart_key);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid item ID format',
            'cart_key' => $cart_key,
            'is_guest' => false
        ]);
        exit;
    }
    
    $item_id = intval($cart_key);
    error_log("🔐 LOGGED-IN MODE - Removing item");
    error_log("   - Item ID: " . $item_id);
    error_log("   - User ID: " . $user_id);

    // Start transaction
    $conn->autocommit(false);

    try {
        // Verify the item belongs to the current user
        $verify_stmt = $conn->prepare("SELECT id, product_id, quantity FROM user_cart_items WHERE id = ? AND user_id = ?");
        $verify_stmt->bind_param("ii", $item_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();

        if ($verify_result->num_rows === 0) {
            $verify_stmt->close();
            $conn->rollback();
            
            error_log("❌ LOGGED-IN: Item not found or access denied");
            
            // Check if item exists but belongs to different user
            $check_stmt = $conn->prepare("SELECT user_id FROM user_cart_items WHERE id = ?");
            $check_stmt->bind_param("i", $item_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                error_log("   - Item exists but belongs to user: " . $row['user_id']);
            } else {
                error_log("   - Item does not exist in database");
            }
            $check_stmt->close();
            
            echo json_encode([
                'success' => false,
                'message' => 'Item not found or access denied',
                'item_id' => $item_id,
                'user_id' => $user_id,
                'is_guest' => false
            ]);
            exit;
        }
        
        $item_data = $verify_result->fetch_assoc();
        error_log("   - Found item: " . json_encode($item_data));
        $verify_stmt->close();

        // Delete the item
        $delete_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $item_id, $user_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception("Failed to delete: " . $conn->error);
        }
        
        $affected_rows = $delete_stmt->affected_rows;
        $delete_stmt->close();

        error_log("✅ LOGGED-IN: Deleted successfully");
        error_log("   - Affected rows: " . $affected_rows);

        // Get updated cart totals
        $count_stmt = $conn->prepare("
            SELECT 
                COUNT(*) as count, 
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(price * quantity), 0) as total_amount
            FROM user_cart_items 
            WHERE user_id = ?
        ");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        
        $total_cart_items = intval($count_data['total_quantity']);
        $total_amount = floatval($count_data['total_amount']);
        $item_count = intval($count_data['count']);
        
        $count_stmt->close();

        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);

        error_log("   - Remaining items: " . $item_count);
        error_log("   - Total quantity: " . $total_cart_items);

        echo json_encode([
            'success' => true,
            'message' => 'Item removed successfully',
            'total_items' => $total_cart_items,
            'total_amount' => $total_amount,
            'item_count' => $item_count,
            'item_id' => $item_id,
            'user_id' => $user_id,
            'is_guest' => false
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        error_log("❌ LOGGED-IN: Transaction error: " . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    error_log("❌ FATAL ERROR: " . $e->getMessage());
    error_log("   File: " . $e->getFile());
    error_log("   Line: " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error removing item',
        'error' => $e->getMessage(),
        'debug' => [
            'cart_key' => $cart_key ?? null,
            'user_id' => $user_id ?? null,
            'is_guest' => $is_guest ?? true,
            'get' => $_GET ?? [],
            'post' => $_POST ?? []
        ]
    ]);
} finally {
    if (isset($conn) && $conn !== null) {
        $conn->close();
    }
}
?>