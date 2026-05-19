<?php
//term
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
  <title>Terms and Conditions - NobleHome Depot</title>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen" style="font-family: 'Montserrat', sans-serif;">

  <?php include ROOT_PATH . '/user/navbar/top.php'; ?>

  <!-- Hero -->
  <div class="bg-black border-b-4 border-orange-500">
    <div class="max-w-5xl mx-auto px-6 py-12 text-center">
      <p class="text-orange-500 text-sm font-semibold uppercase tracking-widest mb-3">Legal</p>
      <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Terms & <span class="text-orange-500">Conditions</span></h1>
      <div class="w-16 h-1 bg-orange-500 mx-auto mb-5"></div>
      <p class="text-gray-400 text-base max-w-xl mx-auto">
        By using NobleHome Depot, you agree to these terms. Please read them carefully.
      </p>
    </div>
  </div>

  <!-- Content -->
  <div class="max-w-4xl mx-auto px-6 py-12 space-y-6">

    <!-- Intro banner -->
    <div class="bg-orange-50 border border-orange-200 rounded-xl p-6 flex items-start gap-4">
      <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
      </div>
      <div>
        <h2 class="font-bold text-gray-900 text-lg mb-1">Welcome to NobleHome Depot</h2>
        <p class="text-gray-600 text-sm leading-relaxed">
          By accessing and using our website, you agree to the following Terms and Conditions. Please read them carefully before using our services.
        </p>
      </div>
    </div>

    <?php
    $sections = [
      [
        'num' => '1',
        'title' => 'General Information',
        'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'content' => 'This website is owned and operated by <strong>Noblehome Construction Corporation</strong>. By accessing or using this website — including browsing, purchasing, or engaging with our services — you agree to be bound by these Terms &amp; Conditions and our Privacy Policy.',
        'type' => 'text',
      ],
      [
        'num' => '2',
        'title' => 'Acceptance of Terms',
        'icon' => 'M5 13l4 4L19 7',
        'content' => 'By using this website, you confirm that you have read, understood, and accepted these Terms &amp; Conditions. If you do not agree, you must stop using our website and services immediately.',
        'type' => 'text',
      ],
      [
        'num' => '3',
        'title' => 'User Responsibilities',
        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'content' => [
          'Users agree to use this website in a lawful and responsible manner. You must <strong>not</strong>:',
          'Post, share, or transmit harmful, offensive, or fraudulent content.',
          'Attempt to hack, disrupt, or misuse the website.',
          'Copy, reproduce, or distribute website content without permission.',
          'Engage in spam, scams, or unauthorized promotions.',
        ],
        'note' => 'Violation of these rules may result in suspension or termination of your access.',
        'type' => 'list',
      ],
      [
        'num' => '4',
        'title' => 'Orders & Payments',
        'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'content' => [
          'Prices displayed include applicable taxes unless otherwise stated.',
          'Payments must be completed through the available payment channels at checkout.',
          'Shipping and delivery timelines are estimates and may vary depending on courier services.',
          'Refunds and returns are subject to our Return &amp; Refund Policy. Products must be returned in original condition within <strong>7 days</strong> upon receipt.',
        ],
        'type' => 'list',
      ],
      [
        'num' => '5',
        'title' => 'Limitations of Liability',
        'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z',
        'content' => [
          'NobleHome Depot shall not be liable for any indirect, incidental, or consequential damages arising from the use of this website or products purchased.',
          'We are not responsible for any delays, losses, or damages caused by third-party services (e.g., couriers, payment gateways).',
          'Product images may differ slightly from actual products due to variations in display and materials.',
        ],
        'type' => 'list',
      ],
      [
        'num' => '6',
        'title' => 'User Account Obligations',
        'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'content' => [
          'Provide accurate personal details during registration and checkout.',
          'Use the website responsibly and avoid abusive behavior in chats or reviews.',
          'Follow the guides provided on the site for using our services.',
        ],
        'type' => 'list',
      ],
      [
        'num' => '7',
        'title' => 'Force Majeure',
        'icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064',
        'content' => 'NobleHome Depot shall not be held liable for delays caused by unforeseen circumstances such as natural disasters, customs delays, or shipping issues from import sources.',
        'type' => 'text',
      ],
      [
        'num' => '8',
        'title' => 'Governing Law',
        'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
        'content' => 'These Terms &amp; Conditions shall be governed by and interpreted in accordance with the laws of the <strong>Republic of the Philippines</strong>. Any disputes shall be resolved under the exclusive jurisdiction of the courts in <strong>Quezon City, Philippines</strong>.',
        'type' => 'text',
      ],
    ];
    ?>

    <?php foreach ($sections as $s): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <!-- Section header -->
      <div class="flex items-center gap-4 px-6 py-4 border-b border-gray-100">
        <span class="w-8 h-8 rounded-full bg-orange-500 text-white text-sm font-bold flex items-center justify-center shrink-0">
          <?= $s['num'] ?>
        </span>
        <h2 class="font-bold text-gray-900 text-lg"><?= $s['title'] ?></h2>
      </div>
      <!-- Section body -->
      <div class="px-6 py-5">
        <?php if ($s['type'] === 'text'): ?>
          <p class="text-gray-600 text-sm leading-relaxed"><?= $s['content'] ?></p>
        <?php else: ?>
          <?php if (is_array($s['content'])): $items = $s['content']; $intro = null;
            if (!str_ends_with($items[0], '.') || str_contains($items[0], 'not')): $intro = array_shift($items); endif; ?>
            <?php if ($intro): ?><p class="text-gray-600 text-sm mb-3"><?= $intro ?></p><?php endif; ?>
            <ul class="space-y-2">
              <?php foreach ($items as $item): ?>
              <li class="flex items-start gap-2.5 text-sm text-gray-600">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 mt-2 shrink-0"></span>
                <span><?= $item ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php if (isset($s['note'])): ?>
            <p class="mt-3 text-xs text-orange-700 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
              <i class="fas fa-exclamation-circle mr-1"></i><?= $s['note'] ?>
            </p>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Contact CTA -->
    <div class="bg-black rounded-xl p-8 text-center">
      <div class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h2 class="text-xl font-bold text-white mb-2">Questions About Our Terms?</h2>
      <p class="text-gray-400 text-sm mb-6">We're here to help clarify anything you need.</p>
      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <a href="customer-services.php"
           class="inline-flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
          </svg>
          Contact Us
        </a>
        <div class="inline-flex items-center justify-center gap-2 bg-white/10 text-gray-300 px-6 py-2.5 rounded-lg text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
          </svg>
          Use our chatbot support
        </div>
      </div>
    </div>

  </div>

  <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>
</body>
</html>