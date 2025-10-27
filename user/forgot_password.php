<?php
// forgot_password.php - Main forgot password page with PHPMailer
session_name("nobleuser");
session_start();
include '../connection/connect.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Adjust path to your PHPMailer installation

// Function to generate random token
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

// Function to send reset email using PHPMailer
function sendResetEmail($email, $token, $name) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Change to your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'wendhil10@gmail.com';
        $mail->Password   = 'tnjqjsuopqlwzoug';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Noble System');
        $mail->addAddress($email, $name);

        // Content https://noblehomedepot.com/user/
        $reset_link = "http://localhost/noble/user/reset_password.php?token=" . $token;
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Noble System';
        $mail->Body    = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f97316; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { 
                    display: inline-block; 
                    padding: 12px 24px; 
                    background-color: #f97316; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 20px 0;
                }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p>We received a request to reset your password for your Noble account.</p>
                    <p>Click the button below to reset your password:</p>
                    <p style='text-align: center;'>
                        <a href='" . $reset_link . "' class='button'>Reset Password</a>
                    </p>
                    <p><strong>Note:</strong> This link will expire in 1 hour for security reasons.</p>
                    <p>If you didn't request this password reset, please ignore this email.</p>
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background-color: #f0f0f0; padding: 10px; border-radius: 3px;'>
                        " . $reset_link . "
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from Noble System. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "
        Hi $name,
        
        We received a request to reset your password for your Noble account.
        
        Please click the following link to reset your password:
        $reset_link
        
        Note: This link will expire in 1 hour for security reasons.
        
        If you didn't request this password reset, please ignore this email.
        
        Thank you,
        Noble System Team
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_or_mobile = trim($_POST['email_or_mobile'] ?? '');
    
    if (empty($email_or_mobile)) {
        $error = "Please enter your email or mobile number.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR mobile = ?");
        $stmt->bind_param("ss", $email_or_mobile, $email_or_mobile);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check login method
            if ($user['login_method'] == 'google') {
                $error = "This account was created with Google. Please login using Google.";
            } else {
                // Generate reset token
                $reset_token = generateResetToken();
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                
                // Save token to database
                $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $stmt->bind_param("ssi", $reset_token, $expires_at, $user['id']);
                
                if ($stmt->execute()) {
                    // Send reset email/SMS
                    if (filter_var($email_or_mobile, FILTER_VALIDATE_EMAIL)) {
                        if (sendResetEmail($user['email'], $reset_token, $user['name'])) {
                            $message = "Password reset link has been sent to your email. Please check your inbox.";
                        } else {
                            $error = "Failed to send reset email. Please try again later.";
                        }
                    } else {
                        // For mobile - you can implement SMS sending here
                        $_SESSION['reset_token'] = $reset_token;
                        $_SESSION['reset_mobile'] = $user['mobile'];
                        $message = "Password reset code has been generated. Please contact support for assistance.";
                    }
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        } else {
            // Security: Don't reveal if email exists or not
            $message = "If an account with that email/mobile exists, a password reset link has been sent.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Noble</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-md">
        <!-- Header -->
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Forgot Password</h2>
            <p class="text-gray-600">Enter your email or mobile number to reset your password</p>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                <div class="flex">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                <div class="flex">
                    <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Forgot Password Form -->
        <form method="POST" class="space-y-6">
            <div>
                <label for="email_or_mobile" class="block text-sm font-medium text-gray-700">
                    Email or Mobile Number
                </label>
                <input 
                    type="text" 
                    id="email_or_mobile" 
                    name="email_or_mobile" 
                    required 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                    placeholder="Enter your email or mobile number"
                    value="<?= htmlspecialchars($_POST['email_or_mobile'] ?? '') ?>"
                >
            </div>

            <button 
                type="submit" 
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-500 hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
            >
                Send Reset Link
            </button>
        </form>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="otherpage/index-page-1-A-B-C-D-E.php" class="text-sm text-orange-500 hover:underline">
                ← Back to Login
            </a>
        </div>

        <!-- Help Text -->
        <div class="text-center text-xs text-gray-500">
            <p>Having trouble? <a href="mailto:support@noble.com" class="text-orange-500 hover:underline">Contact Support</a></p>
        </div>
    </div>
</body>
</html>