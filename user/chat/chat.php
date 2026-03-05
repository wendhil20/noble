<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messenger</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Socket.io client — connects to our Node.js server -->
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Sora', sans-serif; box-sizing: border-box; }

    body {
      background: #0a0a0f;
      height: 100vh;
      display: flex;
      overflow: hidden;
      margin: 0;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: 280px;
      background: rgba(255,255,255,0.03);
      border-right: 1px solid rgba(255,255,255,0.06);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
    }

    .sidebar-header {
      padding: 20px 18px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .user-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700;
      font-size: 14px;
      flex-shrink: 0;
    }

    .online-dot {
      width: 10px; height: 10px;
      background: #22c55e;
      border-radius: 50%;
      border: 2px solid #0a0a0f;
      flex-shrink: 0;
    }

    .user-list-item {
      padding: 10px 18px;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: background 0.15s;
    }
    .user-list-item:hover { background: rgba(255,255,255,0.04); }

    /* ── MAIN CHAT ── */
    .chat-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .chat-header {
      padding: 16px 20px;
      background: rgba(255,255,255,0.02);
      border-bottom: 1px solid rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* ── MESSAGES ── */
    #messagesContainer {
      flex: 1;
      overflow-y: auto;
      padding: 20px 16px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    #messagesContainer::-webkit-scrollbar { width: 4px; }
    #messagesContainer::-webkit-scrollbar-track { background: transparent; }
    #messagesContainer::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

    /* Message bubble wrapper */
    .msg-wrapper {
      display: flex;
      align-items: flex-end;
      gap: 8px;
      max-width: 75%;
      animation: msgIn 0.2s ease-out;
    }
    @keyframes msgIn {
      from { opacity: 0; transform: translateY(8px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .msg-wrapper.mine  { align-self: flex-end; flex-direction: row-reverse; }
    .msg-wrapper.other { align-self: flex-start; }

    .msg-avatar {
      width: 28px; height: 28px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700;
      flex-shrink: 0;
      margin-bottom: 2px;
    }

    .msg-bubble {
      padding: 10px 14px;
      border-radius: 18px;
      font-size: 14px;
      line-height: 1.45;
      word-break: break-word;
      max-width: 100%;
    }

    /* MY messages — blue gradient */
    .msg-wrapper.mine .msg-bubble {
      background: linear-gradient(135deg, #0084ff, #0066dd);
      color: #fff;
      border-bottom-right-radius: 4px;
    }

    /* OTHER messages — dark glass */
    .msg-wrapper.other .msg-bubble {
      background: rgba(255,255,255,0.08);
      color: #e8e8f0;
      border-bottom-left-radius: 4px;
      border: 1px solid rgba(255,255,255,0.07);
    }

    /* Name label (for group) */
    .msg-name {
      font-size: 11px;
      font-weight: 600;
      margin-bottom: 3px;
      padding-left: 14px;
      opacity: 0.6;
    }

    /* Time */
    .msg-time {
      font-size: 10px;
      color: rgba(255,255,255,0.25);
      padding: 0 4px;
      white-space: nowrap;
    }

    /* System / event messages */
    .msg-system {
      text-align: center;
      font-size: 11px;
      color: rgba(255,255,255,0.25);
      padding: 6px 0;
      font-style: italic;
    }

    /* Typing indicator */
    #typingIndicator {
      padding: 0 20px 8px;
      font-size: 12px;
      color: rgba(255,255,255,0.35);
      height: 24px;
      font-style: italic;
    }

    /* ── INPUT BAR ── */
    .input-bar {
      padding: 12px 16px;
      border-top: 1px solid rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,0.02);
    }

    #msgInput {
      flex: 1;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 22px;
      padding: 11px 18px;
      color: #fff;
      font-size: 14px;
      outline: none;
      transition: all 0.2s;
      font-family: 'Sora', sans-serif;
      resize: none;
      max-height: 120px;
    }
    #msgInput:focus {
      border-color: rgba(0,132,255,0.5);
      background: rgba(0,132,255,0.06);
    }
    #msgInput::placeholder { color: rgba(255,255,255,0.25); }

    .send-btn {
      width: 42px; height: 42px;
      background: #0084ff;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: all 0.2s;
      flex-shrink: 0;
    }
    .send-btn:hover {
      background: #1a8fff;
      transform: scale(1.05);
      box-shadow: 0 4px 16px rgba(0,132,255,0.4);
    }
    .send-btn:active { transform: scale(0.97); }
    .send-btn:disabled { background: rgba(255,255,255,0.1); cursor: not-allowed; transform: none; }

    /* Connection status badge */
    #connStatus {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      padding: 3px 10px;
      border-radius: 20px;
      font-weight: 500;
    }
    #connStatus.connected    { background: rgba(34,197,94,0.15);  color: #22c55e; }
    #connStatus.disconnected { background: rgba(239,68,68,0.15);  color: #ef4444; }
    #connStatus.connecting   { background: rgba(234,179,8,0.15);  color: #eab308; }

    /* Avatar colors */
    .av-blue   { background: linear-gradient(135deg,#0084ff,#0044aa); color:#fff; }
    .av-purple { background: linear-gradient(135deg,#a855f7,#7c3aed); color:#fff; }
    .av-green  { background: linear-gradient(135deg,#22c55e,#16a34a); color:#fff; }
    .av-orange { background: linear-gradient(135deg,#f97316,#ea580c); color:#fff; }
    .av-pink   { background: linear-gradient(135deg,#ec4899,#db2777); color:#fff; }
    .av-cyan   { background: linear-gradient(135deg,#06b6d4,#0891b2); color:#fff; }

    @media (max-width: 640px) {
      .sidebar { display: none; }
    }
  </style>
</head>
<body>

  <!-- ══════════════ SIDEBAR ══════════════ -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <span style="color:#fff; font-weight:700; font-size:16px;">Messenger</span>
        <div id="connStatus" class="connecting">
          <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
          Connecting...
        </div>
      </div>

      <!-- Current user pill -->
      <div style="display:flex; align-items:center; gap:10px; background:rgba(0,132,255,0.1); border:1px solid rgba(0,132,255,0.2); border-radius:12px; padding:10px 12px;">
        <div id="myAvatar" class="user-avatar av-blue" style="width:32px;height:32px;font-size:13px;"></div>
        <div>
          <div id="myName" style="color:#fff; font-size:13px; font-weight:600;"></div>
          <div style="color:rgba(255,255,255,0.4); font-size:11px;">You</div>
        </div>
      </div>
    </div>

    <!-- Online users list -->
    <div style="padding:14px 18px 8px; color:rgba(255,255,255,0.3); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px;">
      Online — <span id="onlineCount">0</span>
    </div>
    <div id="userList" style="flex:1; overflow-y:auto;"></div>
  </div>

  <!-- ══════════════ CHAT AREA ══════════════ -->
  <div class="chat-area">

    <!-- Header -->
    <div class="chat-header">
      <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#0084ff,#a855f7);display:flex;align-items:center;justify-content:center;">
        <svg width="20" height="20" viewBox="0 0 32 32" fill="white">
          <path d="M16 2C8.268 2 2 7.87 2 15.07c0 4.2 2.07 7.95 5.31 10.41V30l4.85-2.66c1.29.36 2.66.55 4.08.55 7.732 0 14-5.87 14-13.07C30 7.87 23.732 2 16 2zm1.39 17.58L14 16.07l-6.5 3.51 7.14-7.58 3.39 3.51 6.5-3.51-7.14 7.58z"/>
        </svg>
      </div>
      <div>
        <div style="color:#fff; font-weight:600; font-size:14px;">General Chat</div>
        <div style="color:rgba(255,255,255,0.35); font-size:11px;" id="headerStatus">Connecting...</div>
      </div>
    </div>

    <!-- Messages -->
    <div id="messagesContainer"></div>

    <!-- Typing indicator -->
    <div id="typingIndicator"></div>

    <!-- Input bar -->
    <div class="input-bar">
      <input
        type="text"
        id="msgInput"
        placeholder="Aa"
        maxlength="500"
        autocomplete="off"
      />
      <button class="send-btn" id="sendBtn" disabled>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- ══════════════ JAVASCRIPT ══════════════ -->
  <script>
    // ── 0. GET USERNAME ────────────────────────────────────
    const MY_NAME = sessionStorage.getItem('messenger_user');
    if (!MY_NAME) {
      window.location.href = 'index-page4.php';
    }

    // Set my name in sidebar
    document.getElementById('myName').textContent = MY_NAME;
    document.getElementById('myAvatar').textContent = MY_NAME[0].toUpperCase();

    // ── 1. CONNECT TO WEBSOCKET SERVER ────────────────────
    // Change this URL if your Node.js server is on a different port
    const socket = io('http://localhost:3000');

    const messagesEl   = document.getElementById('messagesContainer');
    const msgInput     = document.getElementById('msgInput');
    const sendBtn      = document.getElementById('sendBtn');
    const typingEl     = document.getElementById('typingIndicator');
    const connStatus   = document.getElementById('connStatus');
    const userListEl   = document.getElementById('userList');
    const onlineCount  = document.getElementById('onlineCount');
    const headerStatus = document.getElementById('headerStatus');

    // Avatar color pool
    const AV_COLORS = ['av-blue','av-purple','av-green','av-orange','av-pink','av-cyan'];
    const userColorMap = {};
    function getColor(name) {
      if (!userColorMap[name]) {
        const idx = Object.keys(userColorMap).length % AV_COLORS.length;
        userColorMap[name] = AV_COLORS[idx];
      }
      return userColorMap[name];
    }
    // Assign my own color
    userColorMap[MY_NAME] = 'av-blue';
    document.getElementById('myAvatar').className = `user-avatar av-blue`;

    // ── 2. SOCKET EVENTS ──────────────────────────────────

    socket.on('connect', () => {
      console.log('✅ Connected to server');
      setStatus('connected', '● Connected');
      headerStatus.textContent = 'Connected · General Chat';
      sendBtn.disabled = false;

      // Tell server our username
      socket.emit('user:join', MY_NAME);
    });

    socket.on('disconnect', () => {
      setStatus('disconnected', '● Disconnected');
      headerStatus.textContent = 'Disconnected';
      sendBtn.disabled = true;
    });

    socket.on('connect_error', () => {
      setStatus('disconnected', '● Server offline');
      headerStatus.textContent = 'Cannot reach server';
    });

    // Receive full message history on join
    socket.on('history', (messages) => {
      messagesEl.innerHTML = '';
      if (messages.length === 0) {
        addSystemMsg('No messages yet. Say hello! 👋');
      } else {
        messages.forEach(renderMessage);
      }
      scrollToBottom();
    });

    // A new message arrives in real-time
    socket.on('message:new', (msg) => {
      renderMessage(msg);
      scrollToBottom();
    });

    // Someone joined
    socket.on('user:joined', (data) => {
      addSystemMsg(`${data.username} joined the chat`);
    });

    // Someone left
    socket.on('user:left', (data) => {
      addSystemMsg(`${data.username} left the chat`);
    });

    // Online users list updated
    socket.on('users:online', (users) => {
      onlineCount.textContent = users.length;
      userListEl.innerHTML = '';
      users.forEach(name => {
        if (name === MY_NAME) return; // skip self (shown in header already)
        const color = getColor(name);
        const div = document.createElement('div');
        div.className = 'user-list-item';
        div.innerHTML = `
          <div style="position:relative;">
            <div class="user-avatar ${color}" style="width:34px;height:34px;font-size:13px;">${name[0].toUpperCase()}</div>
            <div class="online-dot" style="position:absolute;bottom:0;right:0;"></div>
          </div>
          <span style="color:rgba(255,255,255,0.75); font-size:13px; font-weight:500;">${name}</span>
        `;
        userListEl.appendChild(div);
      });
    });

    // Typing indicators
    let typingTimeout;
    socket.on('typing:show', (name) => {
      typingEl.textContent = `${name} is typing...`;
      clearTimeout(typingTimeout);
      typingTimeout = setTimeout(() => { typingEl.textContent = ''; }, 3000);
    });
    socket.on('typing:hide', () => {
      typingEl.textContent = '';
    });

    // ── 3. SEND MESSAGE ───────────────────────────────────

    function sendMessage() {
      const text = msgInput.value.trim();
      if (!text || !socket.connected) return;

      // Emit to server — server will broadcast back to everyone
      socket.emit('message:send', { text });
      socket.emit('typing:stop');

      msgInput.value = '';
      msgInput.style.height = 'auto';
      isTyping = false;
    }

    sendBtn.addEventListener('click', sendMessage);

    msgInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Typing detection
    let isTyping = false;
    let typingStopTimer;
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

    // ── 4. RENDER HELPERS ─────────────────────────────────

    function renderMessage(msg) {
      const isMine = msg.user === MY_NAME;
      const color  = getColor(msg.user);
      const time   = formatTime(msg.timestamp);

      const wrapper = document.createElement('div');
      wrapper.className = `msg-wrapper ${isMine ? 'mine' : 'other'}`;

      if (isMine) {
        wrapper.innerHTML = `
          <span class="msg-time">${time}</span>
          <div class="msg-bubble">${escapeHtml(msg.text)}</div>
          <div class="msg-avatar ${color}">${msg.user[0].toUpperCase()}</div>
        `;
      } else {
        // Show name label for others
        const group = document.createElement('div');
        group.style.display = 'flex';
        group.style.flexDirection = 'column';
        group.innerHTML = `
          <span class="msg-name" style="color:${getNameColor(color)};">${msg.user}</span>
          <div style="display:flex;align-items:flex-end;gap:6px;">
            <div class="msg-bubble">${escapeHtml(msg.text)}</div>
            <span class="msg-time">${time}</span>
          </div>
        `;
        wrapper.innerHTML = `<div class="msg-avatar ${color}">${msg.user[0].toUpperCase()}</div>`;
        wrapper.appendChild(group);
      }

      messagesEl.appendChild(wrapper);
    }

    function addSystemMsg(text) {
      const div = document.createElement('div');
      div.className = 'msg-system';
      div.textContent = text;
      messagesEl.appendChild(div);
    }

    function setStatus(type, text) {
      connStatus.className = type;
      connStatus.innerHTML = `<span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>${text}`;
    }

    function scrollToBottom() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function formatTime(ts) {
      const d = new Date(ts);
      return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(str) {
      return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/\n/g, '<br>');
    }

    function getNameColor(avClass) {
      const map = {
        'av-blue':'#60a5fa', 'av-purple':'#c084fc', 'av-green':'#4ade80',
        'av-orange':'#fb923c', 'av-pink':'#f472b6', 'av-cyan':'#22d3ee'
      };
      return map[avClass] || '#fff';
    }
  </script>
</body>
</html>