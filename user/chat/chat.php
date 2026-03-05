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
        $_SESSION['user_email'] = $user['email'];
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture']     = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id      = $_SESSION['user_id'];
$user_name    = $_SESSION['user_name']  ?? 'Guest';
$user_email   = $_SESSION['user_email'] ?? '';
$user_picture = $_SESSION['user_picture'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Support Chat — Noble Home Depot</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { font-family: 'Sora', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:        #0a0a0f;
      --surface:   rgba(255,255,255,0.04);
      --border:    rgba(255,255,255,0.07);
      --blue:      #0084ff;
      --blue-dark: #0055cc;
      --text:      #e8e8f0;
      --muted:     rgba(255,255,255,0.35);
      --subtle:    rgba(255,255,255,0.08);
    }

    html, body { height: 100%; background: var(--bg); overflow: hidden; }

    /* ── LAYOUT ── */
    .app {
      height: 100vh;
      display: flex;
      flex-direction: column;
      max-width: 780px;
      margin: 0 auto;
      border-left: 1px solid var(--border);
      border-right: 1px solid var(--border);
      position: relative;
    }

    /* ── HEADER ── */
    .header {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 20px;
      background: rgba(10,10,15,0.85);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
      z-index: 10;
    }

    .header-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1a6fff, #a855f7);
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 16px; color: #fff;
      flex-shrink: 0;
      position: relative;
    }

    .status-ring {
      position: absolute;
      inset: -3px;
      border-radius: 50%;
      border: 2px solid transparent;
      transition: border-color 0.4s;
    }
    .status-ring.online  { border-color: #22c55e; }
    .status-ring.offline { border-color: rgba(255,255,255,0.15); }

    .status-dot {
      width: 10px; height: 10px;
      border-radius: 50%;
      background: #22c55e;
      border: 2px solid var(--bg);
      position: absolute;
      bottom: 1px; right: 1px;
      transition: background 0.3s;
    }
    .status-dot.offline { background: rgba(255,255,255,0.25); }

    /* ── MESSAGES ── */
    #messagesContainer {
      flex: 1;
      overflow-y: auto;
      padding: 24px 20px 12px;
      display: flex;
      flex-direction: column;
      gap: 4px;
      scroll-behavior: smooth;
    }
    #messagesContainer::-webkit-scrollbar { width: 3px; }
    #messagesContainer::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

    /* Message grouping */
    .msg-wrapper {
      display: flex;
      align-items: flex-end;
      gap: 8px;
      max-width: 78%;
      animation: msgIn 0.18s ease-out;
    }
    @keyframes msgIn {
      from { opacity: 0; transform: translateY(6px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .msg-wrapper.mine  { align-self: flex-end; flex-direction: row-reverse; }
    .msg-wrapper.other { align-self: flex-start; }
    .msg-wrapper.mt    { margin-top: 10px; }

    .msg-avatar {
      width: 26px; height: 26px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700; flex-shrink: 0;
      margin-bottom: 2px;
    }
    .msg-avatar.hidden { visibility: hidden; }

    .msg-col { display: flex; flex-direction: column; gap: 2px; }
    .msg-sender-name {
      font-size: 11px; font-weight: 600;
      color: rgba(255,255,255,0.4);
      padding-left: 12px;
      margin-bottom: 2px;
    }

    .msg-bubble {
      padding: 10px 14px;
      border-radius: 20px;
      font-size: 14px;
      line-height: 1.5;
      word-break: break-word;
    }

    .msg-wrapper.mine .msg-bubble {
      background: linear-gradient(135deg, var(--blue), var(--blue-dark));
      color: #fff;
      border-bottom-right-radius: 5px;
    }
    .msg-wrapper.mine .msg-bubble + .msg-bubble { border-top-right-radius: 8px; }

    .msg-wrapper.other .msg-bubble {
      background: rgba(255,255,255,0.08);
      color: var(--text);
      border: 1px solid var(--border);
      border-bottom-left-radius: 5px;
    }
    .msg-wrapper.other .msg-bubble + .msg-bubble { border-top-left-radius: 8px; }

    .msg-time {
      font-size: 10px;
      color: rgba(255,255,255,0.2);
      padding: 0 4px;
      white-space: nowrap;
      align-self: flex-end;
    }

    /* System message */
    .msg-system {
      text-align: center;
      font-size: 11px;
      color: rgba(255,255,255,0.2);
      padding: 8px 0;
      font-style: italic;
    }

    /* Date divider */
    .date-divider {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 0;
    }
    .date-divider::before, .date-divider::after {
      content: ''; flex: 1; height: 1px;
      background: rgba(255,255,255,0.06);
    }
    .date-divider span {
      font-size: 10px; color: rgba(255,255,255,0.2);
      white-space: nowrap; font-weight: 500;
    }

    /* Typing */
    #typingIndicator {
      padding: 0 20px 8px;
      height: 26px;
      font-size: 12px;
      color: var(--muted);
      font-style: italic;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .typing-dots {
      display: flex; gap: 3px; align-items: center;
    }
    .typing-dots span {
      width: 5px; height: 5px;
      background: var(--muted);
      border-radius: 50%;
      animation: typingBounce 1.2s ease-in-out infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typingBounce {
      0%, 60%, 100% { transform: translateY(0); }
      30%            { transform: translateY(-4px); }
    }

    /* ── INPUT BAR ── */
    .input-bar {
      padding: 12px 16px;
      border-top: 1px solid var(--border);
      background: rgba(10,10,15,0.9);
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }

    #msgInput {
      flex: 1;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 24px;
      padding: 11px 18px;
      color: #fff;
      font-size: 14px;
      outline: none;
      transition: all 0.2s;
      font-family: 'Sora', sans-serif;
    }
    #msgInput:focus {
      border-color: rgba(0,132,255,0.45);
      background: rgba(0,132,255,0.06);
      box-shadow: 0 0 0 3px rgba(0,132,255,0.1);
    }
    #msgInput::placeholder { color: rgba(255,255,255,0.22); }

    .send-btn {
      width: 42px; height: 42px;
      background: var(--blue);
      border: none; border-radius: 50%;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: all 0.18s; flex-shrink: 0;
    }
    .send-btn:hover:not(:disabled) {
      background: #1a8fff;
      transform: scale(1.06);
      box-shadow: 0 4px 18px rgba(0,132,255,0.45);
    }
    .send-btn:active:not(:disabled) { transform: scale(0.96); }
    .send-btn:disabled { background: rgba(255,255,255,0.08); cursor: not-allowed; }

    /* ── OFFLINE BANNER ── */
    #offlineBanner {
      display: none;
      background: rgba(239,68,68,0.12);
      border-bottom: 1px solid rgba(239,68,68,0.2);
      padding: 8px 20px;
      font-size: 12px;
      color: #fca5a5;
      text-align: center;
    }

    /* ── EMPTY STATE ── */
    .empty-state {
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; flex: 1; padding: 40px 24px;
      text-align: center; gap: 10px;
    }
    .empty-icon {
      width: 64px; height: 64px;
      background: rgba(0,132,255,0.1);
      border: 1px solid rgba(0,132,255,0.2);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 6px;
    }

    /* Subtle background grid */
    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image: linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
      background-size: 40px 40px;
      pointer-events: none;
      z-index: 0;
    }
    .app { z-index: 1; }
  </style>
</head>
<body>

<div class="app">

  <!-- ── HEADER ── -->
  <div class="header">
    <div class="header-avatar">
      S
      <div class="status-ring offline" id="statusRing"></div>
      <div class="status-dot offline" id="statusDot"></div>
    </div>
    <div style="flex:1;">
      <div style="color:#fff;font-weight:700;font-size:15px;">Noble Support</div>
      <div style="font-size:11px;color:var(--muted);" id="headerStatus">Connecting...</div>
    </div>
    <!-- User info pill -->
    <div style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:20px;padding:6px 12px;">
      <?php if ($user_picture): ?>
        <img src="<?= htmlspecialchars($user_picture) ?>" style="width:22px;height:22px;border-radius:50%;object-fit:cover;" />
      <?php else: ?>
        <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#0084ff,#a855f7);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;">
          <?= strtoupper(substr($user_name, 0, 1)) ?>
        </div>
      <?php endif; ?>
      <span style="font-size:12px;color:rgba(255,255,255,0.6);font-weight:500;"><?= htmlspecialchars($user_name) ?></span>
    </div>
  </div>

  <!-- ── OFFLINE BANNER ── -->
  <div id="offlineBanner">⚠ Connection lost — trying to reconnect...</div>

  <!-- ── MESSAGES ── -->
  <div id="messagesContainer">
    <!-- Empty state shown before messages load -->
    <div class="empty-state" id="emptyState">
      <div class="empty-icon">
        <svg width="28" height="28" viewBox="0 0 32 32" fill="rgba(0,132,255,0.8)">
          <path d="M16 2C8.268 2 2 7.87 2 15.07c0 4.2 2.07 7.95 5.31 10.41V30l4.85-2.66c1.29.36 2.66.55 4.08.55 7.732 0 14-5.87 14-13.07C30 7.87 23.732 2 16 2zm1.39 17.58L14 16.07l-6.5 3.51 7.14-7.58 3.39 3.51 6.5-3.51-7.14 7.58z"/>
        </svg>
      </div>
      <div style="color:rgba(255,255,255,0.6);font-size:15px;font-weight:600;">Start a conversation</div>
      <div style="color:rgba(255,255,255,0.25);font-size:13px;line-height:1.6;">
        Our sales team is here to help.<br>Send a message to get started.
      </div>
    </div>
  </div>

  <!-- ── TYPING INDICATOR ── -->
  <div id="typingIndicator"></div>

  <!-- ── INPUT BAR ── -->
  <div class="input-bar">
    <input
      type="text"
      id="msgInput"
      placeholder="Type a message..."
      maxlength="500"
      autocomplete="off"
    />
    <button class="send-btn" id="sendBtn" disabled>
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
        <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>

</div>

<script>
  // ── PHP → JS ──────────────────────────────────────────
  const USER_ID   = <?= json_encode((string)$user_id) ?>;
  const USER_NAME = <?= json_encode($user_name) ?>;

  // ── DOM REFS ──────────────────────────────────────────
  const messagesEl   = document.getElementById('messagesContainer');
  const msgInput     = document.getElementById('msgInput');
  const sendBtn      = document.getElementById('sendBtn');
  const typingEl     = document.getElementById('typingIndicator');
  const headerStatus = document.getElementById('headerStatus');
  const statusRing   = document.getElementById('statusRing');
  const statusDot    = document.getElementById('statusDot');
  const offlineBanner= document.getElementById('offlineBanner');
  const emptyState   = document.getElementById('emptyState');

  // ── SOCKET ────────────────────────────────────────────
  const socket = io('https://support.noblehomedepot.com');

  // ── CONNECT ───────────────────────────────────────────
  socket.on('connect', () => {
    offlineBanner.style.display = 'none';
    setAdminStatus(false); // will be updated by user:online event
    sendBtn.disabled = false;

    // Join as user with PHP session data — unique per account
    socket.emit('user:join', {
      userId:   USER_ID,
      userName: USER_NAME,
      role:     'user'
    });
  });

  socket.on('disconnect', () => {
    offlineBanner.style.display = 'block';
    headerStatus.textContent = 'Disconnected — reconnecting...';
    sendBtn.disabled = true;
  });

  // ── HISTORY (load past messages on connect) ────────────
  socket.on('history', (msgs) => {
    messagesEl.innerHTML = '';
    if (!msgs || msgs.length === 0) {
      messagesEl.appendChild(emptyState);
      emptyState.style.display = 'flex';
    } else {
      emptyState.style.display = 'none';
      msgs.forEach((msg, i) => {
        if (msg && msg.text) renderMessage(msg, i > 0 ? msgs[i-1] : null);
      });
    }
    scrollToBottom();
  });

  // ── NEW MESSAGE ────────────────────────────────────────
  socket.on('message:new', (msg) => {
    if (!msg || !msg.text) return;
    emptyState.style.display = 'none';

    // Get last message for grouping logic
    const bubbles = messagesEl.querySelectorAll('.msg-wrapper');
    const lastMsg = bubbles.length > 0 ? bubbles[bubbles.length - 1] : null;
    renderMessage(msg, null, lastMsg);
    scrollToBottom();
  });

  // ── ADMIN ONLINE/OFFLINE ────────────────────────────────
  // Listen if admin comes online (optional — server can emit this)
  socket.on('admin:online', () => setAdminStatus(true));
  socket.on('admin:offline', () => setAdminStatus(false));

  // ── TYPING ────────────────────────────────────────────
  let typingTimeout;
  socket.on('typing:show', ({ userName }) => {
    typingEl.innerHTML = `
      <div class="typing-dots"><span></span><span></span><span></span></div>
      <span style="font-size:12px;color:rgba(255,255,255,0.3);">Support is typing...</span>
    `;
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => { typingEl.innerHTML = ''; }, 4000);
  });

  socket.on('typing:hide', () => {
    typingEl.innerHTML = '';
    clearTimeout(typingTimeout);
  });

  // ── SEND MESSAGE ──────────────────────────────────────
  function sendMessage() {
    const text = msgInput.value.trim();
    if (!text || !socket.connected) return;

    socket.emit('message:send', { text });
    socket.emit('typing:stop');

    msgInput.value = '';
    isTyping = false;
    clearTimeout(typingStopTimer);
  }

  sendBtn.addEventListener('click', sendMessage);
  msgInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  let isTyping = false, typingStopTimer;
  msgInput.addEventListener('input', () => {
    if (!isTyping) {
      isTyping = true;
      socket.emit('typing:start');
    }
    clearTimeout(typingStopTimer);
    typingStopTimer = setTimeout(() => {
      isTyping = false;
      socket.emit('typing:stop');
    }, 1500);
  });

  // ── RENDER MESSAGE ────────────────────────────────────
  function renderMessage(msg, prevMsg, prevEl) {
    const isMine  = msg.from === 'user' || msg.userId == USER_ID && msg.from !== 'admin';
    const isAdmin = msg.from === 'admin';
    const userName = msg.userName || (isAdmin ? 'Support' : USER_NAME);

    const wrapper = document.createElement('div');
    wrapper.className = `msg-wrapper ${isMine ? 'mine' : 'other'}`;

    // Add top margin if different sender than previous
    const prevFrom = prevMsg ? prevMsg.from : (prevEl ? prevEl.dataset.from : null);
    if (!prevFrom || prevFrom !== msg.from) wrapper.classList.add('mt');
    wrapper.dataset.from = msg.from;

    const time = formatTime(msg.timestamp);

    if (isMine) {
      wrapper.innerHTML = `
        <span class="msg-time">${time}</span>
        <div class="msg-bubble">${escapeHtml(msg.text)}</div>
      `;
    } else {
      // Admin / Support message
      wrapper.innerHTML = `
        <div class="msg-avatar" style="background:linear-gradient(135deg,#1a6fff,#a855f7);color:#fff;">S</div>
        <div class="msg-col">
          <div class="msg-sender-name">${escapeHtml(userName)}</div>
          <div style="display:flex;align-items:flex-end;gap:6px;">
            <div class="msg-bubble">${escapeHtml(msg.text)}</div>
            <span class="msg-time">${time}</span>
          </div>
        </div>
      `;
    }

    messagesEl.appendChild(wrapper);
  }

  // ── HELPERS ───────────────────────────────────────────
  function setAdminStatus(online) {
    if (online) {
      headerStatus.textContent = '● Online — Support available';
      headerStatus.style.color = '#22c55e';
      statusRing.className = 'status-ring online';
      statusDot.className  = 'status-dot online';
    } else {
      headerStatus.textContent = 'Support · We\'ll reply soon';
      headerStatus.style.color = 'rgba(255,255,255,0.35)';
      statusRing.className = 'status-ring offline';
      statusDot.className  = 'status-dot offline';
    }
  }

  function scrollToBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function formatTime(ts) {
    try {
      return new Date(ts).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
    } catch(e) { return ''; }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/\n/g,'<br>');
  }
</script>
</body>
</html>