<?php 
session_start(); 

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in response
ini_set('log_errors', 1);

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
            error_log("Database connection loaded from: " . $path);
            break;
        }
    }
    
    if (!isset($conn) || $conn === null) {
        throw new Exception('Database connection file not found');
    }

    // Check if request is AJAX
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Direct access not allowed'
        ]);
        exit;
    }

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method. Expected POST, got ' . $_SERVER['REQUEST_METHOD']
        ]);
        exit;
    }

    // ✅ Restore session from remember_token (email or mobile-based or Google)
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            
            // 🔐 Store essential user session info
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'] ?? '';
            $_SESSION['user_mobile'] = $user['mobile'] ?? '';
            
            // 👤 Check if it's a Google account (optional)
            if (!empty($user['google_id'])) {
                $_SESSION['google_logged_in'] = true;
                $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
            }
            
            error_log("Session restored from remember_token for user ID: " . $user['id']);
        } else {
            error_log("Invalid remember_token found: " . substr($token, 0, 10) . '...');
            // Clear invalid cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        $stmt->close();
    }

    // Debug session info (remove in production)
    error_log("Session ID: " . session_id());
    error_log("Session status: " . session_status());
    error_log("User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
    error_log("Remember token present: " . (isset($_COOKIE['remember_token']) ? 'YES' : 'NO'));

    // ✅ Final session check
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // For AJAX requests, don't redirect - return JSON error instead
        echo json_encode([
            'success' => false,
            'message' => 'User not logged in',
            'redirect' => '../google-callback.php', // Frontend can handle redirect
            'session_id' => session_id(),
            'session_status' => session_status(),
            'debug' => [
                'session_exists' => isset($_SESSION),
                'user_id_set' => isset($_SESSION['user_id']),
                'user_id_value' => $_SESSION['user_id'] ?? 'not set',
                'remember_token_exists' => isset($_COOKIE['remember_token'])
            ]
        ]);
        exit;
    }

    $user_id = intval($_SESSION['user_id']);
    $item_id = null;

    // Log received data
    error_log("POST data: " . print_r($_POST, true));
    $raw_input = file_get_contents('php://input');
    error_log("Raw input: " . $raw_input);

    // Handle different input formats
    // First try to get from POST data (form-encoded)
    if (isset($_POST['key']) && is_numeric($_POST['key'])) {
        $item_id = intval($_POST['key']);
        error_log("Item ID from POST key: " . $item_id);
    } 
    // Then try JSON input
    else if (!empty($raw_input)) {
        $input = json_decode($raw_input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($input['item_id']) && is_numeric($input['item_id'])) {
                $item_id = intval($input['item_id']);
                error_log("Item ID from JSON item_id: " . $item_id);
            } elseif (isset($input['key']) && is_numeric($input['key'])) {
                $item_id = intval($input['key']);
                error_log("Item ID from JSON key: " . $item_id);
            }
        } else {
            error_log("JSON decode error: " . json_last_error_msg());
        }
    }

    // Validate item_id
    if ($item_id === null || $item_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid item ID provided',
            'received_data' => [
                'post' => $_POST,
                'raw_input' => $raw_input,
                'parsed_item_id' => $item_id
            ]
        ]);
        exit;
    }

    error_log("Processing removal for item ID: " . $item_id . " by user ID: " . $user_id);

    // Start transaction
    $conn->autocommit(false);

    try {
        // Verify the item belongs to the current user before deleting
        $verify_stmt = $conn->prepare("SELECT id FROM user_cart_items WHERE id = ? AND user_id = ?");
        $verify_stmt->bind_param("ii", $item_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();

        if ($verify_result->num_rows === 0) {
            $verify_stmt->close();
            $conn->rollback();
            
            error_log("Item not found or access denied - Item ID: " . $item_id . ", User ID: " . $user_id);
            
            echo json_encode([
                'success' => false,
                'message' => 'Item not found or access denied',
                'item_id' => $item_id,
                'user_id' => $user_id
            ]);
            exit;
        }
        $verify_stmt->close();

        // Delete the item
        $delete_stmt = $conn->prepare("DELETE FROM user_cart_items WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $item_id, $user_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception("Failed to execute delete statement: " . $conn->error);
        }
        
        $affected_rows = $delete_stmt->affected_rows;
        $delete_stmt->close();

        if ($affected_rows === 0) {
            throw new Exception("No item was deleted - possibly already removed");
        }

        error_log("Successfully deleted item ID: " . $item_id . " (affected rows: " . $affected_rows . ")");

        // Get updated cart count and total
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

        error_log("Cart updated - Total items: " . $total_cart_items . ", Total amount: " . $total_amount);

        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart successfully',
            'total_items' => $total_cart_items,
            'total_amount' => $total_amount,
            'item_count' => $item_count,
            'item_id' => $item_id,
            'user_id' => $user_id
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $conn->autocommit(true);
        error_log("Database transaction error: " . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    // Log comprehensive error information
    error_log("Cart item removal error: " . $e->getMessage() . 
              " | Item ID: " . ($item_id ?? 'unknown') . 
              " | User ID: " . ($user_id ?? 'unknown') .
              " | File: " . $e->getFile() . 
              " | Line: " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error removing item from cart',
        'error_details' => $e->getMessage(),
        'debug' => [
            'item_id' => $item_id ?? null,
            'user_id' => $user_id ?? null,
            'post_data' => $_POST ?? [],
            'raw_input' => file_get_contents('php://input'),
            'session_data' => [
                'session_id' => session_id(),
                'user_id_set' => isset($_SESSION['user_id']),
                'user_id_value' => $_SESSION['user_id'] ?? null
            ]
        ]
    ]);
} finally {
    // Ensure database connection is closed
    if (isset($conn) && $conn !== null) {
        $conn->close();
    }
}
?>