<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// // Check if request is AJAX
// if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
//     http_response_code(403);
//     exit('Direct access not allowed');
// }

// ✅ Restore session from remember_token if not already set
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_mobile'] = $user['mobile'] ?? '';

    if (!empty($user['google_id'])) {
      $_SESSION['google_logged_in'] = true;
      $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
    }
  }

  $stmt->close();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // ADD DETAILED DEBUGGING
    error_log("=== CART DEBUG START ===");
    error_log("User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
    error_log("Connection: " . ($conn ? 'OK' : 'FAILED'));
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        error_log("ERROR: User not logged in");
        echo json_encode([
            'success' => false,
            'message' => 'User not logged in',
            'debug' => 'Session user_id not set',
            'items' => [],
            'total_items' => 0,
            'total_price' => 0
        ]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    error_log("Processing for user ID: " . $user_id);

    // Check if database connection is working
    if (!$conn) {
        error_log("ERROR: Database connection failed");
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'debug' => 'Connection object is null',
            'items' => [],
            'total_items' => 0,
            'total_price' => 0
        ]);
        exit;
    }

    // Test if user_cart_items table exists
    $test_query = "SHOW TABLES LIKE 'user_cart_items'";
    $test_result = $conn->query($test_query);
    if ($test_result->num_rows == 0) {
        error_log("ERROR: user_cart_items table does not exist");
        echo json_encode([
            'success' => false,
            'message' => 'Cart table does not exist',
            'debug' => 'user_cart_items table not found',
            'items' => [],
            'total_items' => 0,
            'total_price' => 0
        ]);
        exit;
    }

    // Get cart items count
    error_log("Attempting to get cart count...");
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(quantity), 0) as total_quantity FROM user_cart_items WHERE user_id = ?");
    
    if (!$count_stmt) {
        error_log("ERROR: Failed to prepare count statement: " . $conn->error);
        echo json_encode([
            'success' => false,
            'message' => 'Database query preparation failed',
            'debug' => 'Count statement prepare failed: ' . $conn->error,
            'items' => [],
            'total_items' => 0,
            'total_price' => 0
        ]);
        exit;
    }
    
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    
    if (!$count_result) {
        error_log("ERROR: Count query failed: " . $count_stmt->error);
        echo json_encode([
            'success' => false,
            'message' => 'Count query failed',
            'debug' => 'Count query error: ' . $count_stmt->error,
            'items' => [],
            'total_items' => 0,
            'total_price' => 0
        ]);
        exit;
    }
    
    $count_data = $count_result->fetch_assoc();
    $total_cart_items = $count_data['total_quantity'];
    $count_stmt->close();
    
    error_log("Cart count successful. Total items: " . $total_cart_items);

    // Get cart items with details
    error_log("Attempting to get cart items...");
  $items_stmt = $conn->prepare("
    SELECT c.*, t.type_image, v.descrip6, v.descrip7
    FROM user_cart_items c
    LEFT JOIN product_types t ON t.product_id = c.product_id AND t.type_name = c.type_name
    LEFT JOIN product_variants v ON c.variant_id = v.id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
    
    if (!$items_stmt) {
        error_log("ERROR: Failed to prepare items statement: " . $conn->error);
        echo json_encode([
            'success' => false,
            'message' => 'Items query preparation failed',
            'debug' => 'Items statement prepare failed: ' . $conn->error,
            'items' => [],
            'total_items' => 0,
            'total_price' => 0
        ]);
        exit;
    }
    
    $items_stmt->bind_param("i", $user_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    if (!$items_result) {
        error_log("ERROR: Items query failed: " . $items_stmt->error);
        echo json_encode([
            'success' => false,
            'message' => 'Items query failed',
            'debug' => 'Items query error: ' . $items_stmt->error,
            'items' => [],
            'total_items' => 0,
            'total_price' => 0
        ]);
        exit;
    }

    $cart_items = [];
    $total_price = 0;

    while ($item = $items_result->fetch_assoc()) {
        $unit_price = floatval($item['price']);
        $quantity = intval($item['quantity']);
        $item_total = $unit_price * $quantity;
        $total_price += $item_total;

        $cart_items[] = [
            'id' => $item['id'],
            'product_id' => $item['product_id'],
            'codename' => $item['codename'],
            'type_name' => $item['type_name'],
            'variant_name' => $item['variant_name'],
            'color_name' => $item['color_name'],
            'size' => $item['size'],
            'price' => $unit_price,
            'quantity' => $quantity,
            'item_total' => $item_total,
            'type_image' => $item['type_image'],
            'descrip6' => $item['descrip6'],
            'descrip7' => $item['descrip7']
        ];
    }
    $items_stmt->close();

    error_log("Cart items retrieved successfully. Count: " . count($cart_items));

    // Return success response
    echo json_encode([
        'success' => true,
        'items' => $cart_items,
        'total_items' => $total_cart_items,
        'total_price' => $total_price,
        'formatted_total' => number_format($total_price, 2),
        'debug' => 'All queries successful'
    ]);

} catch (Exception $e) {
    // Log detailed error
    error_log("CART ERROR DETAILS: " . $e->getMessage());
    error_log("CART ERROR FILE: " . $e->getFile());
    error_log("CART ERROR LINE: " . $e->getLine());
    error_log("CART ERROR TRACE: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error loading cart data',
        'debug' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'items' => [],
        'total_items' => 0,
        'total_price' => 0
    ]);
}
?>