<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";


header('Content-Type: application/json');
require_role(['superadmin','hr']); // only superadmin allowed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$account_id || !in_array($action, ['set_head','remove_head','update_subrole','update_commission','upload_signature'])) {
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

if ($action === 'update_commission') {
    // Only allow commission for sales department
    if ($lvl !== 'sales') {
        echo json_encode(['success' => false, 'message' => 'Commission can only be set for sales members.']);
        exit;
    }
    
    $commission = isset($_POST['commission']) ? floatval($_POST['commission']) : 0.00;
    
    // Validate commission range
    if ($commission < 0 || $commission > 100) {
        echo json_encode(['success' => false, 'message' => 'Commission must be between 0 and 100.']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE nobleaccount SET commission_rate = ? WHERE id = ?");
    $stmt->bind_param("di", $commission, $account_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Commission updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update commission.']);
    }
    exit;
}

if ($action === 'upload_signature') {
    if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
        exit;
    }
    
    $file = $_FILES['signature'];
    
    // Validate file size (2MB max)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB.']);
        exit;
    }
    
    // Validate file type
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PNG, JPG, and GIF allowed.']);
        exit;
    }
    
    // Read file as binary
    $signature_data = file_get_contents($file['tmp_name']);
    
    if ($signature_data === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to read file.']);
        exit;
    }
    
    // Save to database as BLOB
    $stmt = $conn->prepare("UPDATE nobleaccount SET e_signature = ? WHERE id = ?");
    $stmt->bind_param("bi", $signature_data, $account_id);
    $stmt->send_long_data(0, $signature_data);
    $ok = $stmt->execute();
    $stmt->close();
    
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Signature uploaded successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save signature.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unhandled action.']);
exit;
