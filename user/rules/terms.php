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

// ✅ Final session check
if (!isset($_SESSION['user_id'])) {
  // Not logged in — redirect to login or Google auth
  header('Location: ../google-callback.php'); // You may replace with `index.php` if default login
  exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms and Conditions - NobleHome Depot</title>
  <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Nunito:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Pacifico&family=Lobster&family=Quicksand:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700&family=Lora:wght@400;500;600;700&family=Oswald:wght@300;400;500;600;700&family=Bebas+Neue&family=Anton&family=Rubik:wght@300;400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&family=Ubuntu:wght@300;400;500;700&family=Barlow:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

<body class=" text-white min-h-screen font-mont">
  <?php include '../navbar/top.php'; ?>
  <!-- Header Section -->
  <div class="bg-black border-b border-noble-orange">
    <div class="max-w-6xl mx-auto px-6 py-8">
      <div class="text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">
          Terms & <span class="text-noble-orange">Conditions</span>
        </h1>
        <div class="w-24 h-1 bg-noble-orange mx-auto mb-4"></div>
        <p class="text-gray-300 text-lg max-w-2xl mx-auto">
          Please read our terms carefully. By using NobleHome Depot, you agree to these conditions.
        </p>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="max-w-5xl mx-auto px-6 py-12">
    <!-- Introduction Card -->
    <div class="  rounded-lg p-8 mb-8 ">
      <div class="flex items-start space-x-4">
        <div class="flex-shrink-0">
          <div class="w-12 h-12 bg-noble-orange rounded-full flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
        </div>
        <div>
          <h2 class="text-4xl font-bold text-black mb-3">Welcome to NobleHome Depot</h2>
          <p class="text-black leading-relaxed">
            By accessing and using our website, you agree to the following Terms and Conditions. Please read them carefully before using our services.
          </p>
        </div>
      </div>
    </div>

    <!-- Terms Sections -->
    <div class="space-y-8">
      <!-- Section 1 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">1</span>
            General Information
          </h2>
        </div>
        <div class="p-6">
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0 mt-1">
              <svg class="w-6 h-6 text-noble-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
            </div>
            <p class="text-black leading-relaxed">
              Introduction
              This website is owned and operated by Noblehome Construction Corporation. By accessing or using this website, including browsing, purchasing, or engaging with our services, the User or Customer agree to be bound by these Terms & Conditions and our Privacy Policy.

            </p>
          </div>
        </div>
      </div>

      <!-- Section 2 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">2</span>
            Acceptance of Terms
          </h2>
        </div>
        <div class="p-6">
          <ul class="space-y-3">
            <li class="flex items-start space-x-3">
              <div class="w-2 h-2 bg-noble-orange rounded-full mt-2 flex-shrink-0"></div>
              <span class="text-black">By using this website, you confirm that you have read, understood, and accepted these Terms & Conditions. If you do not agree, you must stop using our website and services immediately.</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Section 3 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">3</span>
            User Responsibilities
          </h2>
        </div>
        <div class="p-6">
          <ul class="space-y-3">
            <li class="flex items-start space-x-3">
              <div class="w-2 h-2 bg-noble-orange rounded-full mt-2 flex-shrink-0"></div>
              <span class="text-black">Users agree to use this website in a lawful and responsible manner. You must not:
                , Post, share, or transmit harmful, offensive, or fraudulent content.
                , Attempt to hack, disrupt, or misuse the website.
                , Copy, reproduce, or distribute website content without permission.
                , Engage in spam, scams, or unauthorized promotions.
                Violation of these rules may result in suspension or termination of your access.</span>
            </li>

          </ul>
        </div>
      </div>

      <!-- Section 4 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">4</span>
            Orders & Payments
          </h2>
        </div>
        <div class="p-6">
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0 mt-1">
              <svg class="w-6 h-6 text-noble-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
              </svg>
            </div>
            <p class="text-black leading-relaxed">
              For online orders:
              , Prices displayed include applicable taxes unless otherwise stated.
              , Payments must be completed through the available payment channels at checkout.
              , Shipping and delivery timelines are estimates and may vary depending on courier services.
              ,Refunds and returns are subject to our Return & Refund Policy, which requires the product to be returned in original condition within 7 days upon receipt.
            </p>
          </div>
        </div>
      </div>

      <!-- Section 5 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">5</span>
            Limitations of Liability
          </h2>
        </div>
        <div class="p-6">
          <div class="  rounded-lg p-4">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0 mt-1">
                <svg class="w-6 h-6 text-noble-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
              </div>
              <div>

                <p class="text-black leading-relaxed">
                  Noblehome Depot shall not be liable for any indirect, incidental, or consequential damages arising from the use of this website or products purchased.
                  ,We are not responsible for any delays, losses, or damages caused by third-party services (e.g., couriers, payment gateways).
                  ,Product images may differ slightly from actual products due to variations in display and materials. (manage your expectation)
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 6 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">6</span>
            User Responsibilities
          </h2>
        </div>
        <div class="p-6">
          <ul class="space-y-3">
            <li class="flex items-start space-x-3">
              <div class="w-2 h-2 bg-noble-orange rounded-full mt-2 flex-shrink-0"></div>
              <span class="text-black">Provide accurate personal details during registration and checkout</span>
            </li>
            <li class="flex items-start space-x-3">
              <div class="w-2 h-2 bg-noble-orange rounded-full mt-2 flex-shrink-0"></div>
              <span class="text-black">Use the website responsibly and avoid abusive behavior in chats or reviews</span>
            </li>
            <li class="flex items-start space-x-3">
              <div class="w-2 h-2 bg-noble-orange rounded-full mt-2 flex-shrink-0"></div>
              <span class="text-black">Follow the guides provided on the site for using our services</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Section 7 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">7</span>
            Limitation of Liability
          </h2>
        </div>
        <div class="p-6">
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0 mt-1">
              <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div>
              <p class="text-black leading-relaxed">
                NobleHome Depot shall not be held liable for delays caused by unforeseen circumstances such as natural disasters, customs delays, or shipping issues from import sources.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 8 -->
      <div class="  rounded-lg overflow-hidden ">
        <div class=" p-6">
          <h2 class="text-2xl font-bold text-black flex items-center">
            <span class="bg-orange-400 text-white  rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold mr-4">8</span>
            Governing Law
          </h2>
        </div>
        <div class="p-6">
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0 mt-1">
              <svg class="w-6 h-6 text-noble-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
              </svg>
            </div>
            <p class="text-black leading-relaxed">
            These Terms & Conditions shall be governed by and interpreted in accordance with the laws of the Republic of the Philippines. Any disputes shall be resolved under the exclusive jurisdiction of the courts in Quezon City, Philippines.

            </p>
          </div>
        </div>
      </div>

      <!-- Section 9 - Contact -->
      <div class=" rounded-lg p-8">
        <div class="text-center">
          <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-noble-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-black mb-4">Questions About Our Terms?</h2>
          <p class="text-black mb-6 opacity-90">
            If you have any questions about these Terms and Conditions, we're here to help clarify.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="contact.php" class="inline-flex items-center bg-white text-orange-400 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors duration-200">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
              </svg>
              Contact Page
            </a>
            <div class="flex items-center text-black opacity-90">
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
              </svg>
              Use our built-in chatbot support
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="bg-gray-900 border-t border-gray-800 py-8 mt-16">
    <div class="max-w-5xl mx-auto px-6 text-center">
      <p class="text-gray-400">
        © <?= date("Y"); ?> NobleHome Depot. All rights reserved. |
        <span class="text-noble-orange">Terms Updated: <?= date("F j, Y"); ?></span>
      </p>
    </div>
  </div>
</body>

</html>