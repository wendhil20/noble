<?php
// index.php - Root redirect file
// This redirects noblehomedepot.com → noblehomedepot.com/noble/user/otherpage/index-page-1-A-B-C-D-E.php

// Build dynamic redirect URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Redirect to your actual homepage
$redirectUrl = $protocol . $host . '/noble/user/otherpage/index-page-1-A-B-C-D-E.php';

// Redirect
header('Location: ' . $redirectUrl);
exit;
?>