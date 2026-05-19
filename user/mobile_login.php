<?php
//mobile_login.php
include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $login = trim($input['login'] ?? '');
    $password = $input['password'] ?? '';
    $remember = $input['remember'] ?? false;

    // Validate input
    if (empty($login)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your mobile number or email']);
        exit;
    }

    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your password']);
        exit;
    }

    // Check if input is mobile number format
    $isMobile = preg_match('/^09\d{9}$/', $login);
    $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);

    if (!$isMobile && !$isEmail) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid mobile number (09xxxxxxxxx) or email']);
        exit;
    }

    // Search for user
    $stmt = $conn->prepare("SELECT * FROM users WHERE (email = ? OR mobile = ?)");
    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }

    $user = $result->fetch_assoc();

    // Check login method restrictions
    if ($user['login_method'] === 'google') {
        echo json_encode(['success' => false, 'message' => 'This account is registered with Google. Please use Google login.']);
        exit;
    }

    if ($user['login_method'] === 'otp') {
        echo json_encode(['success' => false, 'message' => 'This account requires OTP login. Please use the email login option.']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_mobile'] = $user['mobile'];
    // ✅ Assign referral code if user came from a referral link
    require_once '../includes/referral_tracker.php';
    assignReferralToUser($conn, $user['id']);

    // Handle remember me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie("remember_token", $token, time() + (30 * 24 * 60 * 60), "/", "", false, true);

        $update_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $update_stmt->bind_param("si", $token, $user['id']);
        $update_stmt->execute();
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Welcome, ' . htmlspecialchars($user['name']) . '!',
        'redirect' => 'index.php',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'mobile' => $user['mobile']
        ]
    ]);

} catch (Exception $e) {
    error_log("Mobile Login Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.',
        'debug_error' => $e->getMessage() // Remove in production
    ]);
}
?>