<?php
// check_session.php  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

define('INACTIVITY_LIMIT', 86400); // 24 hours - should match your main session file

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['valid' => false, 'message' => 'No session']);
    exit;
}

$time_left = INACTIVITY_LIMIT - (time() - ($_SESSION['last_activity'] ?? time()));

if ($time_left <= 0) {
    session_unset();
    session_destroy();
    echo json_encode(['valid' => false, 'message' => 'Session expired']);
    exit;
}

echo json_encode([
    'valid' => true,
    'time_left' => $time_left,
    'expires_in' => date('Y-m-d H:i:s', time() + $time_left)
]);
?>