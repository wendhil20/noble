<?php

include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

// Only allow POST with JSON
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$password = trim($input['password'] ?? '');

if (!isset($_SESSION['pending_password_email'])) {
    echo json_encode(['success' => false, 'message' => 'No pending login. Please try again.']);
    exit;
}

$email = $_SESSION['pending_password_email'];

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Account not found.']);
        exit;
    }

    $user = $result->fetch_assoc();

    if (empty($user['password'])) {
        echo json_encode(['success' => false, 'message' => 'No password is set for this account.']);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
        exit;
    }

    // ✅ Login success — set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_mobile'] = $user['mobile'];
    $_SESSION['login_success'] = "Welcome back, " . htmlspecialchars($user['name']) . "!";

    // Clean up
    unset($_SESSION['pending_password_email']);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'redirect' => 'otherpage/index-page-1-A-B-C-D-E.php'
    ]);

} catch (Exception $e) {
    error_log("Final Password Login Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>
