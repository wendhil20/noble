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
  <title>Support & Help - NobleHome Depot</title>
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

    // Toggle FAQ answers
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

    // Open chatbot (you can integrate your actual chatbot here)
    function openChatbot() {
      alert('Chatbot feature - integrate your actual chatbot system here');
    }

    // Submit support form
    function submitSupportForm() {
      const name = document.getElementById('support-name').value;
      const email = document.getElementById('support-email').value;
      const subject = document.getElementById('support-subject').value;
      const message = document.getElementById('support-message').value;

      if (!name || !email || !subject || !message) {
        alert('Please fill in all fields');
        return;
      }

      // Here you would integrate with your backend to submit the form
      alert('Support request submitted! We will get back to you within 24 hours.');
    }
  </script>
</head>

<body class=" text-white min-h-screen" style="font-family: 'Montserrat', sans-serif;">

  <?php include '../navbar/top.php'; ?>
  <!-- Header Section -->
  <div class="">
    <div class="max-w-6xl mx-auto px-6 py-8">
      <div class="text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-black mb-2">
          Support & <span class="text-noble-orange">Help Center</span>
        </h1>
        <div class="w-24 h-1 bg-noble-orange mx-auto mb-4"></div>
        <p class="text-black text-lg max-w-2xl mx-auto">
          Get the help you need. Find answers, contact support, or explore our furniture guides.
        </p>
      </div>
    </div>
  </div>

  <!-- Quick Contact Options -->
  <div class="max-w-6xl mx-auto px-6 py-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
      <!-- Live Chat -->
      <div class="bg-gradient-to-br from-noble-orange to-noble-orange-dark rounded-lg p-6 text-center shadow-xl hover:shadow-2xl transition-shadow">

        <h3 class="text-xl font-bold text-white mb-2">Live Chatbot</h3>
        <p class="text-white opacity-90 mb-4">Get instant help 24/7</p>
        <button onclick="openChatbot()" class="bg-white text-noble-orange px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
          Start Chat Now
        </button>
      </div>

      <!-- Email Support -->
      <div class="bg-gray-900 border border-gray-800 rounded-lg p-6 text-center shadow-xl hover:shadow-2xl transition-shadow">
        <h3 class="text-xl font-bold text-white mb-2">Email Support</h3>
        <p class="text-gray-300 mb-4">Send detailed inquiries</p>
        <a href="#contact-form" class="bg-noble-orange text-white px-6 py-2 rounded-lg font-semibold hover:bg-noble-orange-dark transition-colors">
          Contact Form
        </a>
      </div>

      <!-- Help Guides -->
      <div class="bg-gray-900 border border-gray-800 rounded-lg p-6 text-center shadow-xl hover:shadow-2xl transition-shadow">

        <h3 class="text-xl font-bold text-white mb-2">User Guides</h3>
        <p class="text-gray-300 mb-4">Step-by-step tutorials</p>
        <a href="#guides-section" class="bg-noble-orange text-white px-6 py-2 rounded-lg font-semibold hover:bg-noble-orange-dark transition-colors">
          View Guides
        </a>
      </div>
    </div>
  </div>

  <!-- FAQ Section -->
  <div id="faq-section" class="max-w-5xl mx-auto px-6 py-8">
    <h2 class="text-3xl font-bold text-black mb-8 text-center">
      Frequently Asked <span class="text-noble-orange">Questions</span>
    </h2>
    <div class="space-y-4">
      <!-- FAQ Item 1 -->
      <div class=" rounded-lg shadow-xl">
        <div class="p-6 cursor-pointer" onclick="toggleFAQ('faq1')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-black">How do I place an order for furniture?</h3>
            <span id="faq1-icon" class="text-noble-orange text-lg">▼</span>
          </div>
        </div>
        <div id="faq1" class="hidden px-6 pb-6">
          <p class="text-black">Browse our furniture catalog, select items, add to cart, provide your delivery address, and complete payment via bank transfer to our official account.</p>
        </div>
      </div>

      <!-- FAQ Item 2 -->
      <div class=" rounded-lg shadow-xl">
        <div class="p-6 cursor-pointer" onclick="toggleFAQ('faq2')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-black">What payment methods do you accept?</h3>
            <span id="faq2-icon" class="text-noble-orange text-lg">▼</span>
          </div>
        </div>
        <div id="faq2" class="hidden px-6 pb-6">
          <p class="text-black">We only accept bank transfers to our official NobleHome Depot account. Cash on delivery (COD) is not available. Payment must be confirmed before delivery scheduling.</p>
        </div>
      </div>

      <!-- FAQ Item 3 -->
      <div class="rounded-lg shadow-xl">
        <div class="p-6 cursor-pointer" onclick="toggleFAQ('faq3')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-black">What if my furniture arrives damaged?</h3>
            <span id="faq3-icon" class="text-noble-orange text-lg">▼</span>
          </div>
        </div>
        <div id="faq3" class="hidden px-6 pb-6">
          <p class="text-black">If your furniture arrives damaged or defective, we will provide a replacement at no extra cost and reschedule delivery. We do not offer refunds, only replacements.</p>
        </div>
      </div>

      <!-- FAQ Item 4 -->
      <div class="rounded-lg shadow-xl">
        <div class="p-6 cursor-pointer" onclick="toggleFAQ('faq4')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-black">How do I create an account?</h3>
            <span id="faq4-icon" class="text-noble-orange text-lg">▼</span>
          </div>
        </div>
        <div id="faq4" class="hidden px-6 pb-6">
          <p class="text-black">You can register using your email address and password, or sign in directly through Google redirect for faster access. Your account is secure and protected.</p>
        </div>
      </div>

      <!-- FAQ Item 5 -->
      <div class=" rounded-lg shadow-xl">
        <div class="p-6 cursor-pointer" onclick="toggleFAQ('faq5')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-black">Do you sell imported furniture from China?</h3>
            <span id="faq5-icon" class="text-noble-orange text-lg">▼</span>
          </div>
        </div>
        <div id="faq5" class="hidden px-6 pb-6">
          <p class="text-black">Yes, some of our furniture products are imported from China. Delivery times may vary for imported items, but we ensure quality and proper handling through NobleHome Delivery Services.</p>
        </div>
      </div>

      <!-- FAQ Item 6 -->
      <div class=" rounded-lg shadow-xl">
        <div class="p-6 cursor-pointer" onclick="toggleFAQ('faq6')">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-black">Can my account be suspended or banned?</h3>
            <span id="faq6-icon" class="text-noble-orange text-lg">▼</span>
          </div>
        </div>
        <div id="faq6" class="hidden px-6 pb-6">
          <p class="text-black">Yes, accounts may be terminated or have verification removed if fraudulent or abusive activity is detected. We reserve the right to verify, suspend, or ban accounts that violate our terms.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Help Guides Section -->
  <div id="guides-section" class="max-w-5xl mx-auto px-6 py-12">
    <h2 class="text-3xl font-bold text-black mb-8 text-center">
      Help <span class="text-noble-orange">Guides</span>
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
      <!-- Order Guide -->
      <div class="  rounded-lg p-6">

        <h3 class="text-xl font-semibold text-white mb-3">How to Order</h3>
        <ol class="space-y-2 text-black text-sm">
          <li><span class="text-noble-orange font-bold">1.</span> Browse furniture catalog</li>
          <li><span class="text-noble-orange font-bold">2.</span> Add items to your cart</li>
          <li><span class="text-noble-orange font-bold">3.</span> Enter delivery address</li>
          <li><span class="text-noble-orange font-bold">4.</span> Make bank transfer payment</li>
          <li><span class="text-noble-orange font-bold">5.</span> Upload payment proof</li>
          <li><span class="text-noble-orange font-bold">6.</span> Wait for delivery confirmation</li>
        </ol>
      </div>

      <!-- Payment Guide -->
      <div class="  rounded-lg p-6">

        <h3 class="text-xl font-semibold text-white mb-3">Payment Process</h3>
        <ol class="space-y-2 text-black text-sm">
          <li><span class="text-noble-orange font-bold">1.</span> Get bank details at checkout</li>
          <li><span class="text-noble-orange font-bold">2.</span> Transfer exact amount</li>
          <li><span class="text-noble-orange font-bold">3.</span> Upload payment screenshot</li>
          <li><span class="text-noble-orange font-bold">4.</span> Include account name used</li>
          <li><span class="text-noble-orange font-bold">5.</span> Wait for verification</li>
          <li><span class="text-noble-orange font-bold">6.</span> Delivery gets scheduled</li>
        </ol>
      </div>

      <!-- Account Guide -->
      <div class="  rounded-lg p-6">

        <h3 class="text-xl font-semibold text-white mb-3">Account Setup</h3>
        <ol class="space-y-2 text-black text-sm">
          <li><span class="text-noble-orange font-bold">1.</span> Click Register/Sign Up</li>
          <li><span class="text-noble-orange font-bold">2.</span> Use email or Google login</li>
          <li><span class="text-noble-orange font-bold">3.</span> Fill required information</li>
          <li><span class="text-noble-orange font-bold">4.</span> Verify your account</li>
          <li><span class="text-noble-orange font-bold">5.</span> Add delivery address</li>
          <li><span class="text-noble-orange font-bold">6.</span> Start shopping furniture</li>
        </ol>
      </div>
    </div>
  </div>


  <!-- Important Information -->
  <div class="max-w-5xl mx-auto px-6 py-8">
    <div class=" rounded-lg p-8 ">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
          <div class="flex items-center mb-4">

            <h3 class="text-xl font-bold text-black">Important Notes</h3>
          </div>
          <ul class="space-y-2 text-black text-sm">
            <li><span class="text-noble-orange">•</span> Delivery by NobleHome Delivery Services</li>
            <li><span class="text-noble-orange">•</span> Some products are imported from China</li>
            <li><span class="text-noble-orange">•</span> Bank transfer payment only</li>
            <li><span class="text-noble-orange">•</span> Replacement available for damaged items</li>
            <li><span class="text-noble-orange">•</span> Account security is our priority</li>
          </ul>
        </div>
        <div>
          <div class="flex items-center mb-4">

            <h3 class="text-xl font-bold text-black">Response Times</h3>
          </div>
          <ul class="space-y-2 text-black text-sm">
            <li><span class="text-noble-orange">•</span> Chatbot: Instant response</li>
            <li><span class="text-noble-orange">•</span> Email support: Within 24 hours</li>
            <li><span class="text-noble-orange">•</span> Payment verification: 2-4 hours</li>
            <li><span class="text-noble-orange">•</span> Damage reports: Same day response</li>
            <li><span class="text-noble-orange">•</span> Account issues: Within 12 hours</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <footer class="bg-black pattern-bg text-white py-16 mt-12 relative overflow-hidden">
    <!-- Decorative Elements -->
    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-500"></div>

    <div class="max-w-7xl mx-auto px-6 relative z-10">
      <!-- Main Footer Content -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

        <!-- Enhanced Branding Section -->
        <div class="lg:col-span-2">
          <div class="flex items-center space-x-4 mb-6">
            <!-- Logo with glow and pulse -->
            <div class="relative">
              <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-2xl glow-effect floating overflow-hidden">
                <img src="../img/logo.png" alt="Noble Home Logo" class="w-10 h-10 object-cover">
              </div>
              <div class="absolute -top-1 -right-1 w-4 h-4 bg-blue-400 rounded-full animate-pulse"></div>
            </div>

            <!-- Text Branding -->
            <div>
              <h2 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">Noble Home</h2>

            </div>
          </div>


          <p class="text-gray-300 leading-relaxed mb-6 max-w-md">
            Crafting exceptional living spaces with unmatched quality and attention to detail. Your dream home awaits with our expert construction and design services.
          </p>

          <!-- Contact Info -->
          <div class="space-y-3">
            <div class="flex items-center space-x-3 text-sm">
              <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                  <path d="m18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                </svg>
              </div>
              <span class="text-gray-300">noblehomeconst.ph@gmail.com</span>
            </div>
            <div class="flex items-center space-x-3 text-sm">
              <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                </svg>
              </div>
              <span class="text-gray-300">0968 591 6536</span>
            </div>
          </div>
        </div>

        <!-- Quick Links -->
        <div>
          <h3 class="text-xl font-bold mb-6 text-white relative">
            Quick Links
            <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
          </h3>
          <nav class="space-y-3">
            <a href="index" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Home</a>
            <a href="about" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">About Us</a>
            <a href="contact" class="block text-gray-300 hover:text-white link-hover transition-all duration-300 font-medium">Contact</a>
          </nav>
        </div>

        <!-- Services -->
        <div>
          <h3 class="text-xl font-bold mb-6 text-white relative">
            Our Services
            <div class="absolute -bottom-2 left-0 w-12 h-1 bg-gradient-to-r from-orange-500 to-transparent rounded-full"></div>
          </h3>
          <ul class="space-y-3 text-gray-300">
            <li class="hover:text-orange-300 transition-colors cursor-pointer">Appointment</li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
            <li class="hover:text-orange-300 transition-colors cursor-pointer"></li>
          </ul>
        </div>
      </div>

      <!-- Divider -->
      <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent mb-8"></div>

      <!-- Bottom Section -->
      <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
        <!-- Copyright -->
        <div class="text-center lg:text-left">
          <p class="text-gray-400 text-sm">
            © 2025 Noble Home Construction. All rights reserved.
          </p>
          <p class="text-gray-500 text-xs mt-1">
            Licensed & Insured | PCAB License No. 12345
          </p>
        </div>

        <!-- Enhanced Social Media -->
        <div class="flex items-center space-x-4">
          <span class="text-gray-400 text-sm mr-2">Follow us:</span>

          <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Facebook">
            <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M22 12a10 10 0 10-11.63 9.88v-6.99H8.4v-2.89h1.97V9.91c0-1.95 1.16-3.03 2.93-3.03.85 0 1.74.15 1.74.15v1.91h-.98c-.97 0-1.27.6-1.27 1.21v1.45h2.16l-.35 2.89h-1.81v6.99A10 10 0 0022 12z" />
            </svg>
          </a>

          <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="Instagram">
            <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 2 .3 2.5.5.6.2 1 .6 1.5 1.1.4.4.8.9 1.1 1.5.2.5.4 1.3.5 2.5.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 2-.5 2.5-.2.6-.6 1-1.1 1.5-.4.4-.9.8-1.5 1.1-.5.2-1.3.4-2.5.5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-2-.3-2.5-.5-.6-.2-1-.6-1.5-1.1-.4-.4-.8-.9-1.1-1.5-.2-.5-.4-1.3-.5-2.5C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-2 .5-2.5.2-.6.6-1 1.1-1.5.4-.4.9-.8 1.5-1.1.5-.2 1.3-.4 2.5-.5C8.4 2.2 8.8 2.2 12 2.2zm0 2.3c-3.1 0-3.5 0-4.7.1-.9.1-1.4.2-1.8.4-.5.2-.8.4-1.2.8s-.6.7-.8 1.2c-.2.4-.3.9-.4 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1.9.2 1.4.4 1.8.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2.9.3 1.8.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c.9-.1 1.4-.2 1.8-.4.5-.2.8-.4 1.2-.8s.6-.7.8-1.2c.2-.4.3-.9.4-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-.9-.2-1.4-.4-1.8-.2-.5-.4-.8-.8-1.2s-.7-.6-1.2-.8c-.4-.2-.9-.3-1.8-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.7a5.8 5.8 0 100 11.6 5.8 5.8 0 000-11.6zm0 9.5a3.7 3.7 0 110-7.4 3.7 3.7 0 010 7.4zm5.9-9.8a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0z" />
            </svg>
          </a>

          <a href="#" class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center social-hover transition-all duration-300 group" aria-label="LinkedIn">
            <svg class="w-5 h-5 text-gray-300 group-hover:text-orange-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
            </svg>
          </a>
        </div>

        <!-- Back to Top Button -->
        <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
          class="w-12 h-12 bg-orange-500 hover:bg-orange-600 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-lg">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Background Pattern -->
    <div class="absolute bottom-0 right-0 opacity-5">
      <svg width="200" height="200" viewBox="0 0 200 200" fill="none">
        <path d="M50 50h100v100H50z" stroke="currentColor" stroke-width="2" />
        <path d="M70 70h60v60H70z" stroke="currentColor" stroke-width="1" />
        <path d="M90 90h20v20H90z" stroke="currentColor" stroke-width="1" />
      </svg>
    </div>
  </footer>

  <!-- Footer -->
  <?php include '../navbar/footer.php'; ?>
  <script>
    // Auto-scroll to sections when clicking navigation
    document.addEventListener('DOMContentLoaded', function() {
      // Smooth scroll for anchor links
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        });
      });
    });
  </script>
</body>

</html>