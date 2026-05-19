<?php
//index-admin-loggin.php - FIXED VERSION

// Add this at the top of index-admin-logging.php, before the match()
require_once ROOT_PATH . '/admin/authentication/index-admin-role.php';
// ✅ SET TIMEZONE CONSISTENTLY
date_default_timezone_set('Asia/Manila');

// ✅ LOCAL ENVIRONMENT DETECTION
$is_local = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) ||
    strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0;

include ROOT_PATH . '/connection/connect.php';

// ✅ SET MYSQL TIMEZONE TO MATCH PHP
$conn->query("SET time_zone = '+08:00'");

$response = [];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

try {
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"]);
    $remember_me = isset($_POST["remember_me"]) && $_POST["remember_me"] == "1";

    // ✅ COOKIE FUNCTION - Local/Production compatible
    function setRememberCookie($name, $value, $expire)
    {
        global $is_local;

        if ($is_local) {
            setcookie($name, $value, $expire, '/', '', false, true);
        } else {
            setcookie($name, $value, $expire, '/', '', true, true);
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    if (empty($email) || empty($password)) {
        throw new Exception("Please fill in all required fields.");
    }

    // ✅ CONSISTENT CLEANUP - Use same 4-hour timeout as roleaccount.php
    $cleanup_stmt = $conn->prepare("UPDATE nobleaccount SET is_online = 0, remember_token = NULL, remember_expires = NULL WHERE last_activity < DATE_SUB(NOW(), INTERVAL 4 HOUR)");
    if ($cleanup_stmt) {
        $cleanup_stmt->execute();
        $cleanup_stmt->close();
    }

    $stmt = $conn->prepare("SELECT id, email, password, lvl, subrole, status, last_login, failed_attempts, locked_until, is_online, remember_token, remember_expires FROM nobleaccount WHERE email = ? LIMIT 1");

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

    // ✅ Check for valid remember token first (auto-login)
    if (!empty($user['remember_token']) && !empty($user['remember_expires'])) {
        $remember_expires = new DateTime($user['remember_expires']);
        if ($remember_expires > new DateTime()) {
            // Valid remember token exists - allow automatic login
            goto login_success;
        }
    }

    // ✅ Force login option
    $force_login = isset($_POST["force_login"]) && $_POST["force_login"] == "1";

    if ($force_login) {
        // Force logout the existing session
        $force_logout = $conn->prepare("UPDATE nobleaccount SET is_online = 0, remember_token = NULL WHERE email = ?");
        if ($force_logout) {
            $force_logout->bind_param("s", $email);
            $force_logout->execute();
            $force_logout->close();
        }
    } else {
        // ✅ CONSISTENT online check - 4 hours instead of 30 minutes
        $last_activity_query = $conn->prepare("SELECT last_activity FROM nobleaccount WHERE email = ? LIMIT 1");
        if ($last_activity_query) {
            $last_activity_query->bind_param("s", $email);
            $last_activity_query->execute();
            $activity_result = $last_activity_query->get_result();

            if ($activity_result->num_rows > 0) {
                $activity_data = $activity_result->fetch_assoc();
                $last_activity = new DateTime($activity_data['last_activity']);
                $now = new DateTime();
                $diff = $now->diff($last_activity);
                $inactive_minutes = ($diff->h * 60) + $diff->i;

                // ✅ Changed to 4 hours (240 minutes) to match roleaccount.php
                if ((int) $user['is_online'] === 1 && $inactive_minutes < 240) {
                    // Offer force login option
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        $response = [
                            'success' => false,
                            'force_login_required' => true,
                            'message' => 'Account is currently active on another device. Would you like to force login?',
                            'inactive_minutes' => $inactive_minutes
                        ];
                        header('Content-Type: application/json');
                        echo json_encode($response);
                        exit;
                    } else {
                        throw new Exception("This account is already logged in.");
                    }
                }
            }
            $last_activity_query->close();
        }
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
        if ($update) {
            $update->bind_param("iss", $failed_attempts, $locked_until, $email);
            $update->execute();
            $update->close();
        }

        throw new Exception("Invalid email or password.");
    }

    login_success:

    // ✅ Generate remember token if "Remember Me" is checked
    $remember_token = null;
    $remember_expires = null;

    if ($remember_me) {
        $remember_token = bin2hex(random_bytes(32));
        $remember_expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        setRememberCookie(
            'noble_remember_token',
            $remember_token,
            time() + (30 * 24 * 60 * 60)
        );
        setRememberCookie(
            'noble_remember_email',
            $email,
            time() + (30 * 24 * 60 * 60)
        );
    }

    // ✅ Update user status with remember token
    if ($remember_me) {
        $reset = $conn->prepare("UPDATE nobleaccount SET failed_attempts = 0, locked_until = NULL, last_login = NOW(), last_activity = NOW(), is_online = 1, remember_token = ?, remember_expires = ? WHERE email = ?");
        if ($reset) {
            $reset->bind_param("sss", $remember_token, $remember_expires, $email);
        }
    } else {
        $reset = $conn->prepare("UPDATE nobleaccount SET failed_attempts = 0, locked_until = NULL, last_login = NOW(), last_activity = NOW(), is_online = 1, remember_token = NULL, remember_expires = NULL WHERE email = ?");
        if ($reset) {
            $reset->bind_param("s", $email);
        }
    }

    if ($reset && $reset->execute()) {
        error_log("User successfully logged in: " . $email . ($remember_me ? " (with remember me)" : ""));
        $reset->close();
    } else {
        error_log("Failed to update user status for: " . $email);
        throw new Exception("Login processing failed. Please try again.");
    }

    // Session regeneration
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // ✅ Set comprehensive session data
    $_SESSION['noble_user'] = $user['email'];
    $_SESSION['noble_lvl'] = $user['lvl'];
    $_SESSION['noble_subrole'] = $user['subrole'] ?? null;
    $_SESSION['noble_id'] = $user['id'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_db_check'] = time(); // For roleaccount.php validation
    $_SESSION['session_expires'] = time() + 86400;
    $_SESSION['is_online'] = true;
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['remember_me'] = $remember_me;

    $redirect = match (strtolower($user['lvl'])) {
        ROLE_SUPERADMIN, 'admin'                                                => BASE_URL . "/ownerdashboard",
        ROLE_SALES                                                              => BASE_URL . "/ordermain",
        ROLE_ACCOUNTANT => match (strtolower($user['subrole'] ?? '')) {
                SUBROLE_DOCUMENT_CONTROLLER                                     => BASE_URL . "/accountantvieworder",
                default                                                         => BASE_URL . "/accountant"
            },
        ROLE_SUPPLIER                                                           => BASE_URL . "/suppliercompany",
        ROLE_PRODUCTSPECIALIST                                                  => BASE_URL . "/addnewproduct",
        ROLE_LOGISTIC => match (strtolower($user['subrole'] ?? '')) { 
            SUBROLE_DISPATCHER                                                  => BASE_URL . "/logisticdispatcherdashboard",
            default                                                             => BASE_URL . "/logisticdashboard"
        },
        ROLE_WAREHOUSE => match (strtolower($user['subrole'] ?? '')) {
                SUBROLE_WAREHOUSE_RECEIVER                                      => BASE_URL . "/qrscanner",
                SUBROLE_WAREHOUSE_STAFF                                         => BASE_URL . "/warehousestaff",
                default                                                         => BASE_URL . "/warehousedashboard"
            },
        ROLE_HR                                                                 => BASE_URL . "/account",
        default                                                                 => BASE_URL . "/account"
    };


    $stmt->close();

    // AJAX or normal redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $response = [
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirect,
            'user_status' => 'online',
            'session_expires' => date('Y-m-d H:i:s', time() + 86400),
            'remember_me' => $remember_me
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    header("Location: " . $redirect);
    exit();

} catch (Exception $e) {
    error_log("Login error for " . ($email ?? 'unknown') . ": " . $e->getMessage());

    if (isset($stmt))
        $stmt->close();
    if (isset($last_activity_query))
        $last_activity_query->close();

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
    if (isset($conn))
        $conn->close();
}
?>