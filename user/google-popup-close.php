<?php
session_name("nobleuser");
session_start();
$hasError = isset($_GET['error']);
?>
<!DOCTYPE html>
<html>
<head><title>Login Complete</title></head>
<body>
<p>Login successful! Closing...</p>
<script>
  // Try postMessage first (works even with COOP)
  try {
    window.opener.postMessage('google-login-success', '*');
  } catch(e) {}

  // Also try direct close after short delay
  setTimeout(function() {
    window.close();
  }, 500);
</script>
</body>
</html>