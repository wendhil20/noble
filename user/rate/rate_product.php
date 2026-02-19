<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
header('Content-Type: application/json');

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
    }
    
    $stmt->close();
}

// ✅ Final session check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// ✅ Reset AUTO_INCREMENT if needed
$tables = ['product_ratings'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

// 🔥 FIX: Handle JSON input properly
$input = json_decode(file_get_contents('php://input'), true);

// Get data from JSON input OR $_POST (fallback)
$user_id = $_SESSION['user_id'];
$product_id = (int)($input['product_id'] ?? $_POST['product_id'] ?? 0);
$rating = (int)($input['rating'] ?? $_POST['rating'] ?? 0);

// ============================================================================
// GET COMMENT FROM JSON INPUT - Optional feedback from user
// ============================================================================
// Sanitize comment: trim whitespace and limit to 500 characters
$comment = isset($input['comment']) ? trim($input['comment']) : '';
$comment = strlen($comment) > 500 ? substr($comment, 0, 500) : $comment;

// 🔍 Debug output (remove in production)
error_log("Debug - Product ID: $product_id, Rating: $rating, User ID: $user_id, Comment Length: " . strlen($comment));

if ($product_id && $rating >= 1 && $rating <= 5) {
    // ============================================================================
    // INSERT OR UPDATE RATING WITH COMMENT
    // ============================================================================
    // Use ON DUPLICATE KEY UPDATE to handle existing ratings
    // This updates both rating and comment if user re-rates the same product
    $stmt = $conn->prepare("
        INSERT INTO product_ratings (product_id, user_id, rating, comment, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            rating = VALUES(rating),
            comment = VALUES(comment),
            updated_at = NOW()
    ");
    
    // ============================================================================
    // BIND PARAMETERS: i=integer, s=string
    // ============================================================================
    // product_id (int), user_id (int), rating (int), comment (string)
    $stmt->bind_param("iiis", $product_id, $user_id, $rating, $comment);
    
    if ($stmt->execute()) {
        // ============================================================================
        // CALCULATE NEW AVERAGE RATING - Get updated average for the product
        // ============================================================================
        $avg_stmt = $conn->prepare("
            SELECT 
                AVG(rating) AS avg_rating,
                COUNT(*) AS total_ratings
            FROM product_ratings 
            WHERE product_id = ?
        ");
        $avg_stmt->bind_param("i", $product_id);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result()->fetch_assoc();
        $new_average = round($avg_result['avg_rating'] ?? 0, 1);
        $total_ratings = $avg_result['total_ratings'] ?? 0;
        $avg_stmt->close();
        
        // ============================================================================
        // RETURN SUCCESS RESPONSE WITH UPDATED DATA
        // ============================================================================
        echo json_encode([
            'success' => true, 
            'new_average' => $new_average,
            'total_ratings' => $total_ratings,
            'message' => 'Rating and comment submitted successfully',
            'rating' => $rating,
            'comment_length' => strlen($comment)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
} else {
    // ============================================================================
    // VALIDATION ERROR - Invalid product_id or rating
    // ============================================================================
    echo json_encode([
        'success' => false, 
        'message' => "Invalid input - Product ID: $product_id, Rating: $rating (must be 1-5)"
    ]);
}

// ============================================================================
// CLOSE DATABASE CONNECTION
// ============================================================================
$conn->close();
?>