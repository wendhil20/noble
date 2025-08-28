<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    // Set session lifetime to 10 hours
ini_set('session.gc_maxlifetime', 36000);
session_set_cookie_params(36000);
}

include '../../connection/connect.php';

// Redirect if session data is missing
if (!isset($_SESSION['noble_user']) || !isset($_SESSION['noble_lvl'])) {
    $_SESSION['access_denied'] = "You must login first.";
    header("Location: ../../loginpage/index.php");
    exit;
}

// ✅ IP-based session security
if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
} elseif ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
    // IP changed - potential session hijacking
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php");
    exit;
}

// ✅ Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ Regenerate session ID periodically (every 30 minutes)
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
    // Regenerate CSRF token as well
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$email = $_SESSION['noble_user']; // assuming noble_user = email

// ✅ Check if user still exists and fetch role from database
$stmt = $conn->prepare("SELECT id, lvl, status FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // ❌ Account no longer exists — force logout
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php");
    exit;
}

$user = $result->fetch_assoc();

// ✅ Optional: Check if account is deactivated (status != 'active')
if (strtolower($user['status']) !== 'active') {
    session_unset();
    session_destroy();
    $_SESSION['access_denied'] = "Account deactivated.";
    header("Location: ../../loginpage/index.php");
    exit;
}

// ✅ Optional: Sync session role with latest DB role
$_SESSION['noble_lvl'] = $user['lvl'];

/**
 * ✅ Secure role-checking function
 * @param array $allowed_roles Array of allowed user levels (e.g., ['admin', 'staff'])
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
        header("Location: " . $_SERVER['HTTP_REFERER'] ?? "../../loginpage/index.php");
        exit;
    }
}

// Auto-logout after 10 hours of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 36000) {
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// ✅ Helper function to safely output CSRF token in JavaScript
function csrf_token_js(): string {
    return json_encode(get_csrf_token());
}
?>