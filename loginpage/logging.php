<?php
session_name("nobleadmin");
// Secure session settings - 24 hours
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start([
    'cookie_lifetime' => 86400,
    'gc_maxlifetime' => 86400
]);

include '../connection/connect.php';

$response = [];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

try {
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    if (empty($email) || empty($password)) {
        throw new Exception("Please fill in all required fields.");
    }

    // First, clean up any stale online status (users online for more than 30 minutes without activity)
    $cleanup_stmt = $conn->prepare("UPDATE nobleaccount SET is_online = 0 WHERE is_online = 1 AND last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $cleanup_stmt->execute();
    $cleanup_stmt->close();

    $stmt = $conn->prepare("SELECT id, email, password, lvl, status, last_login, failed_attempts, locked_until, is_online FROM nobleaccount WHERE email = ? LIMIT 1");

    if (!$stmt) {
        throw new Exception("Database error.");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        throw new Exception("Invalid email or password.");
    }

    $user = $result->fetch_assoc();

    // Status check
    if ($user['status'] !== 'active') {
        throw new Exception("Your account has been deactivated.");
    }

    // Check if user is already online (prevent multiple sessions)
    // Convert to int for proper comparison
    if ((int)$user['is_online'] === 1) {
        throw new Exception("This account is already logged in from another device. Please logout from the other device first.");
    }

    // Lockout check
    if (!empty($user['locked_until']) && new DateTime() < new DateTime($user['locked_until'])) {
        throw new Exception("Account locked due to failed login attempts. Try again later.");
    }

    // Password verify
    if (!password_verify($password, $user['password'])) {
        $failed_attempts = $user['failed_attempts'] + 1;
        $locked_until = null;

        if ($failed_attempts >= 5) {
            $locked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        }

        $update = $conn->prepare("UPDATE nobleaccount SET failed_attempts = ?, locked_until = ? WHERE email = ?");
        $update->bind_param("iss", $failed_attempts, $locked_until, $email);
        $update->execute();
        $update->close();

        throw new Exception("Invalid email or password.");
    }

    // CRITICAL FIX: Reset attempts on success and SET USER AS ONLINE with last_activity
    $reset = $conn->prepare("UPDATE nobleaccount SET failed_attempts = 0, locked_until = NULL, last_login = NOW(), last_activity = NOW(), is_online = 1 WHERE email = ?");
    $reset->bind_param("s", $email);
    
    if (!$reset->execute()) {
        throw new Exception("Failed to update user status. Please try again.");
    }
    
    // Verify the update worked
    if ($reset->affected_rows === 0) {
        error_log("Warning: No rows affected when setting user online for email: " . $email);
    }
    
    $reset->close();

    // Regenerate session ID
    session_regenerate_id(true);

    $_SESSION['noble_user'] = $user['email'];
    $_SESSION['noble_lvl'] = $user['lvl'];
    $_SESSION['noble_id'] = $user['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['session_expires'] = time() + 86400;
    $_SESSION['is_online'] = true;

    // Determine redirect
    $redirect = match (strtolower($user['lvl'])) {
        'superadmin', 'admin' => "../admin/client/dashboard",
        'sales' => "../admin/orders/ordering",
        'accountant' => "../admin/accountant/accountant",
        'supplier' => "../admin/suppliermain/suppliercompany",
        'productspecialist' => "../admin/shop/adminshop",
        'logistic' => "../admin/client/monitortracking",
        'warehouse' => "../admin/warehouse_management/order_list",
        default => "../admin/client/dashboard"
    };

    $stmt->close();

    // AJAX or normal redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $response = [
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirect,
            'user_status' => 'online'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    header("Location: " . $redirect);
    exit();

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());

    if (isset($stmt)) $stmt->close();

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    echo "<script>alert('" . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    exit();
} finally {
    if (isset($conn)) $conn->close();
}
?>