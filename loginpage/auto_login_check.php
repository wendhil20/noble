<?php
// auto_login_check.php - Add this to the beginning of your login page (index.php)
session_name("nobleadmin");
session_start();

include '../connection/connect.php';

// ✅ Check for remember me cookies and auto-login
if (!isset($_SESSION['noble_user']) && isset($_COOKIE['noble_remember_token']) && isset($_COOKIE['noble_remember_email'])) {
    
    $remember_token = $_COOKIE['noble_remember_token'];
    $remember_email = $_COOKIE['noble_remember_email'];
    
    // Validate remember token
    $stmt = $conn->prepare("SELECT id, email, lvl, status, remember_token, remember_expires FROM nobleaccount WHERE email = ? AND remember_token = ? AND remember_expires > NOW() LIMIT 1");
    $stmt->bind_param("ss", $remember_email, $remember_token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Valid remember token - auto login
        if ($user['status'] === 'active') {
            
            // Update user activity
            $update_stmt = $conn->prepare("UPDATE nobleaccount SET last_activity = NOW(), is_online = 1, last_login = NOW() WHERE email = ?");
            $update_stmt->bind_param("s", $remember_email);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Set session data
            $_SESSION['noble_user'] = $user['email'];
            $_SESSION['noble_lvl'] = $user['lvl'];
            $_SESSION['noble_id'] = $user['id'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['last_db_check'] = time();
            $_SESSION['session_expires'] = time() + 86400;
            $_SESSION['is_online'] = true;
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['remember_me'] = true;
            
            // Determine redirect based on user level
            $redirect = match (strtolower($user['lvl'])) {
                'superadmin', 'admin' => "admin/client/dashboard",
                'sales' => "admin/orders/ordering",
                'accountant' => "admin/accountant/accountant",
                'supplier' => "admin/suppliermain/suppliercompany",
                'productspecialist' => "admin/shop/adminshop",
                'logistic' => "admin/logistic_management/logistics_dashboard",
                'warehouse' => "admin/warehouse_management/order_list",
                'hr' => "admin/hr/account",
                default => "admin/client/dashboard"
            };
            
            // Log auto-login
            error_log("Auto-login successful for user: " . $remember_email);
            
            // Redirect to appropriate dashboard
            header("Location: ../" . $redirect);
            exit();
        }
    } else {
        // Invalid or expired token - clear cookies
        setcookie('noble_remember_token', '', time() - 3600, '/');
        setcookie('noble_remember_email', '', time() - 3600, '/');
    }
    
    $stmt->close();
}

// ✅ Clear old sessions periodically (run once per day)
if (!isset($_SESSION['last_cleanup']) || (time() - $_SESSION['last_cleanup']) > 86400) {
    $cleanup = $conn->prepare("UPDATE nobleaccount SET is_online = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    $cleanup->execute();
    $cleanup->close();
    $_SESSION['last_cleanup'] = time();
}

$conn->close();
?>