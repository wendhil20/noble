<?php
session_name("nobleadmin");
session_start();
require_once '../../connection/connect.php';
require_once '../role/roleaccount.php';

header('Content-Type: application/json');
require_role(['superadmin','hr']); // only superadmin allowed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$account_id || !in_array($action, ['set_head','remove_head','update_subrole'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Check is_head column exists
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM `nobleaccount` LIKE 'is_head'");
if (!$col_check || mysqli_num_rows($col_check) === 0) {
    echo json_encode(['success' => false, 'message' => "DB missing column `is_head`. Run: ALTER TABLE nobleaccount ADD COLUMN is_head TINYINT(1) NOT NULL DEFAULT 0;"]);
    exit;
}

// Get account row to know department
$stmt = $conn->prepare("SELECT lvl FROM nobleaccount WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$stmt->bind_result($lvl);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit;
}
$stmt->close();

$lvl = strtolower($lvl);

// Prevent superadmin assignment
if ($lvl === 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Cannot assign/remove head for superadmin.']);
    exit;
}

if ($action === 'set_head') {
    // Clear existing head(s) for this department
    $stmt = $conn->prepare("UPDATE nobleaccount SET is_head = 0 WHERE lvl = ?");
    $stmt->bind_param("s", $lvl);
    $stmt->execute();
    $stmt->close();

    // Set selected account as head
    $stmt = $conn->prepare("UPDATE nobleaccount SET is_head = 1 WHERE id = ?");
    $stmt->bind_param("i", $account_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Head assigned successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to assign head.']);
    }
    exit;
}

if ($action === 'remove_head') {
    $stmt = $conn->prepare("UPDATE nobleaccount SET is_head = 0 WHERE id = ?");
    $stmt->bind_param("i", $account_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Head removed successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove head.']);
    }
    exit;
}

if ($action === 'update_subrole') {
    $subrole = isset($_POST['subrole']) ? trim($_POST['subrole']) : '';
    
    $stmt = $conn->prepare("UPDATE nobleaccount SET subrole = ? WHERE id = ?");
    $stmt->bind_param("si", $subrole, $account_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Subrole updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update subrole.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unhandled action.']);
exit;
