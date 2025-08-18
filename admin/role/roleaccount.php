<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../connection/connect.php';

// Redirect if session data is missing
if (!isset($_SESSION['noble_user']) || !isset($_SESSION['noble_lvl'])) {
    $_SESSION['access_denied'] = "You must login first.";
    header("Location: ../../loginpage/index.php");
    exit;
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
        $_SESSION['access_denied'] = "You don’t have permission to access this section.";
        return false;
    }
    return true;
}

