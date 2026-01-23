<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token (email or mobile-based or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();

    // 🔐 Store essential user session info
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_mobile'] = $user['mobile'] ?? '';

    // 👤 Check if it's a Google account (optional)
    if (!empty($user['google_id'])) {
      $_SESSION['google_logged_in'] = true;
      $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
    }
  }

  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Policy - NobleHome Depot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'noble-orange': '#f97316',
                        'noble-orange-dark': '#ea580c',
                    }
                }
            }
        }
    </script>   
</head>
<body style="font-family: 'Montserrat', sans-serif;">
    <?php include '../navbar/top.php'; ?>

    <div class="max-w-5xl mx-auto px-6 py-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-6">Return Policy</h1>
        <p class="text-lg text-gray-700 leading-relaxed">
            If your furniture arrives damaged or defective, we will provide a replacement at no extra cost and reschedule delivery. We do not offer refunds, only replacements.
        </p>
    </div>

    <?php include '../navbar/footer.php'; ?>

</body>
</html>