<?php
// index-customize_quote_handler-page-4-AA.php
header('Content-Type: application/json');
session_name("nobleuser");
session_start();

// Log all POST data
error_log("POST data received: " . print_r($_POST, true));

try {
    // Include database connection - SAME PATH AS PRODUCT PAGE
    if (!file_exists('../../connection/connect.php')) {
        throw new Exception("Connection file not found at ../../connection/connect.php");
    }
    include '../../connection/connect.php';
    
    // Check if connection exists
    if (!isset($conn)) {
        throw new Exception("Database connection not established");
    }

    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data
    $customType = $_POST['customType'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $fullName = $_POST['fullName'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    $agreeTerms = isset($_POST['agreeTerms']) ? 1 : 0;
    $productId = intval($_POST['product_id'] ?? 0);
    $selectedColor = $_POST['selected_color'] ?? '';
    $selectedVariant = $_POST['selected_variant'] ?? '';

    // Validate required fields
    if (empty($specifications) || empty($fullName) || empty($email) || empty($phone)) {
        throw new Exception('Please fill in all required fields');
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Validate phone (basic validation)
    if (strlen($phone) < 10) {
        throw new Exception('Invalid phone number');
    }

    // Get user ID if logged in
    $userId = $_SESSION['user_id'] ?? null;

    // Check if table exists, if not create it
    $checkTable = $conn->query("SHOW TABLES LIKE 'custom_quote_requests'");
    if ($checkTable->num_rows === 0) {
        // Create table if it doesn't exist
        $createTable = "
            CREATE TABLE custom_quote_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id INT NOT NULL,
                user_id INT,
                custom_type VARCHAR(50) NOT NULL,
                specifications LONGTEXT NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                message LONGTEXT,
                selected_color VARCHAR(100),
                selected_variant VARCHAR(100),
                agree_terms TINYINT(1) DEFAULT 0,
                status ENUM('pending', 'quoted', 'completed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (product_id),
                INDEX (user_id),
                INDEX (status),
                INDEX (created_at)
            )
        ";
        if (!$conn->query($createTable)) {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    }

    // Insert into database
    $stmt = $conn->prepare("
        INSERT INTO custom_quote_requests (
            product_id, 
            user_id, 
            custom_type, 
            specifications, 
            full_name, 
            email, 
            phone, 
            message, 
            selected_color, 
            selected_variant, 
            agree_terms, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "iissssssssi",
        $productId,
        $userId,
        $customType,
        $specifications,
        $fullName,
        $email,
        $phone,
        $message,
        $selectedColor,
        $selectedVariant,
        $agreeTerms
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $requestId = $conn->insert_id;
    $stmt->close();

    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Quote request saved successfully! We will contact you shortly.',
        'id' => $requestId
    ]);

} catch (Exception $e) {
    error_log("Customize Quote Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>