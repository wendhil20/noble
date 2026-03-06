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
  <title>Support — Noble Home Depot</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <style>
    :root {
      --navy:    #0d1b2e;
      --navy2:   #142236;
      --navy3:   #1a2d45;
      --gold:    #c9a84c;
      --gold2:   #e8c96a;
      --cream:   #f5f0e8;
      --cream2:  #ede6d8;
      --text:    #1a1a2e;
      --muted:   #7a8090;
      --border:  #d8d0c0;
      --white:   #ffffff;
      --shadow:  0 4px 24px rgba(13,27,46,0.10);
      --shadow-lg: 0 12px 48px rgba(13,27,46,0.15);
    }

    html, body {
      background: var(--cream);
      color: var(--text);
    }

    /* Subtle linen texture */
    body::before {
      content: '';
      position: fixed; inset: 0; pointer-events: none; z-index: 0;
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a84c' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    .app {
      height: calc(100vh - 120px);
      display: flex;
      flex-direction: column;
      max-width: 780px;
      margin: 24px auto;
      position: relative; z-index: 1;
      background: var(--white);
      box-shadow: var(--shadow-lg);
      border-radius: 16px;
      overflow: hidden;
    }

    /* ── TOP BRAND BAR ── */
    .brand-bar {
      background: var(--navy);
      padding: 14px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
      border-bottom: 2px solid var(--gold);
    }
    .brand-wordmark {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-name {
      font-size: 17px; font-weight: 700;
      color: var(--cream);
      letter-spacing: 0.3px;
    }
    .brand-sub {
      font-size: 10px;
      color: var(--gold);
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-top: 1px;
    }
    .user-pill {
      display: flex; align-items: center; gap: 9px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(201,168,76,0.3);
      border-radius: 30px;
      padding: 6px 14px 6px 7px;
    }
    .user-pill-av {
      width: 24px; height: 24px; border-radius: 50%;
      object-fit: cover;
      border: 1.5px solid var(--gold);
    }
    .user-pill-av.initials {
      background: var(--gold);
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700; color: var(--navy);
    }
    .user-pill span {
      font-size: 12px; font-weight: 500;
      color: rgba(255,255,255,0.75);
    }

    /* ── SECTION LABEL ── */
    .section-label {
      padding: 32px 28px 0;
      flex-shrink: 0;
    }
    .section-label h2 {
      font-family: 'Playfair Display', serif;
      font-size: 22px; font-weight: 700;
      color: var(--navy);
    }
    .section-label p {
      font-size: 13px; color: var(--muted);
      margin-top: 4px; line-height: 1.5;
    }
    .gold-rule {
      width: 40px; height: 2px;
      background: var(--gold);
      margin: 10px 0 14px;
      border-radius: 2px;
    }

    /* ── PANELS ── */
    #adminPickPanel, #chatPanel {
      flex: 1; display: flex; flex-direction: column; overflow: hidden;
    }
    #chatPanel { display: none; }

    /* ── ADMIN LIST ── */
    #adminListContainer {
      flex: 1; overflow-y: auto;
      padding: 14px 20px 20px;
      display: flex; flex-direction: column; gap: 8px;
    }
    #adminListContainer::-webkit-scrollbar { width: 4px; }
    #adminListContainer::-webkit-scrollbar-thumb {
      background: var(--cream2); border-radius: 4px;
    }

    .admin-card {
      display: flex; align-items: center; gap: 16px;
      padding: 16px 20px;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
      position: relative;
      overflow: hidden;
    }
    .admin-card::before {
      content: '';
      position: absolute; left: 0; top: 0; bottom: 0;
      width: 3px; background: var(--gold);
      transform: scaleY(0); transform-origin: center;
      transition: transform 0.2s ease;
    }
    .admin-card:hover {
      border-color: var(--gold);
      box-shadow: 0 4px 20px rgba(201,168,76,0.15);
      transform: translateY(-1px);
    }
    .admin-card:hover::before { transform: scaleY(1); }

    .admin-av-wrap { position: relative; flex-shrink: 0; }
    .admin-av {
      width: 50px; height: 50px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; font-weight: 700; color: var(--white);
      border: 2px solid var(--cream2);
      transition: border-color 0.2s;
    }
    .admin-card:hover .admin-av { border-color: var(--gold); }
    .online-badge {
      position: absolute; bottom: 1px; right: 1px;
      width: 13px; height: 13px; border-radius: 50%;
      background: #22c55e; border: 2px solid var(--white);
    }
    .admin-info { flex: 1; }
    .admin-info-name {
      font-size: 15px; font-weight: 600; color: var(--navy);
    }
    .admin-info-title {
      font-size: 12px; color: var(--muted);
      margin-top: 3px;
    }
    .admin-info-title .tag {
      display: inline-block;
      background: rgba(201,168,76,0.12);
      color: var(--gold);
      border-radius: 4px; padding: 1px 6px;
      font-size: 10px; font-weight: 600;
      letter-spacing: 0.5px; text-transform: uppercase;
      margin-left: 6px;
    }
    .admin-arrow {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--cream);
      display: flex; align-items: center; justify-content: center;
      color: var(--muted); font-size: 14px;
      transition: all 0.2s;
    }
    .admin-card:hover .admin-arrow {
      background: var(--gold); color: var(--white);
    }

    .no-admins {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 10px; text-align: center; padding: 40px;
    }
    .no-admins-icon {
      width: 64px; height: 64px; border-radius: 50%;
      border: 2px dashed var(--border);
      display: flex; align-items: center; justify-content: center;
      font-size: 28px; margin-bottom: 6px;
    }
    .no-admins h3 { font-size: 16px; color: var(--navy); font-weight: 600; }
    .no-admins p  { font-size: 13px; color: var(--muted); line-height: 1.6; }

    /* ── CHAT PANEL ── */
    .chat-topbar {
      background: var(--navy);
      padding: 12px 20px;
      display: flex; align-items: center; gap: 14px;
      border-bottom: 2px solid var(--gold);
      flex-shrink: 0;
    }
    .back-btn {
      width: 36px; height: 36px; border-radius: 8px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all 0.15s; flex-shrink: 0;
      color: rgba(255,255,255,0.7);
    }
    .back-btn:hover { background: rgba(255,255,255,0.14); color: #fff; }
    .chat-admin-av-wrap { position: relative; flex-shrink: 0; }
    .chat-admin-av {
      width: 40px; height: 40px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; font-weight: 700; color: #fff;
      border: 2px solid rgba(201,168,76,0.5);
    }
    #chatStatusDot {
      position: absolute; bottom: 1px; right: 1px;
      width: 11px; height: 11px; border-radius: 50%;
      background: #22c55e; border: 2px solid var(--navy);
    }
    .chat-admin-meta { flex: 1; }
    .chat-admin-meta-name {
      font-size: 14px; font-weight: 600; color: #fff;
    }
    .chat-admin-meta-status {
      font-size: 11px; color: #22c55e; margin-top: 1px;
    }

    /* ── MESSAGES ── */
    #messagesContainer {
      flex: 1; overflow-y: auto;
      padding: 24px 24px 8px;
      display: flex; flex-direction: column; gap: 6px;
      scroll-behavior: smooth;
      background: var(--cream);
    }
    #messagesContainer::-webkit-scrollbar { width: 4px; }
    #messagesContainer::-webkit-scrollbar-thumb { background: var(--cream2); border-radius: 4px; }

    .msg-row {
      display: flex;
      align-items: flex-end;
      gap: 10px;
      animation: fadeUp 0.18s ease-out;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .msg-row.mine  { flex-direction: row-reverse; align-self: flex-end; max-width: 72%; }
    .msg-row.other { align-self: flex-start; max-width: 72%; }
    .msg-row.gap   { margin-top: 12px; }

    .msg-av-sm {
      width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-size: 10px; font-weight: 700; color: #fff; margin-bottom: 2px;
    }
    .msg-body { display: flex; flex-direction: column; gap: 2px; }
    .msg-label {
      font-size: 11px; color: var(--muted); font-weight: 500;
      padding: 0 4px;
    }
    .msg-row.mine .msg-label { text-align: right; }

    .msg-inner { display: flex; align-items: flex-end; gap: 8px; }
    .msg-row.mine .msg-inner { flex-direction: row-reverse; }

    .msg-bubble {
      padding: 11px 16px;
      font-size: 14px; line-height: 1.55;
      word-break: break-word;
      border-radius: 16px;
    }
    .msg-row.mine .msg-bubble {
      background: var(--navy);
      color: #fff;
      border-bottom-right-radius: 4px;
    }
    .msg-row.other .msg-bubble {
      background: var(--white);
      color: var(--text);
      border: 1px solid var(--border);
      border-bottom-left-radius: 4px;
    }
    .msg-time-sm {
      font-size: 10px; color: var(--muted); white-space: nowrap;
      padding-bottom: 2px; flex-shrink: 0;
    }

    .msg-system {
      text-align: center;
      font-size: 11px; color: var(--muted);
      padding: 10px 0; font-style: italic;
      display: flex; align-items: center; gap: 10px;
    }
    .msg-system::before, .msg-system::after {
      content: ''; flex: 1; height: 1px; background: var(--border);
    }

    /* ── TYPING ── */
    #typingIndicator {
      height: 28px;
      padding: 0 24px;
      display: flex; align-items: center; gap: 8px;
      background: var(--cream);
      font-size: 12px; color: var(--muted); font-style: italic;
    }
    .dots { display: flex; gap: 3px; align-items: center; }
    .dots span {
      width: 5px; height: 5px; border-radius: 50%;
      background: var(--gold);
      animation: hop 1.2s ease-in-out infinite;
    }
    .dots span:nth-child(2) { animation-delay: .2s; }
    .dots span:nth-child(3) { animation-delay: .4s; }
    @keyframes hop {
      0%,60%,100% { transform: translateY(0); opacity: .6; }
      30% { transform: translateY(-4px); opacity: 1; }
    }

    /* ── INPUT BAR ── */
    .input-bar {
      padding: 14px 20px;
      border-top: 1px solid var(--border);
      background: var(--white);
      display: flex; align-items: center; gap: 10px; flex-shrink: 0;
    }
    #msgInput {
      flex: 1;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 11px 16px;
      font-size: 14px; color: var(--text);
      outline: none; background: var(--cream);
      transition: all 0.2s;
      font-family: 'DM Sans', sans-serif;
    }
    #msgInput:focus {
      border-color: var(--gold);
      background: var(--white);
      box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
    }
    #msgInput::placeholder { color: var(--muted); }
    .send-btn {
      width: 44px; height: 44px; border-radius: 10px;
      background: var(--navy);
      border: none; cursor: pointer; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      transition: all 0.18s; color: var(--white);
    }
    .send-btn:hover:not(:disabled) {
      background: var(--navy3);
      box-shadow: 0 4px 16px rgba(13,27,46,0.3);
      transform: translateY(-1px);
    }
    .send-btn:disabled { background: var(--cream2); color: var(--muted); cursor: not-allowed; }

    /* Avatar palette */
    .av-a { background: linear-gradient(135deg, #0d1b2e, #1a2d45); }
    .av-b { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
    .av-c { background: linear-gradient(135deg, #0891b2, #0e7490); }
    .av-d { background: linear-gradient(135deg, #b45309, #92400e); }
    .av-e { background: linear-gradient(135deg, #be185d, #9d174d); }
    .av-f { background: linear-gradient(135deg, #1d4ed8, #1e40af); }
  </style>
</head>
<body class="font-roboto"> 
<?php include '../navbar/top.php' ?>

<div class="app">

  <!-- BRAND BAR -->
  <div class="brand-bar">
    <div class="brand-wordmark">
<div class="brand-icon bg-white rounded" >
        <img src="../img/logo.png" alt="Noble Home Depot" style="width:28px;height:28px;object-fit:contain;" />
      </div>
      <div>
        <div class="brand-name">Noble Home Depot</div>
        <div class="brand-sub">Client Support</div>
      </div>
    </div>
    <div class="user-pill">
      <?php if ($user_picture): ?>
        <img src="<?= htmlspecialchars($user_picture) ?>" class="user-pill-av" />
      <?php else: ?>
        <div class="user-pill-av initials"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
      <?php endif; ?>
      <span><?= htmlspecialchars($user_name) ?></span>
    </div>
  </div>

  <!-- ══ PANEL 1: PICK A REP ══ -->
  <div id="adminPickPanel">
    <div class="section-label">
      <h2>How can we help?</h2>
      <div class="gold-rule"></div>
      <p>Select a sales representative to begin a private conversation with our team.</p>
    </div>
    <div id="adminListContainer">
      <div class="no-admins" id="noAdminsMsg">
        <div class="no-admins-icon">🌙</div>
        <h3>No representatives online</h3>
        <p>Our team is currently unavailable.<br>Please check back during business hours.</p>
      </div>
    </div>
  </div>

  <!-- ══ PANEL 2: CHAT ══ -->
  <div id="chatPanel">

    <div class="chat-topbar">
      <div class="back-btn" onclick="backToAdminList()" title="Back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="chat-admin-av-wrap">
        <div class="chat-admin-av av-a" id="chatAdminAv">?
          <div id="chatStatusDot"></div>
        </div>
      </div>
      <div class="chat-admin-meta">
        <div class="chat-admin-meta-name" id="chatAdminName">—</div>
        <div class="chat-admin-meta-status" id="chatAdminStatus">● Online now</div>
      </div>
    </div>

    <div id="messagesContainer"></div>
    <div id="typingIndicator"></div>

    <div class="input-bar">
      <input type="text" id="msgInput" placeholder="Write your message…" maxlength="500" autocomplete="off" />
      <button class="send-btn" id="sendBtn" disabled>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
          <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>

</div>

<script>
  const USER_ID   = <?= json_encode((string)$user_id) ?>;
  const USER_NAME = <?= json_encode($user_name) ?>;

  const PALETTES = ['av-a','av-b','av-c','av-d','av-e','av-f'];
  function getColor(id) {
    const n = Math.abs(String(id).split('').reduce((a,c) => a + c.charCodeAt(0), 0));
    return PALETTES[n % PALETTES.length];
  }

  let selectedAdmin = null;
  let isTyping = false, typingTimer, typingHideTimer;

  const socket = io('https://support.noblehomedepot.com');

  socket.on('connect', () => {
    socket.emit('user:join', { userId: USER_ID, userName: USER_NAME, role: 'user' });
  });

  socket.on('disconnect', () => {
    document.getElementById('sendBtn').disabled = true;
  });

  socket.on('admins:list', (admins) => {
    renderAdminList(admins);
    if (selectedAdmin) {
      const still = admins.find(a => a.adminId == selectedAdmin.adminId);
      updateChatStatus(!!still);
    }
  });

  socket.on('history', (msgs) => {
    const el = document.getElementById('messagesContainer');
    el.innerHTML = '';
    if (!msgs || msgs.length === 0) {
      addSystemMsg('Conversation started — say hello!');
    } else {
      msgs.forEach((m, i) => { if (m && m.text) renderMessage(m, i > 0 ? msgs[i-1] : null); });
    }
    scrollToBottom();
  });

  socket.on('message:new', (msg) => {
    if (!msg || !msg.text) return;
    renderMessage(msg);
    scrollToBottom();
  });

  socket.on('typing:show', ({ userName }) => {
    const el = document.getElementById('typingIndicator');
    el.innerHTML = `
      <div class="dots"><span></span><span></span><span></span></div>
      <span>${escapeHtml(userName || 'Support')} is typing…</span>
    `;
    clearTimeout(typingHideTimer);
    typingHideTimer = setTimeout(() => { el.innerHTML = ''; }, 4000);
  });

  socket.on('typing:hide', () => {
    clearTimeout(typingHideTimer);
    document.getElementById('typingIndicator').innerHTML = '';
  });

  // ── RENDER ADMIN LIST ──
  function renderAdminList(admins) {
    const container = document.getElementById('adminListContainer');
    const noMsg = document.getElementById('noAdminsMsg');

    Array.from(container.children).forEach(c => { if (c.id !== 'noAdminsMsg') c.remove(); });

    if (!admins || admins.length === 0) {
      if (noMsg) noMsg.style.display = 'flex';
      return;
    }
    if (noMsg) noMsg.style.display = 'none';

    admins.forEach(admin => {
      const color   = getColor(admin.adminId);
      const initial = (admin.adminName || 'A')[0].toUpperCase();
      const card = document.createElement('div');
      card.className = 'admin-card';
      card.innerHTML = `
        <div class="admin-av-wrap">
          <div class="admin-av ${color}">${initial}</div>
          <div class="online-badge"></div>
        </div>
        <div class="admin-info">
          <div class="admin-info-name">${escapeHtml(admin.adminName)}</div>
          <div class="admin-info-title">
            ${escapeHtml(admin.title || 'Sales Representative')}
            <span class="tag">Online</span>
          </div>
        </div>
        <div class="admin-arrow">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      `;
      card.onclick = () => selectAdmin(admin, color);
      container.appendChild(card);
    });
  }

  // ── SELECT ADMIN ──
  function selectAdmin(admin, colorClass) {
    selectedAdmin = { ...admin, colorClass };

    const av = document.getElementById('chatAdminAv');
    av.className = `chat-admin-av ${colorClass}`;
    av.textContent = (admin.adminName || 'A')[0].toUpperCase();
    const dot = document.createElement('div');
    dot.id = 'chatStatusDot';
    av.appendChild(dot);

    document.getElementById('chatAdminName').textContent   = admin.adminName || 'Support';
    document.getElementById('chatAdminStatus').textContent = '● Online now';
    document.getElementById('chatAdminStatus').style.color = '#22c55e';

    document.getElementById('adminPickPanel').style.display = 'none';
    document.getElementById('chatPanel').style.display      = 'flex';
    document.getElementById('chatPanel').style.flexDirection = 'column';

    document.getElementById('sendBtn').disabled = false;
    document.getElementById('msgInput').focus();

    socket.emit('user:select-admin', { adminId: admin.adminId });
  }

  // ── BACK ──
  function backToAdminList() {
    selectedAdmin = null;
    document.getElementById('chatPanel').style.display      = 'none';
    document.getElementById('adminPickPanel').style.display = 'flex';
    document.getElementById('adminPickPanel').style.flexDirection = 'column';
    document.getElementById('messagesContainer').innerHTML  = '';
    document.getElementById('typingIndicator').innerHTML    = '';
    document.getElementById('sendBtn').disabled = true;
  }

  // ── SEND ──
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
    if (!isTyping) { isTyping = true; socket.emit('typing:start', { toAdminId: selectedAdmin.adminId }); }
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      isTyping = false;
      socket.emit('typing:stop', { toAdminId: selectedAdmin.adminId });
    }, 1500);
  });

  // ── RENDER MESSAGE ──
  function renderMessage(msg, prevMsg) {
    const isMine = msg.from === 'user';
    const row = document.createElement('div');
    row.className = `msg-row ${isMine ? 'mine' : 'other'}`;
    if (!prevMsg || prevMsg.from !== msg.from) row.classList.add('gap');

    const time     = formatTime(msg.timestamp);
    const userName = msg.userName || (isMine ? USER_NAME : 'Support');
    const color    = isMine ? getColor(USER_ID) : (selectedAdmin ? selectedAdmin.colorClass : 'av-a');

    row.innerHTML = `
      <div class="msg-av-sm ${color}">${(userName)[0].toUpperCase()}</div>
      <div class="msg-body">
        <div class="msg-label">${escapeHtml(userName)}</div>
        <div class="msg-inner">
          <div class="msg-bubble">${escapeHtml(msg.text)}</div>
          <span class="msg-time-sm">${time}</span>
        </div>
      </div>
    `;
    document.getElementById('messagesContainer').appendChild(row);
  }

  function addSystemMsg(text) {
    const d = document.createElement('div');
    d.className = 'msg-system';
    d.textContent = text;
    document.getElementById('messagesContainer').appendChild(d);
  }

  function updateChatStatus(online) {
    const el = document.getElementById('chatAdminStatus');
    if (!el) return;
    el.textContent = online ? '● Online now' : 'Offline';
    el.style.color = online ? '#22c55e' : 'rgba(255,255,255,0.35)';
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