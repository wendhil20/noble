<?php
session_name("nobleadmin");
session_start();

include '../connection/connect.php';

try {
    // Debug: Log the session data before logout
    error_log("Logout attempt - Session user: " . ($_SESSION['noble_user'] ?? 'NOT SET'));
    error_log("Logout attempt - Session ID: " . ($_SESSION['noble_id'] ?? 'NOT SET'));

    if (isset($_SESSION['noble_user']) && !empty($_SESSION['noble_user'])) {
        // Set user as offline before destroying session
        $stmt = $conn->prepare("UPDATE nobleaccount SET is_online = 0, last_activity = NOW() WHERE email = ?");
        $stmt->bind_param("s", $_SESSION['noble_user']);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            error_log("Logout success - Updated $affectedRows row(s) for email: " . $_SESSION['noble_user']);
            
            if ($affectedRows === 0) {
                error_log("Warning: No rows were updated. Check if email exists: " . $_SESSION['noble_user']);
                
                // Try alternative approach using ID if available
                if (isset($_SESSION['noble_id']) && !empty($_SESSION['noble_id'])) {
                    $stmt2 = $conn->prepare("UPDATE nobleaccount SET is_online = 0, last_activity = NOW() WHERE id = ?");
                    $stmt2->bind_param("i", $_SESSION['noble_id']);
                    
                    if ($stmt2->execute()) {
                        $affectedRows2 = $stmt2->affected_rows;
                        error_log("Logout via ID - Updated $affectedRows2 row(s) for ID: " . $_SESSION['noble_id']);
                    } else {
                        error_log("Failed to update via ID: " . $stmt2->error);
                    }
                    $stmt2->close();
                }
            }
        } else {
            error_log("Database update failed: " . $stmt->error);
        }
        
        $stmt->close();
    } else {
        error_log("No session user found during logout");
    }

    // Store redirect info before clearing session
    $redirectPage = "index.php"; // Default redirect
    
    // Remove all session variables
    session_unset();
    
    // Destroy the session
    session_destroy();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Delete remember_token cookie if using "Remember Me"
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
        unset($_COOKIE['remember_token']);
    }
    
    // Clear any other auth-related cookies
    $authCookies = ['admin_token', 'noble_token', 'user_session'];
    foreach ($authCookies as $cookieName) {
        if (isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, '', time() - 3600, '/');
            unset($_COOKIE[$cookieName]);
        }
    }
    
    // Prevent caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    
    // Redirect to login page
    header("Location: " . $redirectPage);
    exit();

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    
    // Even if there's an error, try to clear the session
    session_unset();
    session_destroy();
    
    header("Location: index.php");
    exit();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>