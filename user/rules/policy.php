<?php
//policy
include ROOT_PATH . '/connection/connect.php';

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];
  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_mobile'] = $user['mobile'] ?? '';
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
  <title>Privacy Policy - NobleHome Depot</title>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen" style="font-family: 'Montserrat', sans-serif;">

  <?php include ROOT_PATH . '/user/navbar/top.php'; ?>

  <!-- Hero -->
  <div class="bg-black border-b-4 border-orange-500">
    <div class="max-w-5xl mx-auto px-6 py-12 text-center">
      <p class="text-orange-500 text-sm font-semibold uppercase tracking-widest mb-3">Legal</p>
      <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Privacy <span class="text-orange-500">Policy</span></h1>
      <div class="w-16 h-1 bg-orange-500 mx-auto mb-5"></div>
      <p class="text-gray-400 text-base max-w-xl mx-auto">
        We value your privacy. Here's exactly what data we collect, how we use it, and how it's protected.
      </p>
    </div>
  </div>

  <div class="max-w-4xl mx-auto px-6 py-12">

    <!-- Back button -->
    <div class="flex items-center justify-between mb-8">
      <button onclick="window.history.back()"
        class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back
      </button>
      <p class="text-xs text-gray-400">Last updated: <?= date("F j, Y") ?></p>
    </div>

    <!-- Intro -->
    <div class="bg-orange-50 border border-orange-200 rounded-xl p-6 mb-8 flex items-start gap-4">
      <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>
      <p class="text-gray-700 text-sm leading-relaxed">
        At <strong>NobleHome Depot</strong>, we value your privacy and are committed to protecting your personal information. This Privacy Policy explains what data we collect, how we use it, and how it is protected.
      </p>
    </div>

    <!-- Sections -->
    <div class="space-y-4">

      <?php
      $sections = [
        [
          'num' => '1',
          'title' => 'Information We Collect',
          'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
          'items' => [
            'Name, email address, phone number, and delivery address.',
            'Login credentials (email/password) or Google login information.',
            'Payment details (bank transfer reference, account name).',
            'Chat interactions via our support chatbot.',
          ],
        ],
        [
          'num' => '2',
          'title' => 'How We Use Your Data',
          'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
          'items' => [
            'To process and deliver your orders.',
            'To verify your account and provide secure access.',
            'To improve our services through website analytics.',
            'To provide customer support via chatbot or email.',
          ],
        ],
        [
          'num' => '3',
          'title' => 'Data Sharing',
          'icon' => 'M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z',
          'text' => 'We do not sell your personal data. We only share it with trusted third parties such as <strong>logistics providers</strong> (for delivery) and <strong>payment processors</strong> (for bank transfer verification).',
        ],
        [
          'num' => '4',
          'title' => 'Data Security',
          'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
          'text' => 'Your account and personal data are protected by secure encryption, restricted access, and verification systems. NobleHome Depot takes reasonable steps to safeguard your data against unauthorized access or misuse.',
        ],
        [
          'num' => '5',
          'title' => 'Your Rights',
          'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
          'items' => [
            'You can request to access or update your account information anytime.',
            'You may request account termination or deletion by contacting support.',
            'You have the right to know how your data is used and stored.',
          ],
        ],
        [
          'num' => '6',
          'title' => 'Cookies',
          'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
          'text' => 'We use cookies to improve browsing, remember preferences, and analyze site traffic. You may manage or disable cookies in your browser settings. By using our site, you consent to our cookies policy.',
        ],
        [
          'num' => '7',
          'title' => 'Updates to This Policy',
          'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
          'text' => 'This Privacy Policy may be updated periodically. We encourage you to review it regularly.',
        ],
        [
          'num' => '8',
          'title' => 'Contact Us',
          'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
          'text' => 'If you have privacy concerns, please contact us through our <a href="contact.php" class="text-orange-500 hover:underline font-medium">Contact Page</a> or chatbot support.',
        ],
      ];
      ?>

      <?php foreach ($sections as $s): ?>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-4 border-b border-gray-100">
          <span class="w-8 h-8 rounded-full bg-orange-500 text-white text-sm font-bold flex items-center justify-center shrink-0">
            <?= $s['num'] ?>
          </span>
          <h2 class="font-bold text-gray-900"><?= $s['title'] ?></h2>
        </div>
        <div class="px-6 py-5">
          <?php if (isset($s['items'])): ?>
            <ul class="space-y-2">
              <?php foreach ($s['items'] as $item): ?>
              <li class="flex items-start gap-2.5 text-sm text-gray-600">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 mt-2 shrink-0"></span>
                <span><?= $item ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-sm text-gray-600 leading-relaxed"><?= $s['text'] ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

    </div>
  </div>

  <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>
</body>
</html>