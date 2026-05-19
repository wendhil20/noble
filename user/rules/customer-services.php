<?php
// customerservices
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
  <title>Help Center - NobleHome Depot</title>
  <style>
    body { font-family: 'Montserrat', sans-serif; }
    .faq-answer { display: none; }
    .faq-answer.open { display: block; }
  </style>
  <script>
    function toggleFAQ(id) {
      const el = document.getElementById(id);
      const icon = document.getElementById(id + '-icon');
      const isOpen = el.classList.contains('open');
      // Close all
      document.querySelectorAll('.faq-answer').forEach(a => a.classList.remove('open'));
      document.querySelectorAll('.faq-icon').forEach(i => { i.textContent = '+'; });
      // Open clicked (unless it was already open)
      if (!isOpen) {
        el.classList.add('open');
        icon.textContent = '−';
      }
    }
  </script>
</head>

<body class="bg-gray-50 text-gray-800 min-h-screen" style="font-family: 'Montserrat', sans-serif;">

  <?php include ROOT_PATH . '/user/navbar/top.php'; ?>

  <!-- Hero -->
  <div class="bg-black border-b-4 border-orange-500">
    <div class="max-w-5xl mx-auto px-6 py-12 text-center">
      <p class="text-orange-500 text-sm font-semibold uppercase tracking-widest mb-3">Support</p>
      <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Help Center & <span class="text-orange-500">Customer Service</span></h1>
      <div class="w-16 h-1 bg-orange-500 mx-auto mb-5"></div>
      <p class="text-gray-400 text-base max-w-xl mx-auto">
        Find answers, contact our team, or explore our step-by-step guides.
      </p>
    </div>
  </div>

  <div class="max-w-5xl mx-auto px-6 py-12 space-y-16">

    <!-- Contact Options -->
    <section>
      <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">
        How Can We <span class="text-orange-500">Help You?</span>
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- Chatbot -->
        <div class="bg-orange-500 rounded-xl p-6 text-white text-center shadow-sm">
          <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold mb-1">Live Chatbot</h3>
          <p class="text-white/80 text-sm mb-3">Get instant help 24/7</p>
          <span class="inline-block bg-white/20 text-white text-xs font-semibold px-3 py-1 rounded-full">Available Now</span>
        </div>

        <!-- Email -->
        <div class="bg-white border border-gray-200 rounded-xl p-6 text-center shadow-sm">
          <div class="w-14 h-14 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-orange-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-1">Email Support</h3>
          <p class="text-gray-500 text-sm mb-3">Send detailed inquiries</p>
          <p class="text-orange-500 font-semibold text-sm">noblehomeconst.ph@gmail.com</p>
        </div>

        <!-- Phone -->
        <div class="bg-white border border-gray-200 rounded-xl p-6 text-center shadow-sm">
          <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
              <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-1">Call Us</h3>
          <p class="text-gray-500 text-sm mb-3">Speak with our team</p>
          <p class="font-semibold text-gray-800 text-sm">09922394563</p>
          <p class="font-semibold text-gray-800 text-sm">(02) 8822-1295</p>
        </div>

      </div>
    </section>

    <!-- FAQ -->
    <section>
      <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">
        Frequently Asked <span class="text-orange-500">Questions</span>
      </h2>

      <?php
      $faqs = [
        ['q' => 'How do I place an order for furniture?',
         'a' => 'Browse our furniture catalog, select items, add to cart, provide your delivery address, and complete payment via bank transfer to our official account. Once payment is verified, we\'ll schedule your delivery.'],
        ['q' => 'What payment methods do you accept?',
         'a' => 'We only accept bank transfers to our official NobleHome Depot account. Cash on delivery (COD) is not available. Payment must be confirmed before delivery scheduling.'],
        ['q' => 'What if my furniture arrives damaged?',
         'a' => 'If your furniture arrives damaged or defective, we will provide a replacement at no extra cost and reschedule delivery. We do not offer refunds, only replacements.'],
        ['q' => 'How do I create an account?',
         'a' => 'You can register using your email address and password, or sign in directly through Google for faster access. Your account is secure and protected with encryption.'],
        ['q' => 'Do you sell imported furniture from China?',
         'a' => 'Yes, some of our furniture products are imported from China. Delivery times may vary for imported items, but we ensure quality and proper handling through NobleHome Delivery Services.'],
        ['q' => 'Can my account be suspended or banned?',
         'a' => 'Yes, accounts may be terminated if fraudulent or abusive activity is detected. We reserve the right to verify, suspend, or ban accounts that violate our terms.'],
        ['q' => 'What is your return and refund policy?',
         'a' => 'Products must be returned in original condition within 7 days of receipt. Damaged items are replaced at no cost. For more details, check our Return Policy page.'],
        ['q' => 'How long does delivery take?',
         'a' => 'Local items typically arrive within 3–7 business days. Imported items may take 2–4 weeks. You\'ll receive tracking information once your order ships.'],
      ];
      ?>

      <div class="space-y-3">
        <?php foreach ($faqs as $i => $faq): $id = 'faq' . ($i + 1); ?>
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
          <button onclick="toggleFAQ('<?= $id ?>')"
                  class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition-colors">
            <span class="font-semibold text-gray-900 text-sm pr-4"><?= $faq['q'] ?></span>
            <span id="<?= $id ?>-icon" class="faq-icon text-orange-500 text-xl font-bold shrink-0">+</span>
          </button>
          <div id="<?= $id ?>" class="faq-answer px-6 pb-5 border-t border-gray-100">
            <p class="text-gray-600 text-sm leading-relaxed pt-4"><?= $faq['a'] ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Step-by-step Guides -->
    <section>
      <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">
        Step-by-Step <span class="text-orange-500">Guides</span>
      </h2>

      <?php
      $guides = [
        [
          'num' => '1', 'color' => 'blue',
          'title' => 'How to Order',
          'steps' => ['Browse furniture catalog', 'Add items to your cart', 'Enter delivery address', 'Choose payment method', 'Wait for delivery confirmation'],
        ],
        [
          'num' => '2', 'color' => 'green',
          'title' => 'Payment Process',
          'steps' => ['Verify your order details', 'Transfer the exact amount', 'Upload payment screenshot', 'Include account name used', 'Wait for verification', 'Delivery gets scheduled'],
        ],
        [
          'num' => '3', 'color' => 'purple',
          'title' => 'Account Setup',
          'steps' => ['Click Register / Sign Up', 'Use email or Google login', 'Fill required information', 'Verify your account', 'Add delivery address', 'Start shopping furniture'],
        ],
        [
          'num' => '4', 'color' => 'red',
          'title' => 'Track Your Order',
          'steps' => ['Log in to your account', 'Go to "My Orders"', 'View order status', 'Get tracking number', 'Check delivery updates', 'Contact support if needed'],
        ],
        [
          'num' => '5', 'color' => 'pink',
          'title' => 'Update Profile',
          'steps' => ['Log in to your account', 'Click "My Account" or Profile', 'Verify your account', 'Update delivery address'],
        ],
      ];
      $bg = ['blue'=>'bg-blue-50','green'=>'bg-green-50','purple'=>'bg-purple-50','red'=>'bg-red-50','pink'=>'bg-pink-50'];
      ?>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($guides as $g): ?>
        <div class="<?= $bg[$g['color']] ?> border border-gray-200 rounded-xl p-6 shadow-sm">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0">
              <?= $g['num'] ?>
            </div>
            <h3 class="font-bold text-gray-900"><?= $g['title'] ?></h3>
          </div>
          <ol class="space-y-2">
            <?php foreach ($g['steps'] as $step): ?>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-orange-500 font-bold shrink-0 mt-0.5">›</span>
              <?= $step ?>
            </li>
            <?php endforeach; ?>
          </ol>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Important Info -->
    <section>
      <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="border-t-4 border-orange-500 px-6 py-5 border-b border-gray-100">
          <h2 class="text-lg font-bold text-gray-900">Important Information</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-0 divide-y md:divide-y-0 md:divide-x divide-gray-100">
          <div class="px-6 py-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <span class="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold">!</span>
              Important Notes
            </h3>
            <ul class="space-y-2.5 text-sm text-gray-600">
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> Delivery by NobleHome Delivery Services</li>
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> Some products are imported from China</li>
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> Replacement available for damaged items only</li>
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> Account security is our priority</li>
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> All prices include applicable taxes</li>
            </ul>
          </div>
          <div class="px-6 py-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <span class="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center text-white text-xs">⏱</span>
              Response Times
            </h3>
            <ul class="space-y-2.5 text-sm text-gray-600">
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> Chatbot: Instant response, 24/7</li>
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> Email support: Within 24 hours</li>
              <li class="flex items-start gap-2"><span class="text-orange-500 font-bold shrink-0">•</span> Damage reports: Same day response</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

  </div>

  <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>
</body>
</html>