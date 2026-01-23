<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token
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
  <title>Help Center & Customer Service - NobleHome Depot</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Montserrat', sans-serif; }
    .glass-effect { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-slideDown { animation: slideDown 0.3s ease-out; }
  </style>
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

    function toggleFAQ(id) {
      const answer = document.getElementById(id);
      const icon = document.getElementById(id + '-icon');
      if (answer.classList.contains('hidden')) {
        answer.classList.remove('hidden');
        icon.innerHTML = '▲';
      } else {
        answer.classList.add('hidden');
        icon.innerHTML = '▼';
      }
    }

    function scrollToSection(sectionId) {
      const element = document.getElementById(sectionId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  </script>
</head>
<body class="bg-gray-50 text-gray-800">

<?php include '../navbar/top.php'; ?>

<!-- Header Section -->
<div class="bg-gradient-to-r from-black via-gray-900 to-black border-b-4 border-noble-orange py-16">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center">
      <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
        Help Center & <span class="text-noble-orange">Customer Service</span>
      </h1>
      <div class="w-24 h-1 bg-noble-orange mx-auto mb-6"></div>
      <p class="text-gray-300 text-lg max-w-2xl mx-auto">
        We're here to help. Find answers, contact our support team, or explore our guides.
      </p>
    </div>
  </div>
</div>

<!-- Quick Contact Options -->
<div id="quick-contact" class="max-w-6xl mx-auto px-6 py-12">
  <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">
    How Can We <span class="text-noble-orange">Help You?</span>
  </h2>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
    <!-- Live Chat -->
    <div class="bg-gradient-to-br from-noble-orange to-noble-orange-dark rounded-xl p-8 text-white text-center shadow-lg hover:shadow-2xl transition-shadow">
      <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
        </svg>
      </div>
      <h3 class="text-2xl font-bold mb-2">Live Chatbot</h3>
      <p class="text-white/90 mb-4">Get instant help 24/7</p>
      <div class="bg-white/20 px-4 py-2 rounded-lg inline-block font-semibold">Available Now</div>
    </div>

    <!-- Email Support -->
    <div class="bg-white border-2 border-noble-orange rounded-xl p-8 text-center shadow-lg hover:shadow-2xl transition-shadow">
      <div class="w-16 h-16 bg-noble-orange/10 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-noble-orange" fill="currentColor" viewBox="0 0 24 24">
          <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
        </svg>
      </div>
      <h3 class="text-2xl font-bold text-gray-900 mb-2">Email Support</h3>
      <p class="text-gray-600 mb-4">Send detailed inquiries</p>
      <p class="text-noble-orange font-semibold">noblehomeconst.ph@gmail.com</p>
    </div>

    <!-- Phone Support -->
    <div class="bg-white border-2 border-gray-200 rounded-xl p-8 text-center shadow-lg hover:shadow-2xl transition-shadow">
      <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-900" fill="currentColor" viewBox="0 0 24 24">
          <path d="M17.92 7.02C17.45 6.18 16.37 5.74 15.13 5.74c-1.24 0-2.32.44-2.79 1.28-1.49 2.41-2.2 4.66-2.2 7.98s.71 5.57 2.2 7.98c.47.84 1.55 1.28 2.79 1.28 1.24 0 2.32-.44 2.79-1.28.72-1.16 1.19-2.42 1.38-3.86h-2.4c-.15.97-.41 1.88-.84 2.67-.37.63-.98 1.01-1.63 1.01-.65 0-1.26-.38-1.63-1.01-1.13-1.84-1.71-3.93-1.71-6.39s.58-4.55 1.71-6.39c.37-.63.98-1.01 1.63-1.01.65 0 1.26.38 1.63 1.01.43.79.69 1.7.84 2.67h2.4c-.19-1.44-.66-2.7-1.38-3.86zM13 17.9v2.2h1.8V17.9h-1.8zm0-5.5v2.2h1.8v-2.2h-1.8z"/>
        </svg>
      </div>
      <h3 class="text-2xl font-bold text-gray-900 mb-2">Call Us</h3>
      <p class="text-gray-600 mb-4">Speak with our team</p>
      <div class="space-y-2">
        <p class="text-gray-900 font-semibold">09922394563</p>
        <p class="text-gray-900 font-semibold">(02) 8822-1295</p>
      </div>
    </div>
  </div>
</div>

<!-- FAQ Section -->
<div id="faq-section" class="bg-white py-12 mb-8">
  <div class="max-w-5xl mx-auto px-6">
    <h2 class="text-3xl font-bold text-gray-900 mb-12 text-center">
      Frequently Asked <span class="text-noble-orange">Questions</span>
    </h2>
    <div class="space-y-4">
      <!-- FAQ Item 1 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq1')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">How do I place an order for furniture?</h3>
            <span id="faq1-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq1" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>Browse our furniture catalog, select items, add to cart, provide your delivery address, and complete payment via bank transfer to our official account. Once payment is verified, we'll schedule your delivery.</p>
        </div>
      </div>

      <!-- FAQ Item 2 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq2')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">What payment methods do you accept?</h3>
            <span id="faq2-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq2" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>We only accept bank transfers to our official NobleHome Depot account. Cash on delivery (COD) is not available. Payment must be confirmed before delivery scheduling.</p>
        </div>
      </div>

      <!-- FAQ Item 3 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq3')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">What if my furniture arrives damaged?</h3>
            <span id="faq3-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq3" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>If your furniture arrives damaged or defective, we will provide a replacement at no extra cost and reschedule delivery. We do not offer refunds, only replacements.</p>
        </div>
      </div>

      <!-- FAQ Item 4 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq4')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">How do I create an account?</h3>
            <span id="faq4-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq4" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>You can register using your email address and password, or sign in directly through Google for faster access. Your account is secure and protected with encryption.</p>
        </div>
      </div>

      <!-- FAQ Item 5 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq5')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">Do you sell imported furniture from China?</h3>
            <span id="faq5-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq5" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>Yes, some of our furniture products are imported from China. Delivery times may vary for imported items, but we ensure quality and proper handling through NobleHome Delivery Services.</p>
        </div>
      </div>

      <!-- FAQ Item 6 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq6')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">Can my account be suspended or banned?</h3>
            <span id="faq6-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq6" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>Yes, accounts may be terminated if fraudulent or abusive activity is detected. We reserve the right to verify, suspend, or ban accounts that violate our terms.</p>
        </div>
      </div>

      <!-- FAQ Item 7 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq7')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">What is your return and refund policy?</h3>
            <span id="faq7-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq7" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>Products must be returned in original condition within 7 days of receipt. Damaged items are replaced at no cost. For more details, check our Return Policy page.</p>
        </div>
      </div>

      <!-- FAQ Item 8 -->
      <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
        <div class="bg-white p-6 cursor-pointer hover:bg-gray-50 transition" onclick="toggleFAQ('faq8')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">How long does delivery take?</h3>
            <span id="faq8-icon" class="text-noble-orange text-xl">▼</span>
          </div>
        </div>
        <div id="faq8" class="hidden px-6 pb-6 bg-gray-50 text-gray-700 border-t border-gray-200">
          <p>Delivery time depends on your location and product availability. Local items typically arrive within 3-7 business days. Imported items may take 2-4 weeks. You'll receive tracking information once your order ships.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Help Guides Section -->
<div id="guides-section" class="max-w-5xl mx-auto px-6 py-12">
  <h2 class="text-3xl font-bold text-gray-900 mb-12 text-center">
    Step-by-Step <span class="text-noble-orange">Guides</span>
  </h2>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Guide Card 1 -->
    <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-l-4  rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-noble-orange rounded-full flex items-center justify-center text-white font-bold mr-3">1</div>
        <h3 class="text-xl font-semibold text-gray-900">How to Order</h3>
      </div>
      <ol class="space-y-2 text-gray-700 text-sm">
        <li><span class="text-noble-orange font-bold">▶</span> Browse furniture catalog</li>
        <li><span class="text-noble-orange font-bold">▶</span> Add items to your cart</li>
        <li><span class="text-noble-orange font-bold">▶</span> Enter delivery address</li>
        <li><span class="text-noble-orange font-bold">▶</span> choose payment method</li>
        <li><span class="text-noble-orange font-bold">▶</span> Wait for delivery confirmation</li>
      </ol>
    </div>

    <!-- Guide Card 2 -->
    <div class="bg-gradient-to-br from-green-50 to-green-100 border-l-4  rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-noble-orange rounded-full flex items-center justify-center text-white font-bold mr-3">2</div>
        <h3 class="text-xl font-semibold text-gray-900">Payment Process</h3>
      </div>
      <ol class="space-y-2 text-gray-700 text-sm">
        <li><span class="text-noble-orange font-bold">▶</span> verifying order</li>
        <li><span class="text-noble-orange font-bold">▶</span> Transfer exact amount</li>
        <li><span class="text-noble-orange font-bold">▶</span> check payment</li>
        <li><span class="text-noble-orange font-bold">▶</span> Include account name used</li>
        <li><span class="text-noble-orange font-bold">▶</span> Wait for verification</li>
        <li><span class="text-noble-orange font-bold">▶</span> Delivery gets scheduled</li>
      </ol>
    </div>

    <!-- Guide Card 3 -->
    <div class="bg-gradient-to-br from-purple-50 to-purple-100 border-l-4  rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-noble-orange rounded-full flex items-center justify-center text-white font-bold mr-3">3</div>
        <h3 class="text-xl font-semibold text-gray-900">Account Setup</h3>
      </div>
      <ol class="space-y-2 text-gray-700 text-sm">
        <li><span class="text-noble-orange font-bold">▶</span> Click Register/Sign Up</li>
        <li><span class="text-noble-orange font-bold">▶</span> Use email or Google login</li>
        <li><span class="text-noble-orange font-bold">▶</span> Fill required information</li>
        <li><span class="text-noble-orange font-bold">▶</span> Verify your account</li>
        <li><span class="text-noble-orange font-bold">▶</span> Add delivery address</li>
        <li><span class="text-noble-orange font-bold">▶</span> Start shopping furniture</li>
      </ol>
    </div>

    <!-- Guide Card 4 -->
    <div class="bg-gradient-to-br from-red-50 to-red-100 border-l-4  rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-noble-orange rounded-full flex items-center justify-center text-white font-bold mr-3">4</div>
        <h3 class="text-xl font-semibold text-gray-900">Track Your Order</h3>
      </div>
      <ol class="space-y-2 text-gray-700 text-sm">
        <li><span class="text-noble-orange font-bold">▶</span> Log in to your account</li>
        <li><span class="text-noble-orange font-bold">▶</span> Go to "My Orders"</li>
        <li><span class="text-noble-orange font-bold">▶</span> View order status</li>
        <li><span class="text-noble-orange font-bold">▶</span> Get tracking number</li>
        <li><span class="text-noble-orange font-bold">▶</span> Check delivery updates</li>
        <li><span class="text-noble-orange font-bold">▶</span> Contact support if needed</li>
      </ol>
    </div>


    <!-- Guide Card 6 -->
    <div class="bg-gradient-to-br from-pink-50 to-pink-100 rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-noble-orange rounded-full flex items-center justify-center text-white font-bold mr-3">5</div>
        <h3 class="text-xl font-semibold text-gray-900">Update Profile</h3>
      </div>
      <ol class="space-y-2 text-gray-700 text-sm">
        <li><span class="text-noble-orange font-bold">▶</span> Log in to your account</li>
        <li><span class="text-noble-orange font-bold">▶</span> Click "My Account" or Profile</li>
        <li><span class="text-noble-orange font-bold">▶</span> Verified the account</li>
        <li><span class="text-noble-orange font-bold">▶</span> Update delivery address</li>
      </ol>
    </div>
  </div>
</div>

<!-- Important Information Box -->
<div id="important-info" class="bg-gray-100 py-12 mb-8">
  <div class="max-w-5xl mx-auto px-6">
    <div class="bg-white rounded-lg p-8 shadow-md border-t-4 border-noble-orange">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
          <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
            <span class="w-8 h-8 bg-noble-orange rounded-full flex items-center justify-center text-white mr-3 text-sm font-bold">!</span>
            Important Notes
          </h3>
          <ul class="space-y-3 text-gray-700">
            <li><span class="text-noble-orange font-bold">•</span> Delivery by NobleHome Delivery Services</li>
            <li><span class="text-noble-orange font-bold">•</span> Some products are imported from China</li>
            <li><span class="text-noble-orange font-bold">•</span> Replacement available for damaged items</li>
            <li><span class="text-noble-orange font-bold">•</span> Account security is our priority</li>
            <li><span class="text-noble-orange font-bold">•</span> All prices include applicable taxes</li>
          </ul>
        </div>
        <div>
          <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
            <span class="w-8 h-8 bg-noble-orange rounded-full flex items-center justify-center text-white mr-3 text-sm">⏱</span>
            Response Times
          </h3>
          <ul class="space-y-3 text-gray-700">
            <li><span class="text-noble-orange font-bold">•</span> Chatbot: Instant response</li>
            <li><span class="text-noble-orange font-bold">•</span> Email support: Within 24 hours</li>
            <li><span class="text-noble-orange font-bold">•</span> Damage reports: Same day response</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<?php include '../navbar/footer.php'; ?>

</body>
</html>