<?php
session_name("nobleadmin");
// Secure session settings - 24 hours
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Only works if site uses HTTPS
ini_set('session.use_strict_mode', 1);
session_start([
    'cookie_lifetime' => 86400, // 24 hours (24 * 60 * 60 = 86400 seconds)
    'gc_maxlifetime' => 86400   // Session data lifetime - 24 hours
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

    $stmt = $conn->prepare("SELECT id, email, password, lvl, status, last_login, failed_attempts, locked_until FROM nobleaccount WHERE email = ? LIMIT 1");

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

    // Reset attempts on success
    $reset = $conn->prepare("UPDATE nobleaccount SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE email = ?");
    $reset->bind_param("s", $email);
    $reset->execute();
    $reset->close();

    // Regenerate session ID
    session_regenerate_id(true);

    $_SESSION['noble_user'] = $user['email'];
    $_SESSION['noble_lvl'] = $user['lvl'];
    $_SESSION['noble_id'] = $user['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['session_expires'] = time() + 86400; // 24 hours from now

    // Determine redirect
    $redirect = match (strtolower($user['lvl'])) {
        'superadmin', 'admin' => "../admin/client/dashboard.php",
        'sales' => "../admin/orders/ordering",
        'accountant' => "../admin/accountant/dashboard.php",
        'supplier' => "../admin/productspecialist/dashboard.php",
        'productspecialist' => "../admin/shop/adminshop.php",
        'logistic' => "../admin/client/monitortracking",
        default => "../admin/client/dashboard.php"
    };

    $stmt->close();

    // AJAX or normal redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $response = [
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirect
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