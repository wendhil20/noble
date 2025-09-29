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

    // 🔐 Store essential user session info
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'] ?? '';
    $_SESSION['user_mobile'] = $user['mobile'] ?? '';

    // 👤 Check if it's a Google account (optional)
    if (!empty($user['google_id'])) {
      $_SESSION['google_logged_in'] = true;
      $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
    }
  }

  $stmt->close();
}

// ✅ Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
  header('Location: ../google-callback.php');
  exit;
}

// 🔥 WALANG UPDATE - READ LANG yung status from database
$userStatus = getUserOnlineStatus($conn, $_SESSION['user_id']);
$currentUserEmail = $userStatus['email']; // Get current user's email
$currentUserOnlineStatus = $userStatus['is_online'];
$currentUserLastActivity = $userStatus['last_activity'];

// Simple logic: 1 = Online, 0 = Offline
$statusClass = ($currentUserOnlineStatus == 1) ? 'bg-green-500' : 'bg-gray-400';
$statusText = ($currentUserOnlineStatus == 1) ? 'Online' : 'Offline';

function getUserOnlineStatus($conn, $userId)
{
  try {
    // 🔥 Get email kasama yung status - CURRENT USER LANG
    $stmt = $conn->prepare("SELECT email, is_online, last_activity FROM nobleaccount WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $data = $result->fetch_assoc();
      $stmt->close();
      return [
        'email' => $data['email'] ?? '',
        'is_online' => (int)$data['is_online'], // Convert to integer (0 or 1)
        'last_activity' => $data['last_activity'] ?? null
      ];
    }
    $stmt->close();
    return ['email' => '', 'is_online' => 0, 'last_activity' => null];
  } catch (Exception $e) {
    return ['email' => '', 'is_online' => 0, 'last_activity' => null];
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" sizes="96x96" href="../img/favicon.ico">
  <title>Chat Support</title>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    #supportChatBox {
      overflow-y: auto;
      /* enable vertical scroll */
      scrollbar-width: thin;
      scrollbar-color: rgba(249, 115, 22, 0.6) transparent;
    }

    /* Webkit scrollbar styles */
    #supportChatBox::-webkit-scrollbar {
      width: 8px;
    }

    #supportChatBox::-webkit-scrollbar-track {
      background: transparent;
    }

    #supportChatBox::-webkit-scrollbar-thumb {
      background-color: rgba(249, 115, 22, 0.6);
      border-radius: 4px;
    }

    #supportChatBox::-webkit-scrollbar-thumb:hover {
      background-color: rgba(249, 115, 22, 0.9);
    }

    /* Custom animations */
    @keyframes slideIn {
      from {
        transform: translateX(20px);
        opacity: 0;
      }

      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @keyframes slideInFromLeft {
      from {
        transform: translateX(-100%);
        opacity: 0;
      }

      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .animate-slide-in {
      animation: slideIn 0.3s ease-out;
    }

    .animate-fade-in {
      animation: fadeIn 0.3s ease-out;
    }

    .animate-slide-in-left {
      animation: slideInFromLeft 0.3s ease-out;
    }

    /* Custom scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
      width: 8px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
      background: #f1f5f9;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: rgba(249, 115, 22, 0.4);
      border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: rgba(249, 115, 22, 0.7);
    }

    /* Glass effect */
    .glass-effect {
      backdrop-filter: blur(12px);
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Sales List Items */
    .sales-item {
      border-radius: 0.75rem;
      padding: 1rem 1.25rem;
      transition: all 0.25s ease;
      cursor: pointer;
      border: 2px solid transparent;
    }

    .sales-item:hover {
      background-color: #fff7ed;
      border-color: #fb923c;
      box-shadow: 0 4px 12px rgba(251, 146, 60, 0.25);
      transform: translateY(-2px);
    }

    /* Messages */
    .message.me {
      background: linear-gradient(135deg, #3b82f6, #1d4ed8);
      color: white;
      border-radius: 1.25rem 1.25rem 0.25rem 1.25rem;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    .message.other {
      background: linear-gradient(135deg, #fb923c, #f97316);
      color: white;
      border-radius: 1.25rem 1.25rem 1.25rem 0.25rem;
      box-shadow: 0 4px 12px rgba(251, 146, 60, 0.4);
    }

    /* Message container max width */
    .message-container {
      max-width: 85%;
      word-wrap: break-word;
    }

    @media (min-width: 640px) {
      .message-container {
        max-width: 70%;
      }
    }

    /* User indicator badges */
    .user-badge {
      font-size: 0.65rem;
      font-weight: 700;
      padding: 0.2rem 0.5rem;
      border-radius: 0.5rem;
      letter-spacing: 0.025em;
    }

    .client-badge {
      background: linear-gradient(135deg, #3b82f6, #1d4ed8);
      color: white;
    }

    .sales-badge {
      background: linear-gradient(135deg, #f97316, #ea580c);
      color: white;
    }

    /* Avatar styles */
    .client-avatar {
      background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .sales-avatar {
      background: linear-gradient(135deg, #f97316, #ea580c);
    }

    /* Input and send button */
    .message-input {
      border-radius: 1.5rem;
      border: 2px solid #e5e7eb;
      padding: 1rem 4rem 1rem 1.5rem;
      background-color: #f9fafb;
      transition: all 0.3s ease;
      font-size: 1rem;
      line-height: 1.5rem;
      color: #374151;
    }

    .message-input:focus {
      border-color: #fb923c;
      background-color: white;
      outline: none;
      box-shadow: 0 0 8px rgba(251, 146, 60, 0.3);
    }

    /* Send button enabled */
    .send-btn {
      border-radius: 1.25rem;
      min-width: 80px;
      padding: 0.75rem 1.25rem;
      font-weight: 600;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .send-btn:disabled {
      background-color: #d1d5db;
      cursor: not-allowed;
      box-shadow: none;
      transform: none !important;
      color: #9ca3af;
    }

    .send-btn.enabled {
      background: linear-gradient(135deg, #fb923c, #f97316);
      color: white;
      box-shadow: 0 4px 12px rgba(251, 146, 60, 0.5);
    }

    .send-btn.enabled:hover {
      background: linear-gradient(135deg, #f97316, #c2410c);
      box-shadow: 0 6px 20px rgba(251, 146, 60, 0.7);
      transform: scale(1.05);
      cursor: pointer;
    }

    /* Page background */
    .page-bg {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
    }

    /* Main container */
    .main-container {
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Status indicators */
    .status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.25rem 0.5rem;
      border-radius: 1rem;
    }

    .online-status {
      background: #dcfce7;
      color: #166534;
    }

    .online-dot {
      width: 0.5rem;
      height: 0.5rem;
      background: #22c55e;
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    /* Mobile overlay styles */
    .mobile-sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 40;
      display: none;
    }

    .mobile-sidebar-overlay.show {
      display: block;
      animation: fadeIn 0.3s ease-out;
    }

    .mobile-sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 300px;
      max-width: 85vw;
      background: white;
      z-index: 50;
      transform: translateX(-100%);
      transition: transform 0.3s ease-out;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .mobile-sidebar.show {
      transform: translateX(0);
    }

    /* Mobile message input adjustments */
    @media (max-width: 640px) {
      .message-input {
        padding: 0.875rem 2rem 0.875rem 1rem;
        font-size: 0.875rem;
      }

      .send-btn {
        min-width: 60px;
        padding: 0.75rem;
      }
    }

    /* Hide scrollbar on mobile for cleaner look */
    @media (max-width: 768px) {
      .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
      }
    }
  </style>
</head>


<body class="bg-orange-400">

  <?php include '../navbar/top.php'; ?>

  <div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <div class="max-w-6xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-4 sm:mb-8">
        <h1 class="text-2xl sm:text-4xl font-bold text-white mb-2">Customer Support</h1>
        <p class="text-white/80 text-sm sm:text-lg px-4">Connect with our sales representatives for assistance</p>
      </div>

      <?php if (!$isLoggedIn): ?>
        <!-- Login Required Message -->
        <div class="text-center py-20">
          <h2 class="text-3xl font-bold text-white mb-4">Please Sign In First</h2>
          <p class="text-white/80 mb-8">You need to be logged in to access customer support chat.</p>

          <!-- Sign In Button na mag-close ng modal at mag-redirect -->
          <button
            onclick="closeModalAndRedirect()"
            class="bg-white text-orange-500 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
            Return
          </button>
        </div>

        <script>
          function closeModalAndRedirect() {
            // Method 1: Try to close modal via parent window
            if (window.parent && window.parent !== window) {
              // Inform parent to close modal
              window.parent.postMessage({
                action: 'closeModal',
                redirect: 'index.php'
              }, '*');

              // Backup: Close modal directly if accessible
              try {
                // Common modal close methods
                if (window.parent.closeModal) {
                  window.parent.closeModal();
                } else if (window.parent.jQuery && window.parent.jQuery('.modal').modal) {
                  window.parent.jQuery('.modal').modal('hide');
                } else if (window.parent.bootstrap && window.parent.bootstrap.Modal) {
                  const modals = window.parent.document.querySelectorAll('.modal.show');
                  modals.forEach(modal => {
                    const modalInstance = window.parent.bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                  });
                }
              } catch (e) {
                console.log('Could not access parent modal directly');
              }

              // Then redirect parent page
              setTimeout(() => {
                window.parent.location.href = 'index.php';
              }, 500);

            } else {
              // Fallback: Direct redirect if not in iframe
              window.location.href = 'index.php';
            }
          }

          // Alternative: Auto-detect and close modal on page load
          window.addEventListener('load', function() {
            // Send message to parent that login is required
            if (window.parent && window.parent !== window) {
              window.parent.postMessage({
                action: 'loginRequired',
                message: 'User needs to sign in to access chat support'
              }, '*');
            }
          });
        </script>

      <?php else: ?>

        <!-- Main Chat Container -->
        <div x-data="chatSupport()" x-init="init()" class="main-container rounded-2xl overflow-hidden shadow-2xl">

          <!-- Mobile Menu Button (visible only on mobile) -->
          <div class="lg:hidden bg-white border-b border-gray-200 p-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Customer Support</h2>
            <button
              @click="showMobileSidebar = true"
              class="flex items-center gap-2 px-3 py-2 bg-orange-500 text-white rounded-lg shadow-md hover:bg-orange-600 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
              </svg>
              <span class="text-sm font-medium">Select Rep</span>
            </button>
          </div>

          <!-- Mobile Sidebar Overlay -->
          <div
            x-show="showMobileSidebar"
            @click="showMobileSidebar = false"
            class="mobile-sidebar-overlay lg:hidden"
            :class="showMobileSidebar ? 'show' : ''"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"></div>

          <!-- Mobile Sidebar -->
          <div
            x-show="showMobileSidebar"
            class="mobile-sidebar lg:hidden"
            :class="showMobileSidebar ? 'show' : ''"
            @click.stop
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="transform -translate-x-full"
            x-transition:enter-end="transform translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="transform translate-x-0"
            x-transition:leave-end="transform -translate-x-full">
            <!-- Mobile Sidebar Header -->
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-4 flex items-center justify-between">
              <h2 class="text-lg font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Sales Reps
              </h2>
              <button
                @click="showMobileSidebar = false"
                class="p-1 hover:bg-white/10 rounded-full transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>

            <!-- Mobile Sales List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
              <template x-for="sales in salesList" :key="sales.id">
                <div
                  @click="selectSales(sales); showMobileSidebar = false"
                  :class="selectedSales && selectedSales.id === sales.id ? 'bg-orange-50 border-orange-300 shadow-lg' : ''"
                  class="sales-item group border flex items-center gap-3 animate-fade-in">
                  <div class="w-10 h-10 sales-avatar rounded-full flex items-center justify-center text-white font-bold text-xs shadow-lg flex-shrink-0">
                    <span x-text="sales.fullname.split(' ').map(n => n[0]).join('').slice(0,2)"></span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-semibold text-gray-900 group-hover:text-orange-600 transition-colors duration-300 truncate text-sm" x-text="sales.fullname"></span>
                      <span class="sales-badge user-badge text-xs">SALES</span>
                    </div>
                    <div class="text-xs text-gray-600 truncate flex items-center gap-1 mt-1">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                      </svg>
                      <span x-text="sales.email"></span>
                    </div>
                   <div class="flex items-center gap-2 text-xs">
                        <div class="w-2 h-2 rounded-full" :class="sales.status_class"></div>
                        <span x-text="sales.status_text"></span>
                        <span class="text-gray-600"></span>
                      </div>

                  </div>
                </div>
              </template>

              <template x-if="salesList.length === 0">
                <div class="text-center py-8">
                  <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  </svg>
                  <p class="text-gray-500 font-semibold text-sm">No representatives available</p>
                  <p class="text-gray-400 text-xs mt-1">Please try again later</p>
                </div>
              </template>
            </div>
          </div>

          <!-- Chat Interface -->
          <div class="flex h-[500px] sm:h-[600px]">

            <!-- Desktop Sales Representatives Sidebar -->
            <div class="hidden lg:flex w-1/3 bg-white border-r border-gray-200 flex-col">
              <!-- Sidebar Header -->
              <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white p-4">
                <div class="flex items-center justify-between">
                  <h2 class="text-xl font-semibold flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Sales Representatives
                  </h2>
                  <span class="sales-badge user-badge">SALES TEAM</span>
                </div>
              </div>

              <!-- Desktop Sales List -->
              <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                <!-- Updated Sales List Template -->
                <template x-for="sales in salesList" :key="sales.id">
                  <div
                    @click="selectSales(sales)"
                    :class="selectedSales && selectedSales.id === sales.id ? 'bg-orange-50 border-orange-300 shadow-lg' : ''"
                    class="sales-item group border flex items-center gap-4 animate-fade-in relative">

                    <!-- Avatar with notification badge -->
                    <div class="relative">
                      <div class="w-12 h-12 sales-avatar rounded-full flex items-center justify-center text-white font-bold text-sm shadow-lg">
                        <span x-text="sales.fullname.split(' ').map(n => n[0]).join('').slice(0,2)"></span>
                      </div>

                      <!-- Unread message badge on avatar -->
                      <template x-if="sales.unread_count && sales.unread_count > 0">
                        <div class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full font-bold shadow-lg animate-pulse">
                          <span x-text="sales.unread_count > 9 ? '9+' : sales.unread_count"></span>
                        </div>
                      </template>
                    </div>

                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span
                          class="font-semibold text-gray-900 group-hover:text-orange-600 transition-colors duration-300 truncate"
                          :class="sales.unread_count && sales.unread_count > 0 ? 'text-orange-600 font-bold' : ''"
                          x-text="sales.fullname">
                        </span>
                        <span class="sales-badge user-badge">SALES REP</span>

                        <!-- New message indicator -->
                        <template x-if="sales.unread_count && sales.unread_count > 0">
                          <span class="bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full font-semibold animate-bounce">
                            New message
                          </span>
                        </template>
                      </div>

                      <div class="text-sm text-gray-600 truncate flex items-center gap-1 mt-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span x-text="sales.email"></span>
                      </div>

                      <!-- Last message preview -->
                      <template x-if="sales.last_message">
                        <div class="text-xs mt-1 flex items-center gap-1">
                          <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-2.285-.313l-3.58 1.194a.5.5 0 01-.643-.643l1.194-3.58A8.955 8.955 0 014 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
                          </svg>
                          <span
                            class="text-gray-500 truncate max-w-[200px]"
                            :class="sales.unread_count && sales.unread_count > 0 ? 'text-gray-700 font-medium' : ''"
                            x-text="sales.last_message">
                          </span>
                          <span class="text-gray-400 text-xs ml-auto" x-text="formatMessageTime(sales.last_message_time)"></span>
                        </div>
                      </template>

                      <div class="flex items-center gap-2 text-xs">
                        <div class="w-2 h-2 rounded-full" :class="sales.status_class"></div>
                        <span x-text="sales.status_text"></span>
                        <span class="text-gray-600"></span>
                      </div>


                    </div>

                    <!-- Arrow with notification indicator -->
                    <div class="flex items-center">
                      <template x-if="sales.unread_count && sales.unread_count > 0">
                        <div class="mr-2 flex flex-col items-center">
                          <div class="w-2 h-2 bg-red-500 rounded-full animate-ping"></div>
                        </div>
                      </template>

                      <svg class="w-5 h-5 text-gray-400 group-hover:text-orange-500 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                      </svg>
                    </div>
                  </div>
                </template>

                <template x-if="salesList.length === 0">
                  <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="text-gray-500 font-semibold">No representatives available</p>
                    <p class="text-gray-400 text-sm mt-1">Please try again later</p>
                  </div>
                </template>
              </div>
            </div>

            <!-- Chat Area -->
            <div class="flex-1 flex flex-col bg-gray-50">

              <!-- Chat Header -->
              <div class="bg-white border-b border-gray-200 p-3 sm:p-4 flex items-center gap-3">
                <template x-if="selectedSales">
                  <div class="flex items-center gap-3 animate-slide-in">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 sales-avatar rounded-full flex items-center justify-center text-white font-bold shadow-lg text-xs sm:text-sm">
                      <span x-text="selectedSales.fullname.split(' ').map(n => n[0]).join('').slice(0,2)"></span>
                    </div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-900 text-sm sm:text-base" x-text="selectedSales.fullname"></span>
                        <span class="sales-badge user-badge hidden sm:inline">SALES REP</span>
                        <span class="sales-badge user-badge text-xs sm:hidden">SALES</span>
                      </div>
                      <!-- NEW chat header status: -->
                      <div class="flex items-center gap-1 text-xs">
                        <div class="w-2 h-2 rounded-full" :class="selectedSales.status_class"></div>
                        <span x-text="selectedSales.status_text"></span>
                      </div>

                    </div>
                  </div>
                </template>

                <template x-if="!selectedSales">
                  <div class="flex items-center gap-3 text-gray-500">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <span class="text-sm sm:text-lg font-medium">Select a representative to start chatting</span>
                  </div>
                </template>
              </div>

              <!-- Messages Area -->
              <div id="supportChatBox" class="flex-1 overflow-y-auto p-3 sm:p-6 space-y-3 sm:space-y-4 custom-scrollbar">
                <template x-if="selectedSales && messages.length > 0">
                  <div class="space-y-3 sm:space-y-4">
                    <template x-for="msg in messages" :key="msg.id">
                      <div :class="msg.is_me ? 'flex justify-end' : 'flex justify-start'" class="animate-fade-in w-full">
                        <div class="flex items-end gap-2 sm:gap-3 max-w-[85%] w-auto">
                          <!-- Sales Representative Avatar and Badge -->
                          <div x-show="!msg.is_me" class="flex flex-col items-center gap-1 flex-shrink-0">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 sales-avatar rounded-full flex items-center justify-center text-white font-bold text-xs shadow-md">
                              <span x-text="selectedSales ? selectedSales.fullname.split(' ').map(n => n[0]).join('').slice(0,2) : ''"></span>
                            </div>
                            <span class="sales-badge user-badge text-xs hidden sm:inline whitespace-nowrap">SALES</span>
                          </div>

                          <!-- Message Content -->
                          <div
                            :class="msg.is_me ? 'message me' : 'message other'"
                            class="px-3 py-2 sm:px-4 sm:py-3 break-words leading-relaxed shadow-lg text-sm sm:text-base rounded-lg min-w-0 flex-1"
                            style="word-wrap: break-word; overflow-wrap: anywhere; hyphens: auto;">
                            <span x-text="msg.message" class="inline-block w-full"></span>
                          </div>

                          <!-- Client Avatar and Badge -->
                          <div x-show="msg.is_me" class="flex flex-col items-center gap-1 flex-shrink-0">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 client-avatar rounded-full flex items-center justify-center text-white font-bold text-xs shadow-md overflow-hidden">
                              <?php if ($isLoggedIn && isset($_SESSION['user_picture']) && !empty($_SESSION['user_picture'])): ?>
                                <!-- Show profile picture -->
                                <img src="<?= htmlspecialchars($_SESSION['user_picture']) ?>"
                                  alt="User Avatar"
                                  class="w-full h-full object-cover">
                              <?php elseif ($isLoggedIn && isset($_SESSION['user_name'])): ?>
                                <!-- Show first letter of user's name -->
                                <span class="text-white font-bold">
                                  <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </span>
                              <?php else: ?>
                                <!-- Default user icon if not logged in -->
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="currentColor" viewBox="0 0 20 20">
                                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                </svg>
                              <?php endif; ?>
                            </div>
                            <span class="client-badge user-badge text-xs hidden sm:inline whitespace-nowrap">CLIENT</span>
                          </div>

                        </div>
                      </div>
                    </template>
                  </div>
                </template>
                <template x-if="selectedSales && messages.length === 0">
                  <div class="text-center py-8 sm:py-16">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-gray-500 font-semibold text-base sm:text-lg">Start the conversation!</p>
                    <p class="text-gray-400 mt-1 text-sm">Send a message to get started</p>
                  </div>
                </template>

                <template x-if="!selectedSales">
                  <div class="text-center py-12 sm:py-20 px-4">
                    <svg class="w-16 h-16 sm:w-20 sm:h-20 text-gray-300 mx-auto mb-4 sm:mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-500 mb-2">Welcome to Customer Support</h3>
                    <p class="text-gray-400 text-sm sm:text-base">Choose a sales representative to start your conversation</p>
                    <div class="lg:hidden mt-4">
                      <button
                        @click="showMobileSidebar = true"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded-lg shadow-md hover:bg-orange-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Select Sales Representative
                      </button>
                    </div>
                  </div>
                </template>
              </div>

              <!-- Message Input -->
              <template x-if="selectedSales">
                <div class="bg-white border-t border-gray-200 p-3 sm:p-4">
                  <!-- Current User Info -->
                  <div class="hidden sm:flex items-center gap-2 mb-3 text-sm text-gray-600">
                    <div class="w-6 h-6 client-avatar rounded-full flex items-center justify-center text-white">
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                      </svg>
                    </div>
                    <span class="font-medium">You are chatting as:</span>
                    <span class="client-badge user-badge">CLIENT</span>


                  </div>

                  <div class="flex items-end gap-2 sm:gap-3">
                    <div class="flex-1 relative">
                      <input
                        x-model="newMessage"
                        @keydown.enter="sendMessage()"
                        type="text"
                        placeholder="Type your message..."
                        class="message-input w-full text-sm sm:text-base"
                        autocomplete="off" />
                    </div>
                    <button
                      @click="sendMessage()"
                      :disabled="!newMessage.trim()"
                      :class="newMessage.trim() ? 'send-btn enabled' : 'send-btn'"
                      class="flex-shrink-0">
                      <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php include '../navbar/footer.php'; ?>
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('chatSupport', () => ({
        // UI state
        showMobileSidebar: false,

        // Chat data
        salesList: [],
        selectedSales: null,
        messages: [],
        newMessage: '',
        refreshInterval: null,
        loggedInUserId: <?= json_encode($_SESSION['user_id'] ?? null) ?>,
        debugMode: true,

        init() {
          console.log('Initializing chat support...');
          this.loadSalesReps();

          // Set up periodic refresh for sales list to get unread counts
          setInterval(() => {
            this.loadSalesReps();
          }, 10000); // Refresh every 10 seconds
        },

        loadSalesReps() {
          console.log('Loading sales representatives...');
          fetch('chat_fetch_sales.php', {
              credentials: 'include'
            })
            .then(res => {
              console.log('Sales fetch response status:', res.status);
              return res.json();
            })
            .then(data => {
              console.log('Sales data received:', data);
              if (data.status === 'success' && data.data) {
                this.salesList = data.data;
              } else if (Array.isArray(data)) {
                this.salesList = data;
              } else {
                console.error('Unexpected sales data format:', data);
                this.salesList = [];
              }

              // Sort sales list to show those with unread messages first
              this.salesList.sort((a, b) => {
                const aUnread = a.unread_count || 0;
                const bUnread = b.unread_count || 0;
                if (aUnread !== bUnread) return bUnread - aUnread; // Higher unread count first
                return (a.fullname || '').localeCompare(b.fullname || '');
              });

              console.log('Sales list loaded and sorted:', this.salesList);
            })
            .catch(err => {
              console.error('Error loading sales reps:', err);
              this.salesList = [];
            });
        },

        selectSales(sales) {
          console.log('Selected sales rep:', sales);
          this.selectedSales = sales;
          this.messages = [];

          // Mark messages as read when selecting
          if (sales.unread_count && sales.unread_count > 0) {
            this.markMessagesAsRead(sales.id);
          }

          this.fetchMessages();
          if (this.refreshInterval) clearInterval(this.refreshInterval);
          this.refreshInterval = setInterval(() => this.fetchMessages(), 3000);
        },

        markMessagesAsRead(salesId) {
          fetch('chat_mark_read.php', {
              method: 'POST',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                sales_id: salesId
              })
            })
            .then(res => {
              if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
              }
              return res.json();
            })
            .then(data => {
              console.log('Messages marked as read:', data);
              // Update local unread count
              const salesIndex = this.salesList.findIndex(s => s.id === salesId);
              if (salesIndex !== -1) {
                this.salesList[salesIndex].unread_count = 0;
              }
            })
            .catch(error => {
              console.error('Error marking messages as read:', error);
            });
        },

        fetchMessages() {
          if (!this.selectedSales) {
            console.log('No sales rep selected, skipping message fetch');
            return;
          }
          const url = `chat_getmessage.php?receiver_noble_id=${encodeURIComponent(this.selectedSales.id)}`;
          console.log('Fetching messages from:', url);
          fetch(url, {
              credentials: 'include'
            })
            .then(res => {
              console.log('Messages fetch response status:', res.status);
              return res.text();
            })
            .then(text => {
              console.log('Raw response:', text);
              try {
                const data = JSON.parse(text);
                console.log('Parsed messages data:', data);
                if (data.error) {
                  console.error('Server error:', data.error);
                  return;
                }
                let messagesArray = [];
                if (data.status === 'success' && data.messages) {
                  messagesArray = data.messages;
                } else if (Array.isArray(data)) {
                  messagesArray = data;
                }
                this.messages = messagesArray.map(m => {
                  const isFromCurrentUser = parseInt(m.sender_user_id) === parseInt(this.loggedInUserId);
                  const isFromAdmin = !isFromCurrentUser && (m.sender_noble_id || m.is_from_admin);
                  return {
                    ...m,
                    is_me: isFromCurrentUser,
                    is_from_admin: isFromAdmin,
                    display_name: isFromCurrentUser ? 'You' : (m.sender_name || 'Admin')
                  };
                });
                this.$nextTick(() => {
                  const box = document.getElementById('supportChatBox');
                  if (box) {
                    box.scrollTop = box.scrollHeight;
                  }
                });
              } catch (err) {
                console.error('Failed to parse JSON:', err);
                console.error('Raw response was:', text);
              }
            })
            .catch(err => console.error('Fetch error:', err));
        },

        sendMessage() {
          if (!this.selectedSales) {
            alert("Please select a sales representative first.");
            return;
          }
          if (!this.newMessage.trim()) {
            console.log('Empty message, not sending');
            return;
          }
          const messageData = {
            receiver_noble_id: parseInt(this.selectedSales.id),
            message: this.newMessage.trim()
          };
          console.log('Sending message:', messageData);
          fetch('chat_sendmessage.php', {
              method: 'POST',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
              },
              body: JSON.stringify(messageData)
            })
            .then(res => {
              console.log('Send message response status:', res.status);
              return res.text();
            })
            .then(text => {
              console.log('Send message raw response:', text);
              try {
                const data = JSON.parse(text);
                if (data.status === 'success') {
                  this.newMessage = '';
                  this.fetchMessages();
                  console.log('Message sent successfully');
                } else {
                  console.error('Server error:', data.error);
                  alert("Error sending message: " + (data.error || 'Unknown error'));
                }
              } catch (err) {
                console.error('Failed to parse send response:', err);
                console.error('Raw response was:', text);
                alert("Error: Invalid server response");
              }
            })
            .catch(err => {
              console.error('Send message fetch error:', err);
              alert("Network error occurred while sending message");
            });
        },

        // Helper methods for UI
        formatDateTime(dateString) {
          if (!dateString) return '';
          const date = new Date(dateString);
          const now = new Date();
          const diff = Math.floor((now - date) / 1000);

          if (diff < 60) return 'Just now';
          if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
          if (diff < 86400) return `${Math.floor(diff / 3600)} hrs ago`;
          if (diff < 172800) return 'Yesterday';

          const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
          };
          return date.toLocaleString('en-US', options);
        },

        formatMessageTime(timestamp) {
          if (!timestamp) return '';
          const date = new Date(timestamp);
          const now = new Date();
          const diff = Math.floor((now - date) / 1000);

          if (diff < 60) return 'now';
          if (diff < 3600) return `${Math.floor(diff / 60)}m`;
          if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
          if (diff < 172800) return 'yesterday';

          return date.toLocaleDateString();
        },

        getTotalUnreadCount() {
          return this.salesList.reduce((total, sales) => {
            return total + (sales.unread_count || 0);
          }, 0);
        },

        destroy() {
          if (this.refreshInterval) clearInterval(this.refreshInterval);
        }
      }));
    });
  </script>

</body>

</html>