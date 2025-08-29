<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../connection/connect.php';

// ✅ Define constants at the top
define('INACTIVITY_LIMIT', 86400); // 24 hours (86400 seconds)
define('VALIDATION_INTERVAL', 300); // Check database every 5 minutes
define('REGENERATION_INTERVAL', 7200); // 2 hours

// Redirect if session data is missing
if (!isset($_SESSION['noble_user']) || !isset($_SESSION['noble_lvl'])) {
    $_SESSION['access_denied'] = "You must login first.";
    header("Location: ../../loginpage/index.php");
    exit;
}

// ✅ RELAXED IP-based session security (only for major IP changes)
if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
} elseif (isset($_SESSION['user_ip'])) {
    $current_ip = $_SERVER['REMOTE_ADDR'];
    $session_ip = $_SESSION['user_ip'];
    
    // Only check if IPs are completely different (not just subnet changes)
    // This prevents logout due to mobile network switching or proxy changes
    $current_parts = explode('.', $current_ip);
    $session_parts = explode('.', $session_ip);
    
    // Only logout if first 2 octets are completely different (major IP change)
    if (count($current_parts) >= 2 && count($session_parts) >= 2) {
        if ($current_parts[0] !== $session_parts[0] && $current_parts[1] !== $session_parts[1]) {
            // Major IP change detected - log for security
            error_log("Major IP change detected for user: {$_SESSION['noble_user']} from {$session_ip} to {$current_ip}");
            
            // Optional: Instead of immediate logout, you could set a flag for re-authentication
            session_unset();
            session_destroy();
            $_SESSION['access_denied'] = "Security check required. Please login again.";
            header("Location: ../../loginpage/index.php");
            exit;
        }
    }
}

// ✅ Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ FIXED: Regenerate session ID less frequently (every 2 hours instead of 30 minutes)
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > REGENERATION_INTERVAL) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    // Don't regenerate CSRF token too often - only regenerate session ID
}

$email = $_SESSION['noble_user'];

// ✅ OPTIMIZED: Cache user validation to reduce database hits
$should_validate = !isset($_SESSION['last_db_check']) || (time() - $_SESSION['last_db_check']) > VALIDATION_INTERVAL;

if ($should_validate) {
    $stmt = $conn->prepare("SELECT id, lvl, status FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        session_unset();
        session_destroy();
        $_SESSION['access_denied'] = "Account not found.";
        header("Location: ../../loginpage/index.php");
        exit;
    }

    $user = $result->fetch_assoc();

    // Check if account is deactivated
    if (strtolower($user['status']) !== 'active') {
        session_unset();
        session_destroy();
        $_SESSION['access_denied'] = "Account deactivated.";
        header("Location: ../../loginpage/index.php");
        exit;
    }

    // Update session with fresh data
    $_SESSION['noble_lvl'] = $user['lvl'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['last_db_check'] = time();
}

/**
 * ✅ Secure role-checking function
 */
function require_role(array $allowed_roles): bool {
    if (!isset($_SESSION['noble_lvl']) || !in_array($_SESSION['noble_lvl'], $allowed_roles)) {
        $_SESSION['access_denied'] = "You don't have permission to access this section.";
        header("Location: ../../loginpage/index.php");
        exit;
    }
    return true;
}

/**
 * ✅ CSRF Protection Functions
 */
function get_csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

function generate_csrf_field(): string {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token(string $token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf_token($token)) {
        $_SESSION['access_denied'] = "Invalid security token. Please try again.";
        $redirect = $_SERVER['HTTP_REFERER'] ?? "../../loginpage/index.php";
        header("Location: " . $redirect);
        exit;
    }
}

// ✅ FIXED: Extended auto-logout using constant
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > INACTIVITY_LIMIT) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit;
}

// ✅ Update last activity timestamp
$_SESSION['last_activity'] = time();

/**
 * ✅ Helper functions
 */
function csrf_token_js(): string {
    return json_encode(get_csrf_token());
}

// ✅ Add session extension function for AJAX calls
function extend_session(): void {
    if (isset($_SESSION['noble_user'])) {
        $_SESSION['last_activity'] = time();
        echo json_encode(['status' => 'success', 'message' => 'Session extended']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No active session']);
    }
}

// ✅ Function to check session status via AJAX
function check_session_status(): array {
    if (!isset($_SESSION['noble_user'])) {
        return ['valid' => false, 'message' => 'No session'];
    }
    
    $time_left = INACTIVITY_LIMIT - (time() - ($_SESSION['last_activity'] ?? time()));
    
    return [
        'valid' => true,
        'time_left' => $time_left,
        'expires_in' => date('Y-m-d H:i:s', time() + $time_left)
    ];
}

// ✅ Get current session timeout limit (helper function)
function get_inactivity_limit(): int {
    return INACTIVITY_LIMIT;
}
?>