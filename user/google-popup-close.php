<?php
session_name("nobleuser");
session_start();
?>
<!DOCTYPE html>
<html>
<head><title>Login Success</title></head>
<body>
<script>
  if (window.opener && !window.opener.closed) {
    window.opener.location.reload();
  }
  window.close();
</script>
<p>Login successful! This window will close automatically...</p>
</body>
</html>