<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

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
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { font-family: 'Sora', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0a0a0f; --surface: rgba(255,255,255,0.04);
      --border: rgba(255,255,255,0.07); --blue: #0084ff;
      --text: #e8e8f0; --muted: rgba(255,255,255,0.35);
    }
    html, body { height: 100%; background: var(--bg); overflow: hidden; color: var(--text); }

    /* Background grid */
    body::before {
      content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background-image: linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
      background-size: 40px 40px;
    }

    .app {
      height: 100vh; display: flex; flex-direction: column;
      max-width: 820px; margin: 0 auto;
      border-left: 1px solid var(--border); border-right: 1px solid var(--border);
      position: relative; z-index: 1;
    }

    /* ── HEADER ── */
    .header {
      display: flex; align-items: center; gap: 12px;
      padding: 13px 20px;
      background: rgba(10,10,15,0.9); backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border); flex-shrink: 0;
    }
    .header-avatar {
      width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 15px; color: #fff; position: relative;
    }
    .status-dot {
      width: 10px; height: 10px; border-radius: 50%;
      border: 2px solid var(--bg); position: absolute; bottom: 0; right: 0;
      background: rgba(255,255,255,0.2); transition: background 0.3s;
    }
    .status-dot.online { background: #22c55e; }

    /* ── PANELS ── */
    #adminPickPanel, #chatPanel { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    #chatPanel { display: none; }

    /* ── ADMIN PICK PANEL ── */
    .pick-header {
      padding: 28px 24px 16px;
      border-bottom: 1px solid var(--border);
    }
    .pick-header h2 { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 4px; }
    .pick-header p  { font-size: 13px; color: var(--muted); }

    #adminListContainer {
      flex: 1; overflow-y: auto; padding: 16px;
      display: flex; flex-direction: column; gap: 10px;
    }
    #adminListContainer::-webkit-scrollbar { width: 3px; }
    #adminListContainer::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

    .admin-card {
      display: flex; align-items: center; gap: 14px;
      padding: 14px 16px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 16px; cursor: pointer;
      transition: all 0.18s;
    }
    .admin-card:hover {
      background: rgba(0,132,255,0.08);
      border-color: rgba(0,132,255,0.25);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }
    .admin-card-avatar {
      width: 48px; height: 48px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 18px; color: #fff; flex-shrink: 0;
      position: relative;
    }
    .admin-card-dot {
      width: 12px; height: 12px; border-radius: 50%;
      background: #22c55e; border: 2px solid var(--bg);
      position: absolute; bottom: 1px; right: 1px;
    }
    .admin-card-info { flex: 1; }
    .admin-card-name { font-size: 15px; font-weight: 600; color: #fff; margin-bottom: 3px; }
    .admin-card-title { font-size: 12px; color: var(--muted); }
    .admin-card-arrow {
      color: rgba(255,255,255,0.2); font-size: 18px;
      transition: transform 0.18s; flex-shrink: 0;
    }
    .admin-card:hover .admin-card-arrow { transform: translateX(3px); color: var(--blue); }

    .no-admins {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 10px; text-align: center; padding: 40px;
    }
    .no-admins-icon {
      width: 60px; height: 60px; border-radius: 50%;
      background: rgba(255,255,255,0.04); border: 1px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      font-size: 26px; margin-bottom: 6px;
    }

    /* ── CHAT PANEL ── */
    .chat-header {
      display: flex; align-items: center; gap: 12px;
      padding: 13px 20px;
      background: rgba(10,10,15,0.85); backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border); flex-shrink: 0;
    }
    .back-btn {
      width: 34px; height: 34px; border-radius: 50%;
      background: rgba(255,255,255,0.06); border: 1px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; flex-shrink: 0; transition: all 0.15s;
    }
    .back-btn:hover { background: rgba(255,255,255,0.1); }
    .chat-admin-avatar {
      width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 14px; color: #fff; position: relative;
    }
    #chatStatusDot {
      width: 10px; height: 10px; border-radius: 50%;
      background: #22c55e; border: 2px solid var(--bg);
      position: absolute; bottom: 0; right: 0;
    }

    /* Messages */
    #messagesContainer {
      flex: 1; overflow-y: auto; padding: 20px 18px 10px;
      display: flex; flex-direction: column; gap: 4px;
      scroll-behavior: smooth;
    }
    #messagesContainer::-webkit-scrollbar { width: 3px; }
    #messagesContainer::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

    .msg-wrapper {
      display: flex; align-items: flex-end; gap: 8px;
      max-width: 78%; animation: msgIn 0.18s ease-out;
    }
    @keyframes msgIn {
      from { opacity: 0; transform: translateY(6px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .msg-wrapper.mine  { align-self: flex-end;  flex-direction: row-reverse; }
    .msg-wrapper.other { align-self: flex-start; }
    .msg-wrapper.mt    { margin-top: 10px; }

    .msg-av {
      width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700; margin-bottom: 2px;
    }
    .msg-col { display: flex; flex-direction: column; gap: 2px; }
    .msg-sender { font-size: 11px; color: rgba(255,255,255,0.38); padding-left: 12px; margin-bottom: 2px; }

    .msg-bubble {
      padding: 10px 14px; border-radius: 20px;
      font-size: 14px; line-height: 1.5; word-break: break-word;
    }
    .msg-wrapper.mine .msg-bubble {
      background: linear-gradient(135deg, #0084ff, #0055cc);
      color: #fff; border-bottom-right-radius: 5px;
    }
    .msg-wrapper.other .msg-bubble {
      background: rgba(255,255,255,0.08); color: var(--text);
      border: 1px solid var(--border); border-bottom-left-radius: 5px;
    }
    .msg-time { font-size: 10px; color: rgba(255,255,255,0.2); padding: 0 4px; white-space: nowrap; }
    .msg-system {
      text-align: center; font-size: 11px;
      color: rgba(255,255,255,0.2); padding: 8px 0; font-style: italic;
    }

    /* Typing */
    #typingIndicator {
      padding: 0 20px 8px; height: 26px;
      font-size: 12px; color: var(--muted); font-style: italic;
      display: flex; align-items: center; gap: 6px;
    }
    .typing-dots { display: flex; gap: 3px; align-items: center; }
    .typing-dots span {
      width: 5px; height: 5px; background: var(--muted); border-radius: 50%;
      animation: bounce 1.2s ease-in-out infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-4px); } }

    /* Input */
    .input-bar {
      padding: 12px 16px; border-top: 1px solid var(--border);
      background: rgba(10,10,15,0.9);
      display: flex; align-items: center; gap: 10px; flex-shrink: 0;
    }
    #msgInput {
      flex: 1; background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1); border-radius: 24px;
      padding: 11px 18px; color: #fff; font-size: 14px;
      outline: none; transition: all 0.2s; font-family: 'Sora', sans-serif;
    }
    #msgInput:focus {
      border-color: rgba(0,132,255,0.45); background: rgba(0,132,255,0.06);
      box-shadow: 0 0 0 3px rgba(0,132,255,0.1);
    }
    #msgInput::placeholder { color: rgba(255,255,255,0.22); }
    .send-btn {
      width: 42px; height: 42px; background: var(--blue); border: none;
      border-radius: 50%; cursor: pointer; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; transition: all 0.18s;
    }
    .send-btn:hover:not(:disabled) {
      background: #1a8fff; transform: scale(1.06);
      box-shadow: 0 4px 18px rgba(0,132,255,0.45);
    }
    .send-btn:disabled { background: rgba(255,255,255,0.08); cursor: not-allowed; }

    /* Avatar color palette */
    .av-0 { background: linear-gradient(135deg,#0084ff,#0044aa); }
    .av-1 { background: linear-gradient(135deg,#a855f7,#7c3aed); }
    .av-2 { background: linear-gradient(135deg,#22c55e,#16a34a); }
    .av-3 { background: linear-gradient(135deg,#f97316,#ea580c); }
    .av-4 { background: linear-gradient(135deg,#ec4899,#db2777); }
    .av-5 { background: linear-gradient(135deg,#06b6d4,#0891b2); }
  </style>
</head>
<body>
<div class="app">

  <!-- ── MAIN HEADER ── -->
  <div class="header">
    <div class="header-avatar" style="background:linear-gradient(135deg,#0084ff,#a855f7);">
      N
    </div>
    <div style="flex:1;">
      <div style="font-size:15px;font-weight:700;color:#fff;">Noble Home Depot</div>
      <div style="font-size:11px;color:var(--muted);">Support &amp; Sales</div>
    </div>
    <!-- Logged in user pill -->
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

  <!-- ══ PANEL 1: PICK AN ADMIN ══ -->
  <div id="adminPickPanel">
    <div class="pick-header">
      <h2>Talk to our team 👋</h2>
      <p>Choose a sales representative to start a private conversation.</p>
    </div>
    <div id="adminListContainer">
      <div class="no-admins" id="noAdminsMsg">
        <div class="no-admins-icon">😴</div>
        <div style="font-size:15px;font-weight:600;color:rgba(255,255,255,0.5);">No one online right now</div>
        <div style="font-size:13px;color:rgba(255,255,255,0.25);line-height:1.6;">Our team is currently offline.<br>Please check back later.</div>
      </div>
    </div>
  </div>

  <!-- ══ PANEL 2: CHAT ══ -->
  <div id="chatPanel">
    <div class="chat-header">
      <div class="back-btn" onclick="backToAdminList()" title="Back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18l-6-6 6-6" stroke="rgba(255,255,255,0.7)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="chat-admin-avatar av-0" id="chatAdminAvatar">
        ?
        <div id="chatStatusDot"></div>
      </div>
      <div style="flex:1;">
        <div style="font-size:14px;font-weight:600;color:#fff;" id="chatAdminName">—</div>
        <div style="font-size:11px;color:#22c55e;" id="chatAdminStatus">● Online</div>
      </div>
    </div>

    <div id="messagesContainer"></div>
    <div id="typingIndicator"></div>

    <div class="input-bar">
      <input type="text" id="msgInput" placeholder="Type a message..." maxlength="500" autocomplete="off" />
      <button class="send-btn" id="sendBtn" disabled>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
          <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>

</div>

<script>
  const USER_ID   = <?= json_encode((string)$user_id) ?>;
  const USER_NAME = <?= json_encode($user_name) ?>;

  const COLORS = ['av-0','av-1','av-2','av-3','av-4','av-5'];
  function getColor(id) {
    const idx = Math.abs(String(id).split('').reduce((a,c) => a + c.charCodeAt(0), 0)) % COLORS.length;
    return COLORS[idx];
  }

  let selectedAdmin = null; // { adminId, adminName, colorClass }
  let isTyping = false, typingTimer;

  const socket = io('https://support.noblehomedepot.com');

  // ── CONNECT ──────────────────────────────────────────────
  socket.on('connect', () => {
    socket.emit('user:join', {
      userId:   USER_ID,
      userName: USER_NAME,
      role:     'user'
    });
  });

  socket.on('disconnect', () => {
    document.getElementById('sendBtn').disabled = true;
  });

  // ── ADMIN LIST (+ handle admin going offline while in chat) ─
  socket.on('admins:list', (admins) => {
    renderAdminList(admins);
    if (selectedAdmin) {
      const stillOnline = admins.find(a => a.adminId == selectedAdmin.adminId);
      updateChatStatus(!!stillOnline);
    }
  });

  // ── HISTORY ──────────────────────────────────────────────
  socket.on('history', (msgs) => {
    const el = document.getElementById('messagesContainer');
    el.innerHTML = '';
    if (!msgs || msgs.length === 0) {
      addSystemMsg('No messages yet. Say hello! 👋');
    } else {
      msgs.forEach((m, i) => { if (m && m.text) renderMessage(m, i > 0 ? msgs[i-1] : null); });
    }
    scrollToBottom();
  });

  // ── NEW MESSAGE ──────────────────────────────────────────
  socket.on('message:new', (msg) => {
    if (!msg || !msg.text) return;
    renderMessage(msg);
    scrollToBottom();
  });

  // ── TYPING ───────────────────────────────────────────────
  let typingHideTimer;
  socket.on('typing:show', ({ userName }) => {
    const el = document.getElementById('typingIndicator');
    el.innerHTML = `
      <div class="typing-dots"><span></span><span></span><span></span></div>
      <span style="font-size:12px;color:rgba(255,255,255,0.3);">${escapeHtml(userName || 'Support')} is typing...</span>
    `;
    clearTimeout(typingHideTimer);
    typingHideTimer = setTimeout(() => { el.innerHTML = ''; }, 4000);
  });

  socket.on('typing:hide', () => {
    document.getElementById('typingIndicator').innerHTML = '';
    clearTimeout(typingHideTimer);
  });

  // ── RENDER ADMIN LIST ────────────────────────────────────
  function renderAdminList(admins) {
    const container = document.getElementById('adminListContainer');
    const noMsg = document.getElementById('noAdminsMsg');

    if (!admins || admins.length === 0) {
      // Remove all cards but keep noAdminsMsg in DOM
      Array.from(container.children).forEach(child => {
        if (child.id !== 'noAdminsMsg') child.remove();
      });
      if (noMsg) noMsg.style.display = 'flex';
      return;
    }

    // Hide no-admins message, remove old cards only
    if (noMsg) noMsg.style.display = 'none';
    Array.from(container.children).forEach(child => {
      if (child.id !== 'noAdminsMsg') child.remove();
    });

    admins.forEach(admin => {
      const color = getColor(admin.adminId);
      const initial = (admin.adminName || 'A')[0].toUpperCase();
      const card = document.createElement('div');
      card.className = 'admin-card';
      card.innerHTML = `
        <div class="admin-card-avatar ${color}">
          ${initial}
          <div class="admin-card-dot"></div>
        </div>
        <div class="admin-card-info">
          <div class="admin-card-name">${escapeHtml(admin.adminName)}</div>
          <div class="admin-card-title">${escapeHtml(admin.title || 'Sales Representative')} · <span style="color:#22c55e;">● Online</span></div>
        </div>
        <div class="admin-card-arrow">›</div>
      `;
      card.onclick = () => selectAdmin(admin, color);
      container.appendChild(card);
    });
  }

  // ── SELECT ADMIN → OPEN CHAT ─────────────────────────────
  function selectAdmin(admin, colorClass) {
    selectedAdmin = { ...admin, colorClass };

    // Update chat header
    const av = document.getElementById('chatAdminAvatar');
    av.className = `chat-admin-avatar ${colorClass}`;
    av.innerHTML = `${(admin.adminName || 'A')[0].toUpperCase()}<div id="chatStatusDot" class="status-dot online"></div>`;
    document.getElementById('chatAdminName').textContent   = admin.adminName || 'Support';
    document.getElementById('chatAdminStatus').textContent = '● Online';
    document.getElementById('chatAdminStatus').style.color = '#22c55e';

    // Switch panels
    document.getElementById('adminPickPanel').style.display = 'none';
    document.getElementById('chatPanel').style.display      = 'flex';
    document.getElementById('chatPanel').style.flexDirection = 'column';

    // Enable input
    document.getElementById('sendBtn').disabled = false;
    document.getElementById('msgInput').focus();

    // Tell server user selected this admin
    socket.emit('user:select-admin', { adminId: admin.adminId });
  }

  // ── BACK TO ADMIN LIST ───────────────────────────────────
  function backToAdminList() {
    selectedAdmin = null;
    document.getElementById('chatPanel').style.display      = 'none';
    document.getElementById('adminPickPanel').style.display = 'flex';
    document.getElementById('adminPickPanel').style.flexDirection = 'column';
    document.getElementById('messagesContainer').innerHTML  = '';
    document.getElementById('typingIndicator').innerHTML    = '';
    document.getElementById('sendBtn').disabled = true;
  }

  // ── SEND MESSAGE ─────────────────────────────────────────
  function sendMessage() {
    const text = document.getElementById('msgInput').value.trim();
    if (!text || !socket.connected || !selectedAdmin) return;

    socket.emit('message:send', { text });
    socket.emit('typing:stop');

    document.getElementById('msgInput').value = '';
    isTyping = false;
    clearTimeout(typingTimer);
  }

  document.getElementById('sendBtn').addEventListener('click', sendMessage);
  document.getElementById('msgInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  document.getElementById('msgInput').addEventListener('input', () => {
    if (!selectedAdmin) return;
    if (!isTyping) {
      isTyping = true;
      socket.emit('typing:start', { toAdminId: selectedAdmin.adminId });
    }
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      isTyping = false;
      socket.emit('typing:stop', { toAdminId: selectedAdmin.adminId });
    }, 1500);
  });

  // ── RENDER MESSAGE ───────────────────────────────────────
  function renderMessage(msg, prevMsg) {
    const isMine = msg.from === 'user';
    const wrapper = document.createElement('div');
    wrapper.className = `msg-wrapper ${isMine ? 'mine' : 'other'}`;
    if (!prevMsg || prevMsg.from !== msg.from) wrapper.classList.add('mt');
    wrapper.dataset.from = msg.from;

    const time     = formatTime(msg.timestamp);
    const userName = msg.userName || (isMine ? USER_NAME : 'Support');
    const color    = isMine ? getColor(USER_ID) : (selectedAdmin ? selectedAdmin.colorClass : 'av-0');

    if (isMine) {
      wrapper.innerHTML = `
        <span class="msg-time">${time}</span>
        <div class="msg-bubble">${escapeHtml(msg.text)}</div>
        <div class="msg-av ${color}">${USER_NAME[0].toUpperCase()}</div>
      `;
    } else {
      wrapper.innerHTML = `
        <div class="msg-av ${color}">${(userName)[0].toUpperCase()}</div>
        <div class="msg-col">
          <div class="msg-sender">${escapeHtml(userName)}</div>
          <div style="display:flex;align-items:flex-end;gap:6px;">
            <div class="msg-bubble">${escapeHtml(msg.text)}</div>
            <span class="msg-time">${time}</span>
          </div>
        </div>
      `;
    }
    document.getElementById('messagesContainer').appendChild(wrapper);
  }

  function addSystemMsg(text) {
    const d = document.createElement('div');
    d.className = 'msg-system';
    d.textContent = text;
    document.getElementById('messagesContainer').appendChild(d);
  }

  function updateChatStatus(online) {
    document.getElementById('chatAdminStatus').textContent = online ? '● Online' : 'Offline';
    document.getElementById('chatAdminStatus').style.color = online ? '#22c55e' : 'rgba(255,255,255,0.35)';
  }

  function scrollToBottom() {
    const el = document.getElementById('messagesContainer');
    el.scrollTop = el.scrollHeight;
  }

  function formatTime(ts) {
    try { return new Date(ts).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }); }
    catch(e) { return ''; }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/\n/g,'<br>');
  }
</script>
</body>
</html>