<?php
session_name("nobleadmin");
session_start();

include '../connection/connect.php';

try {
    if (isset($_SESSION['noble_user'])) {
        // Set user as offline before destroying session
        $stmt = $conn->prepare("UPDATE nobleaccount SET is_online = 0 WHERE email = ?");
        $stmt->bind_param("s", $_SESSION['noble_user']);
        $stmt->execute();
        $stmt->close();
    }

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

    // Optional: Delete remember_token cookie if using "Remember Me"
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // Redirect to login page
    header("Location: index.php"); // or use "../loginpage/index.php" if nasa loob ng ibang folder
    exit();

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    header("Location: index.php");
    exit();
} finally {
    if (isset($conn)) $conn->close();
}
?>