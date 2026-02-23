<?php
session_name("nobleadmin");
session_start();
require_once '../../connection/connect.php';
require_once '../role/roleaccount.php';

// ONLY ACCOUNTANT CAN UPDATE COMMISSION
require_role(['accountant']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $account_id = isset($_POST['account_id']) ? (int)$_POST['account_id'] : 0;
  $commission = isset($_POST['commission']) ? (float)$_POST['commission'] : 0;

  // Validate
  if ($account_id <= 0 || $commission < 0 || $commission > 100) {
    echo json_encode([
      'success' => false,
      'message' => 'Invalid input data'
    ]);
    exit;
  }

  // Check if member exists AND is in sales department
  $check_q = "SELECT id, lvl FROM nobleaccount WHERE id = ? AND lvl = 'sales'";
  $stmt = mysqli_prepare($conn, $check_q);
  mysqli_stmt_bind_param($stmt, 'i', $account_id);
  mysqli_stmt_execute($stmt);
  $check_res = mysqli_stmt_get_result($stmt);

  if (mysqli_num_rows($check_res) === 0) {
    echo json_encode([
      'success' => false,
      'message' => 'Sales member not found'
    ]);
    exit;
  }

  // Update commission
  $update_q = "UPDATE nobleaccount SET commission_rate = ? WHERE id = ? AND lvl = 'sales'";
  $stmt = mysqli_prepare($conn, $update_q);
  mysqli_stmt_bind_param($stmt, 'di', $commission, $account_id);
  
  if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
      'success' => true,
      'message' => 'Commission updated successfully',
      'commission' => number_format($commission, 2)
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Database error: ' . mysqli_error($conn)
    ]);
  }
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
  ]);
}
?>