<?php
// extend_session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'No active session']);
    exit;
}

// Extend session
$_SESSION['last_activity'] = time();

echo json_encode(['status' => 'success', 'message' => 'Session extended']);
?>
