<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Include database connection
include '../connection/connect.php';

// Initialize response array for JSON responses
$response = array();

// Check if request is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

try {
    // Sanitize and validate input
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"]);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }
    
    // Check for empty fields
    if (empty($email) || empty($password)) {
        throw new Exception("Please fill in all required fields.");
    }
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, email, password, lvl, status, last_login, failed_attempts, locked_until FROM nobleaccount WHERE email = ? LIMIT 1");
    
    if (!$stmt) {
        throw new Exception("Database error occurred. Please try again.");
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if user exists
    if ($result->num_rows !== 1) {
        // Don't reveal if email exists or not for security
        throw new Exception("Invalid email or password.");
    }
    
    $user = $result->fetch_assoc();
    
    // Check if account is active
    if (isset($user['status']) && $user['status'] !== 'active') {
        throw new Exception("Your account has been deactivated. Please contact support.");
    }
    
    // Check if account is temporarily locked
    if (isset($user['locked_until']) && $user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
        throw new Exception("Account temporarily locked due to multiple failed attempts. Please try again later.");
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Increment failed attempts
        $failed_attempts = isset($user['failed_attempts']) ? $user['failed_attempts'] + 1 : 1;
        $locked_until = null;
        
        // Lock account after 5 failed attempts for 30 minutes
        if ($failed_attempts >= 5) {
            $locked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        }
        
        // Update failed attempts
        $update_stmt = $conn->prepare("UPDATE nobleaccount SET failed_attempts = ?, locked_until = ? WHERE email = ?");
        $update_stmt->bind_param("iss", $failed_attempts, $locked_until, $email);
        $update_stmt->execute();
        $update_stmt->close();
        
        throw new Exception("Invalid email or password.");
    }
    
    // Successful login - Clear failed attempts and update last login
    $update_stmt = $conn->prepare("UPDATE nobleaccount SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE email = ?");
    $update_stmt->bind_param("s", $email);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Store user information in session
    $_SESSION['noble_user'] = $user['email'];
    $_SESSION['noble_lvl'] = $user['lvl'];
    $_SESSION['noble_id'] = $user['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Log successful login (optional)
    error_log("Successful login: " . $user['email'] . " at " . date('Y-m-d H:i:s'));
    
    // Determine redirect URL based on user level
    $redirect_url = "";
    switch (strtolower($user['lvl'])) {
        case 'superadmin':
            $redirect_url = "../admin/client/dashboard.php";
            break;
        case 'admin':
            $redirect_url = "../admin/client/dashboard.php";
            break;
        case 'sales':
            $redirect_url = "../sales/dashboard.php";
            break;
        case 'accountant':
            $redirect_url = "../accountant/dashboard.php";
            break;
        default:
            $redirect_url = "../admin/client/dashboard.php";
            break;
    }
    
    // Close statement
    $stmt->close();
    
    // If AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $response['success'] = true;
        $response['message'] = 'Login successful!';
        $response['redirect'] = $redirect_url;
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Regular form submission - redirect
    header("Location: " . $redirect_url);
    exit();
    
} catch (Exception $e) {
    // Log the error
    error_log("Login error: " . $e->getMessage() . " for email: " . ($email ?? 'unknown'));
    
    // Close statement if it exists
    if (isset($stmt)) {
        $stmt->close();
    }
    
    // If AJAX request, return JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Regular form submission - show alert and go back
    echo "<script>
        alert('" . addslashes($e->getMessage()) . "');
        window.history.back();
    </script>";
    exit();
    
} finally {
    // Always close database connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>