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

// 🔍 Debug output (remove in production)
error_log("Debug - Product ID: $product_id, Rating: $rating, User ID: $user_id");

if ($product_id && $rating >= 1 && $rating <= 5) {
    // 🔄 Insert or update (1 user = 1 rating per product)
    $stmt = $conn->prepare("INSERT INTO product_ratings (product_id, user_id, rating)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE rating = ?");
    $stmt->bind_param("iiii", $product_id, $user_id, $rating, $rating);
    
    if ($stmt->execute()) {
        // 📊 Calculate new average rating
        $avg_stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating FROM product_ratings WHERE product_id = ?");
        $avg_stmt->bind_param("i", $product_id);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result()->fetch_assoc();
        $new_average = round($avg_result['avg_rating'] ?? 0, 1);
        $avg_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'new_average' => $new_average,
            'message' => 'Rating submitted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode([
        'success' => false, 
        'message' => "Invalid input - Product ID: $product_id, Rating: $rating"
    ]);
}
?>