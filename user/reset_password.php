<?php
// reset_password.php - Password reset form with debugging
session_name("nobleuser");
session_start();
include '../connection/connect.php';

$token = $_GET['token'] ?? '';
$error = '';
$message = '';
$valid_token = false;
$debug_info = [];

// Debug: Check if token is provided
if (empty($token)) {
    $error = "No reset token provided in URL.";
    $debug_info[] = "Token from URL: empty";
} else {
    $debug_info[] = "Token from URL: " . substr($token, 0, 10) . "...";
    
    // Verify token exists in database
    $stmt = $conn->prepare("SELECT id, name, email, reset_token, reset_token_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $debug_info[] = "Token found in database for user: " . $user['email'];
        $debug_info[] = "Token expires at: " . $user['reset_token_expires'];
        $debug_info[] = "Current time: " . date('Y-m-d H:i:s');
        
        // Check if token is expired
        $current_time = date('Y-m-d H:i:s');
        if ($user['reset_token_expires'] > $current_time) {
            $valid_token = true;
            $debug_info[] = "Token is valid and not expired";
        } else {
            $error = "Reset token has expired. Please request a new password reset.";
            $debug_info[] = "Token has expired";
        }
    } else {
        $error = "Invalid reset token. Please request a new password reset.";
        $debug_info[] = "Token not found in database";
        
        // Check if any tokens exist at all
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE reset_token IS NOT NULL");
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_assoc()['count'];
        $debug_info[] = "Total users with reset tokens: " . $count;
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL, login_method = 'normal' WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user['id']);
        
        if ($stmt->execute()) {
            $message = "Password has been reset successfully!";
            $valid_token = false; // Disable form
            $debug_info[] = "Password updated successfully";
        } else {
            $error = "Failed to reset password. Please try again.";
            $debug_info[] = "Database error: " . $conn->error;
        }
    }
}

// Show debug info in development (remove in production)
$show_debug = false; // Set to false in production
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Noble</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-md">
        <!-- Header -->
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Reset Password</h2>
            <p class="text-gray-600">Enter your new password</p>
        </div>

        <!-- Debug Info (Remove in production) -->
        <?php if ($show_debug && !empty($debug_info)): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-md text-xs">
                <details>
                    <summary class="cursor-pointer font-semibold">Debug Information (Click to expand)</summary>
                    <ul class="mt-2 space-y-1">
                        <?php foreach ($debug_info as $info): ?>
                            <li>• <?= htmlspecialchars($info) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>
        <?php endif; ?>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                <div class="flex">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <?= htmlspecialchars($error) ?>
                        <div class="mt-2">
                            <a href="forgot_password.php" class="text-sm text-red-600 underline">Request New Reset Link</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                <div class="flex">
                    <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <?= htmlspecialchars($message) ?>
                        <div class="mt-3">
                            <a href="otherpage/index-page-1-A-B-C-D-E.php" class="text-sm text-green-600 underline font-semibold">Go to Login →</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reset Password Form -->
        <?php if ($valid_token && empty($message)): ?>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">
                        New Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required 
                        minlength="6"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                        placeholder="Enter new password (min. 6 characters)"
                    >
                    <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                        Confirm New Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        minlength="6"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                        placeholder="Confirm new password"
                    >
                    <p id="password_match_message" class="text-xs mt-1 hidden"></p>
                </div>

                <button 
                    type="submit" 
                    id="submit_btn"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-500 hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Reset Password
                </button>
            </form>
        <?php elseif (!$valid_token && empty($message)): ?>
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.502 0L5.232 15.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">Invalid or Expired Link</h3>
                <p class="mt-2 text-sm text-gray-600">The password reset link is invalid or has expired.</p>
                <div class="mt-6">
                    <a href="forgot_password.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-500 hover:bg-orange-600">
                        Request New Reset Link
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="otherpage/index-page-1-A-B-C-D-E.php" class="text-sm text-orange-500 hover:underline">
                ← Back to Login
            </a>
        </div>
    </div>

    <script>
        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const messageEl = document.getElementById('password_match_message');
        const submitBtn = document.getElementById('submit_btn');

        function checkPasswordMatch() {
            if (confirmPassword.value === '') {
                messageEl.className = 'text-xs mt-1 hidden';
                submitBtn.disabled = false;
                return;
            }

            if (newPassword.value === confirmPassword.value) {
                messageEl.className = 'text-xs mt-1 text-green-600';
                messageEl.textContent = '✓ Passwords match';
                confirmPassword.setCustomValidity('');
                submitBtn.disabled = false;
            } else {
                messageEl.className = 'text-xs mt-1 text-red-600';
                messageEl.textContent = '✗ Passwords do not match';
                confirmPassword.setCustomValidity('Passwords do not match');
                submitBtn.disabled = true;
            }
        }

        confirmPassword?.addEventListener('input', checkPasswordMatch);
        newPassword?.addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>