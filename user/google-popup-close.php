<?php
session_name("nobleuser");
session_start();
$hasError = isset($_GET['error']);
?>
<!DOCTYPE html>
<html>
<head><title>Login Complete</title></head>
<body>
<script>
  try {
    <?php if (!$hasError): ?>
      window.opener.postMessage('google-login-success', '*');
    <?php else: ?>
      window.opener.postMessage('google-login-error', '*');
    <?php endif; ?>
  } catch(e) {}

  setTimeout(function() {
    window.close();
  }, 500);
</script>
</body>
</html>