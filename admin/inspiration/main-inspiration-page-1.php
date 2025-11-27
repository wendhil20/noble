<?php
//adminshop.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['productspecialist', 'superadmin']);

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
  // Redirect to login page
  header("Location: ../../loginpage/index.php");
  exit();
}

?>
