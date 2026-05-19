<?php

include ROOT_PATH . '/connection/connect.php';

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
  <title>Privacy Policy - NobleHome Depot</title>
  <link rel="stylesheet" href="https://cdn.tailwindcss.com">
  <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

</head>

<body class="bg-gray-50 text-gray-800 leading-relaxed" style="font-family: 'Montserrat', sans-serif;">
  <?php include '../navbar/top.php'; ?>
  <div class="max-w-4xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Privacy Policy</h1>
      <button onclick="window.history.back()"
        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
        Back
      </button>
    </div>


    <p class="mb-6">At NobleHome Depot, we value your privacy and are committed to protecting your personal information. This Privacy Policy explains what data we collect, how we use it, and how it is protected.</p>

    <h2 class="text-xl font-semibold mt-6 mb-2">1. Information We Collect</h2>
    <ul class="list-disc ml-6 mb-4">
      <li>Name, email address, phone number, and delivery address.</li>
      <li>Login credentials (email/password) or Google login information.</li>
      <li>Payment details (bank transfer reference, account name).</li>
      <li>Chat interactions via our support chatbot.</li>
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-2">2. How We Use Your Data</h2>
    <ul class="list-disc ml-6 mb-4">
      <li>To process and deliver your orders.</li>
      <li>To verify your account and provide secure access.</li>
      <li>To improve our services through website analytics.</li>
      <li>To provide customer support via chatbot or email.</li>
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-2">3. Data Sharing</h2>
    <p>We do not sell your personal data. We only share it with trusted third parties such as logistics providers (for delivery) and payment processors (for bank transfer verification).</p>

    <h2 class="text-xl font-semibold mt-6 mb-2">4. Data Security</h2>
    <p>Your account and personal data are protected by secure encryption, restricted access, and verification systems. NobleHome Depot takes reasonable steps to safeguard your data against unauthorized access or misuse.</p>

    <h2 class="text-xl font-semibold mt-6 mb-2">5. User Rights</h2>
    <ul class="list-disc ml-6 mb-4">
      <li>You can request to access or update your account information anytime.</li>
      <li>You may request account termination or deletion by contacting support.</li>
      <li>You have the right to know how your data is used and stored.</li>
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-2">6. Cookies</h2>
    <p>We use cookies to improve browsing, remember preferences, and analyze site traffic. You may manage or disable cookies in your browser settings. By using our site, you consent to our cookies policy.</p>

    <h2 class="text-xl font-semibold mt-6 mb-2">7. Updates to Policy</h2>
    <p>This Privacy Policy may be updated periodically. We encourage you to review it regularly. Last updated: <?= date("F j, Y"); ?></p>

    <h2 class="text-xl font-semibold mt-6 mb-2">8. Contact Us</h2>
    <p>If you have privacy concerns, please contact us through our <a href="contact.php" class="text-orange-500 underline">Contact Page</a> or chatbot support.</p>
  </div>

  <!-- Footer -->
  <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>
</body>

</html>