<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redirecting...</title>
  <script>
    // Get the protocol and host dynamically
    const protocol = window.location.protocol; // http: or https:
    const host = window.location.host; // localhost or noblehomedepot.com
    
    // Build the redirect URL dynamically
    const redirectUrl = protocol + '//' + host + '/noble/user/otherpage/index-page-1-A-B-C-D-E.php';
    
    // Redirect immediately
    window.location.href = redirectUrl;
  </script>
</head>
<body>
  <p>Redirecting, please wait...</p>
</body>
</html>
