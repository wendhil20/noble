<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

try {
    $baseUrl = BASE_URL;
    if (isset($_SESSION['noble_user']) && !empty($_SESSION['noble_user'])) {
        // ✅ CRITICAL FIX: Clear remember tokens in database
        $stmt = $conn->prepare("UPDATE nobleaccount SET is_online = 0, last_activity = NOW(), remember_token = NULL, remember_expires = NULL WHERE email = ?");
        $stmt->bind_param("s", $_SESSION['noble_user']);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            error_log("Logout success - Updated $affectedRows row(s) for email: " . $_SESSION['noble_user']);
            
            if ($affectedRows === 0) {
                error_log("Warning: No rows were updated. Check if email exists: " . $_SESSION['noble_user']);
                
                // Try alternative approach using ID if available
                if (isset($_SESSION['noble_id']) && !empty($_SESSION['noble_id'])) {
                    $stmt2 = $conn->prepare("UPDATE nobleaccount SET is_online = 0, last_activity = NOW(), remember_token = NULL, remember_expires = NULL WHERE id = ?");
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
    $redirectPage = BASE_URL . "/main"; // Default redirect
    
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
    
    // ✅ FIX: Clear the CORRECT remember token cookies
    if (isset($_COOKIE['noble_remember_token'])) {
        setcookie('noble_remember_token', '', time() - 3600, '/');
        unset($_COOKIE['noble_remember_token']);
        error_log("Cleared noble_remember_token cookie");
    }
    
    if (isset($_COOKIE['noble_remember_email'])) {
        setcookie('noble_remember_email', '', time() - 3600, '/');
        unset($_COOKIE['noble_remember_email']);
        error_log("Cleared noble_remember_email cookie");
    }
    
    // ✅ Clear other potential remember cookies
    $rememberCookies = ['remember_token', 'admin_token', 'noble_token', 'user_session'];
    foreach ($rememberCookies as $cookieName) {
        if (isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, '', time() - 3600, '/');
            unset($_COOKIE[$cookieName]);
            error_log("Cleared $cookieName cookie");
        }
    }
    
    // ✅ Enhanced logout page with truck loader animation using Tailwind CSS
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logging Out - Noble Admin</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% {
                    transform: translateY(0);
                }
                40% {
                    transform: translateY(-10px);
                }
                60% {
                    transform: translateY(-5px);
                }
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            @keyframes roadMove {
                from { background-position: 0px 0px; }
                to { background-position: 40px 0px; }
            }
            
            @keyframes lampPass {
                0% { transform: translateX(0px); opacity: 1; }
                50% { transform: translateX(-100px); opacity: 0.7; }
                100% { transform: translateX(-200px); opacity: 0; }
            }
            
            @keyframes progressFill {
                0% { width: 0%; }
                100% { width: 100%; }
            }
            
            .truck-bounce { animation: bounce 2s ease-in-out infinite; }
            .tire-spin { animation: spin 0.8s linear infinite; }
            .road-move { animation: roadMove 1s linear infinite; }
            .lamp-pass { animation: lampPass 3s ease-in-out infinite; }
            .progress-fill { animation: progressFill 2.5s ease-out forwards; }
        </style>
    </head>
    <body class=" min-h-screen overflow-hidden">
        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="fixed inset-0 flex items-center justify-center z-[9999] ">
            <!-- Truck Loader -->
            <div class="flex flex-col items-center gap-5">
                <!-- Logo -->
                <div class="mb-6">
                    <img src="' . $baseUrl . '/admin/img/logo/logo.png" alt="Noble Admin Logo" class="w-20 h-20 mx-auto drop-shadow-lg rounded-full bg-white bg-opacity-10 p-2">
                </div>
                
                <!-- Logout Messages -->
                <div class="text-center mb-8">
                    <h2 class="text-black text-2xl font-semibold mb-2 drop-shadow-lg">Logging Out...</h2>
                    <p class="text-black text-opacity-80 text-base">Clearing your session and securing your data</p>
                </div>
                
                <div class="truckWrapper w-48 h-24 flex flex-col relative items-center">
                    <div class="truckBody w-32 h-auto truck-bounce drop-shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 198 93" class="trucksvg w-full h-auto">
                            <path stroke-width="3" stroke="#282828" fill="#F83D3D"
                                d="M135 22.5H177.264C178.295 22.5 179.22 23.133 179.594 24.0939L192.33 56.8443C192.442 57.1332 192.5 57.4404 192.5 57.7504V89C192.5 90.3807 191.381 91.5 190 91.5H135C133.619 91.5 132.5 90.3807 132.5 89V25C132.5 23.6193 133.619 22.5 135 22.5Z">
                            </path>
                            <path stroke-width="3" stroke="#282828" fill="#7D7C7C"
                                d="M146 33.5H181.741C182.779 33.5 183.709 34.1415 184.078 35.112L190.538 52.112C191.16 53.748 189.951 55.5 188.201 55.5H146C144.619 55.5 143.5 54.3807 143.5 53V36C143.5 34.6193 144.619 33.5 146 33.5Z">
                            </path>
                            <path stroke-width="2" stroke="#282828" fill="#282828"
                                d="M150 65C150 65.39 149.763 65.8656 149.127 66.2893C148.499 66.7083 147.573 67 146.5 67C145.427 67 144.501 66.7083 143.873 66.2893C143.237 65.8656 143 65.39 143 65C143 64.61 143.237 64.1344 143.873 63.7107C144.501 63.2917 145.427 63 146.5 63C147.573 63 148.499 63.2917 149.127 63.7107C149.763 64.1344 150 64.61 150 65Z">
                            </path>
                            <rect stroke-width="2" stroke="#282828" fill="#FFFCAB" rx="1" height="7" width="5" y="63" x="187"></rect>
                            <rect stroke-width="2" stroke="#282828" fill="#282828" rx="1" height="11" width="4" y="81" x="193"></rect>
                            <rect stroke-width="3" stroke="#282828" fill="#DFDFDF" rx="2.5" height="90" width="121" y="1.5" x="6.5"></rect>
                            <rect stroke-width="2" stroke="#282828" fill="#DFDFDF" rx="2" height="4" width="6" y="84" x="1"></rect>
                        </svg>
                    </div>
                    
                    <div class="truckTires w-32 h-auto flex justify-between px-4 -mt-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 30 30" class="tiresvg w-6 h-6 tire-spin">
                            <circle stroke-width="3" stroke="#282828" fill="#282828" r="13.5" cy="15" cx="15"></circle>
                            <circle fill="#DFDFDF" r="7" cy="15" cx="15"></circle>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 30 30" class="tiresvg w-6 h-6 tire-spin">
                            <circle stroke-width="3" stroke="#282828" fill="#282828" r="13.5" cy="15" cx="15"></circle>
                            <circle fill="#DFDFDF" r="7" cy="15" cx="15"></circle>
                        </svg>
                    </div>
                    
                    <div class="road w-72 h-1.5 mt-5 rounded-sm road-move" style="background: repeating-linear-gradient(90deg, #333 0px, #333 20px, transparent 20px, transparent 40px);"></div>
                    
                    <svg xml:space="preserve" viewBox="0 0 453.459 453.459"
                        xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg"
                        version="1.1" class="lampPost w-3 h-12 fill-gray-600 absolute -right-12 top-5 lamp-pass">
                        <path d="M252.882,0c-37.781,0-68.686,29.953-70.245,67.358h-6.917v8.954c-26.109,2.163-45.463,10.011-45.463,19.366h9.993c-7.749,5.936-12.782,15.24-12.782,25.719c0,5.994,1.599,11.627,4.403,16.5c-3.896,5.029-6.234,11.375-6.234,18.26c0,16.223,13.149,29.372,29.372,29.372c16.223,0,29.372-13.149,29.372-29.372c0-6.885-2.338-13.231-6.234-18.26c2.804-4.873,4.403-10.506,4.403-16.5c0-10.479-5.033-19.783-12.782-25.719h9.993c0-9.355-19.354-17.203-45.463-19.366v-8.954h-6.917C184.196,29.953,215.101,0,252.882,0z"></path>
                    </svg>
                </div>
                
                <!-- Progress Bar -->
                <div class="w-48 h-1 bg-white bg-opacity-20 rounded-sm overflow-hidden mt-5">
                    <div class="h-full bg-gradient-to-r from-blue-400 to-cyan-400 rounded-sm progress-fill" style="width: 0%;"></div>
                </div>
                
                <!-- Status Text -->
                <div class="text-center mt-4">
                    <p class="text-black text-sm opacity-75">Please wait while we securely log you out...</p>
                </div>
            </div>
        </div>
        
        <script>
            // Clear all localStorage data
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem("noble_remembered_email");
                localStorage.clear();
                sessionStorage.clear();
            }
            
            // Clear any cached data
            if ("caches" in window) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) {
                        caches.delete(name);
                    });
                });
            }
            
            // Redirect after clearing with extended time for animation
            setTimeout(function() {
                window.location.href = "' . $redirectPage . '";
            }, 3000); // Increased to 3 seconds to see full animation
        </script>
    </body>
    </html>';
    
    exit();

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    
    // Even if there's an error, try to clear the session and tokens
    session_unset();
    session_destroy();
    
    // Force clear remember cookies even on error
    setcookie('noble_remember_token', '', time() - 3600, '/');
    setcookie('noble_remember_email', '', time() - 3600, '/');
    
    header("Location: " . BASE_URL . "/main");
    exit();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>