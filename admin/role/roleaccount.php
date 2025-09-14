<?php
//roleaccount.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../connection/connect.php';

// ✅ Define constants
define('INACTIVITY_LIMIT', 18000); // 5 hours (18000 seconds)
define('VALIDATION_INTERVAL', 300); // Check database every 5 minutes
define('REGENERATION_INTERVAL', 7200); // 2 hours

// Redirect if session data is missing
if (!isset($_SESSION['noble_user']) || !isset($_SESSION['noble_lvl'])) {
    $_SESSION['access_denied'] = "You must login first.";
    header("Location: ../../loginpage/index.php");
    exit;
}
// ✅ SET TIMEZONE FIRST - This fixes your logout issue!
date_default_timezone_set('Asia/Manila'); // Philippine timezone

// ✅ Also set MySQL timezone to match PHP
$conn->query("SET time_zone = '+08:00'");
/**
 * ✅ SESSION MANAGEMENT USING EXISTING DATABASE COLUMNS
 */

// Update user's last activity in database
function update_user_activity($conn, $email) {
    $stmt = $conn->prepare("UPDATE nobleaccount SET last_activity = NOW(), is_online = 1 WHERE email = ?");
    $stmt->bind_param("s", $email);
    return $stmt->execute();
}

// Check if user session is still valid based on database
function is_user_session_valid($conn, $email) {
    $stmt = $conn->prepare("
        SELECT id, lvl, status, last_activity, is_online 
        FROM nobleaccount 
        WHERE email = ? AND status = 'active' 
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return false;
    }

    $user = $result->fetch_assoc();
    
    // Check if last_activity is within our custom timeout
    if ($user['last_activity']) {
        $last_activity_timestamp = strtotime($user['last_activity']);
        $inactive_time = time() - $last_activity_timestamp;
        
        if ($inactive_time > INACTIVITY_LIMIT) {
            // Session expired - mark user as offline
            mark_user_offline($conn, $email);
            return false;
        }
    }
    
    return $user;
}

// Mark user as offline
function mark_user_offline($conn, $email) {
    $stmt = $conn->prepare("UPDATE nobleaccount SET is_online = 0 WHERE email = ?");
    $stmt->bind_param("s", $email);
    return $stmt->execute();
}

// Clean offline users (optional - for housekeeping)
function clean_offline_users($conn) {
    $expired_time = date('Y-m-d H:i:s', time() - INACTIVITY_LIMIT);
    $stmt = $conn->prepare("UPDATE nobleaccount SET is_online = 0 WHERE last_activity < ? AND is_online = 1");
    $stmt->bind_param("s", $expired_time);
    return $stmt->execute();
}

// ✅ MAIN SESSION VALIDATION
$email = $_SESSION['noble_user'];

// Check if user session is still valid in database
$user_data = is_user_session_valid($conn, $email);

if (!$user_data) {
    // Session expired or user not found/inactive
    session_unset();
    session_destroy();
    $_SESSION['access_denied'] = "Your session has expired. Please login again.";
    header("Location: ../../loginpage/index.php?timeout=true");
    exit;
}

// Update user's last activity
update_user_activity($conn, $email);

// Update session with fresh data
$_SESSION['noble_lvl'] = $user_data['lvl'];
$_SESSION['user_id'] = $user_data['id'];

// ✅ RELAXED IP-based session security (only for major IP changes)
if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
} elseif (isset($_SESSION['user_ip'])) {
    $current_ip = $_SERVER['REMOTE_ADDR'];
    $session_ip = $_SESSION['user_ip'];
    
    // Only check if IPs are completely different (not just subnet changes)
    $current_parts = explode('.', $current_ip);
    $session_parts = explode('.', $session_ip);
    
    // Only logout if first 2 octets are completely different (major IP change)
    if (count($current_parts) >= 2 && count($session_parts) >= 2) {
        if ($current_parts[0] !== $session_parts[0] && $current_parts[1] !== $session_parts[1]) {
            error_log("Major IP change detected for user: {$_SESSION['noble_user']} from {$session_ip} to {$current_ip}");
            
            mark_user_offline($conn, $email);
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

// ✅ Regenerate session ID less frequently
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > REGENERATION_INTERVAL) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// ✅ Clean offline users occasionally (1% chance per request)
if (rand(1, 100) === 1) {
    clean_offline_users($conn);
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

/**
 * ✅ Helper functions
 */
function csrf_token_js(): string {
    return json_encode(get_csrf_token());
}

// ✅ Session extension function for AJAX calls
function extend_session(): void {
    global $conn;
    if (isset($_SESSION['noble_user'])) {
        update_user_activity($conn, $_SESSION['noble_user']);
        echo json_encode(['status' => 'success', 'message' => 'Session extended']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No active session']);
    }
}

// ✅ Function to check session status via AJAX
function check_session_status(): array {
    global $conn;
    
    if (!isset($_SESSION['noble_user'])) {
        return ['valid' => false, 'message' => 'No session'];
    }
    
    $user_data = is_user_session_valid($conn, $_SESSION['noble_user']);
    
    if (!$user_data) {
        return ['valid' => false, 'message' => 'Session expired'];
    }
    
    $last_activity_timestamp = strtotime($user_data['last_activity']);
    $time_left = INACTIVITY_LIMIT - (time() - $last_activity_timestamp);
    
    return [
        'valid' => true,
        'time_left' => $time_left,
        'expires_in' => date('Y-m-d H:i:s', time() + $time_left),
        'hours_left' => round($time_left / 3600, 2),
        'last_activity' => $user_data['last_activity'],
        'is_online' => $user_data['is_online']
    ];
}

// ✅ Get current session timeout limit
function get_inactivity_limit(): int {
    return INACTIVITY_LIMIT;
}

// ✅ Manual logout function
function manual_logout(): void {
    global $conn;
    if (isset($_SESSION['noble_user'])) {
        mark_user_offline($conn, $_SESSION['noble_user']);
    }
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?logout=success");
    exit;
}

// ✅ Enhanced debug function
function debug_session_info(): array {
    global $conn;
    
    $db_user_data = null;
    if (isset($_SESSION['noble_user'])) {
        $db_user_data = is_user_session_valid($conn, $_SESSION['noble_user']);
    }
    
    return [
        'php_gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
        'php_cookie_lifetime' => ini_get('session.cookie_lifetime'),
        'custom_limit' => INACTIVITY_LIMIT,
        'custom_limit_hours' => INACTIVITY_LIMIT / 3600,
        'php_session_active' => isset($_SESSION['noble_user']),
        'db_user_valid' => $db_user_data !== false,
        'db_last_activity' => $db_user_data['last_activity'] ?? 'N/A',
        'db_is_online' => $db_user_data['is_online'] ?? 'N/A',
        'session_user' => $_SESSION['noble_user'] ?? 'N/A',
        'session_id' => session_id(),
        'time_now' => date('Y-m-d H:i:s'),
        'timeout_message' => 'Using database last_activity for ' . (INACTIVITY_LIMIT/3600) . ' hour timeout'
    ];
}

// ✅ Get active users count (bonus function)
function get_active_users_count($conn): int {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM nobleaccount WHERE is_online = 1 AND status = 'active'");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}

// ✅ Get list of online users (bonus function for admin)
function get_online_users($conn): array {
    $stmt = $conn->prepare("
        SELECT email, fullname, lvl, last_activity 
        FROM nobleaccount 
        WHERE is_online = 1 AND status = 'active' 
        ORDER BY last_activity DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>