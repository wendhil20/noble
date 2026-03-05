<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

// ✅ Restore session from remember_token (normal account or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        // Check if the account is Google-based (optional flag or logic)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
    header('Location: ../google-callback.php');
    exit;
}

// ✅ Retrieve user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? null;
$user_picture = $_SESSION['user_picture'] ?? null;


?>





<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messenger — Join</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Sora', sans-serif; }

    body {
      background: #0a0a0f;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    /* Animated blobs */
    .blob {
      position: fixed;
      border-radius: 50%;
      filter: blur(80px);
      opacity: 0.15;
      animation: float 8s ease-in-out infinite;
    }
    .blob-1 { width: 400px; height: 400px; background: #0084ff; top: -100px; left: -100px; animation-delay: 0s; }
    .blob-2 { width: 300px; height: 300px; background: #a855f7; bottom: -50px; right: -50px; animation-delay: 3s; }
    .blob-3 { width: 200px; height: 200px; background: #06b6d4; top: 50%; left: 50%; animation-delay: 1.5s; }

    @keyframes float {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33% { transform: translate(20px, -20px) scale(1.05); }
      66% { transform: translate(-10px, 15px) scale(0.95); }
    }

    .card {
      background: rgba(255,255,255,0.04);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 24px;
      padding: 48px 40px;
      width: 100%;
      max-width: 400px;
      position: relative;
      z-index: 10;
      box-shadow: 0 32px 80px rgba(0,0,0,0.5);
    }

    .logo-ring {
      width: 72px; height: 72px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0084ff, #a855f7);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px;
      box-shadow: 0 0 40px rgba(0,132,255,0.4);
    }

    input[type="text"] {
      width: 100%;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 14px;
      padding: 14px 18px;
      color: #fff;
      font-size: 15px;
      outline: none;
      transition: all 0.2s;
      font-family: 'Sora', sans-serif;
    }
    input[type="text"]:focus {
      border-color: #0084ff;
      background: rgba(0,132,255,0.08);
      box-shadow: 0 0 0 3px rgba(0,132,255,0.15);
    }
    input[type="text"]::placeholder { color: rgba(255,255,255,0.3); }

    .btn-join {
      width: 100%;
      background: linear-gradient(135deg, #0084ff, #0066cc);
      border: none;
      border-radius: 14px;
      padding: 14px;
      color: #fff;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      font-family: 'Sora', sans-serif;
      letter-spacing: 0.3px;
    }
    .btn-join:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(0,132,255,0.4);
      background: linear-gradient(135deg, #1a8fff, #0077dd);
    }
    .btn-join:active { transform: translateY(0); }

    .divider {
      display: flex; align-items: center; gap: 12px;
      margin: 20px 0;
    }
    .divider-line { flex: 1; height: 1px; background: rgba(255,255,255,0.08); }
    .divider span { color: rgba(255,255,255,0.25); font-size: 12px; }

    .quick-join-btn {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 10px;
      padding: 9px 14px;
      color: rgba(255,255,255,0.6);
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s;
      font-family: 'Sora', sans-serif;
    }
    .quick-join-btn:hover {
      background: rgba(255,255,255,0.1);
      color: #fff;
    }
  </style>
</head>
<body>
        <?php include '../navigation/uppernav.php'?>
  <!-- Background blobs -->
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <div class="card">
    <div class="logo-ring">
      <!-- Messenger lightning bolt icon -->
      <svg width="32" height="32" viewBox="0 0 32 32" fill="white">
        <path d="M16 2C8.268 2 2 7.87 2 15.07c0 4.2 2.07 7.95 5.31 10.41V30l4.85-2.66c1.29.36 2.66.55 4.08.55 7.732 0 14-5.87 14-13.07C30 7.87 23.732 2 16 2zm1.39 17.58L14 16.07l-6.5 3.51 7.14-7.58 3.39 3.51 6.5-3.51-7.14 7.58z"/>
      </svg>
    </div>

    <h1 style="color:#fff; font-size:22px; font-weight:700; text-align:center; margin-bottom:6px;">
      Welcome to Messenger
    </h1>
    <p style="color:rgba(255,255,255,0.4); font-size:13px; text-align:center; margin-bottom:28px;">
      Enter your name to start chatting
    </p>

    <div style="margin-bottom:14px;">
      <input
        type="text"
        id="usernameInput"
        placeholder="Your name..."
        maxlength="20"
        onkeydown="if(event.key==='Enter') joinChat()"
        autofocus
      />
    </div>

    <button class="btn-join" onclick="joinChat()">
      Enter Chat Room →
    </button>

    <div class="divider">
      <div class="divider-line"></div>
      <span>or join as</span>
      <div class="divider-line"></div>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:center;">
      <button class="quick-join-btn" onclick="quickJoin('Juan')">Juan</button>
      <button class="quick-join-btn" onclick="quickJoin('Maria')">Maria</button>
      <button class="quick-join-btn" onclick="quickJoin('Pedro')">Pedro</button>
      <button class="quick-join-btn" onclick="quickJoin('Ana')">Ana</button>
    </div>

    <p style="color:rgba(255,255,255,0.2); font-size:11px; text-align:center; margin-top:24px;">
      Open multiple tabs to test real-time chat
    </p>
  </div>

  <script>
    function joinChat() {
      const name = document.getElementById('usernameInput').value.trim();
      if (!name) {
        document.getElementById('usernameInput').style.borderColor = '#ef4444';
        document.getElementById('usernameInput').style.boxShadow = '0 0 0 3px rgba(239,68,68,0.2)';
        setTimeout(() => {
          document.getElementById('usernameInput').style.borderColor = '';
          document.getElementById('usernameInput').style.boxShadow = '';
        }, 1500);
        return;
      }
      // Save username to sessionStorage then redirect
      sessionStorage.setItem('messenger_user', name);
      window.location.href = 'chat.php';
    }

    function quickJoin(name) {
      sessionStorage.setItem('messenger_user', name);
      window.location.href = 'chat.php';
    }
  </script>
</body>
</html>