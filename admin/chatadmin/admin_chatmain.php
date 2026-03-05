<?php
session_name("nobleadmin");
session_start();
include '../connection/connect.php';

if (!isset($_SESSION['noble_user'])) {
    header('Location: ../admin/index.php');
    exit;
}

$admin_id   = $_SESSION['noble_id'];
$admin_name = $_SESSION['noble_user'];
$admin_lvl  = $_SESSION['noble_lvl'] ?? 'Sales';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Chat Support</title>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { font-family: 'Sora', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg: #0a0a0f; --border: rgba(255,255,255,0.06);
      --surface: rgba(255,255,255,0.03); --blue: #0084ff;
      --text: #e8e8f0; --muted: rgba(255,255,255,0.3);
    }
    html, body { height: 100vh; background: var(--bg); display: flex; overflow: hidden; margin: 0; }

    /* ── SIDEBAR ── */
    .sidebar {
      width: 300px; background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex; flex-direction: column; flex-shrink: 0;
    }
    .sidebar-header {
      padding: 16px 18px; border-bottom: 1px solid var(--border);
    }
    .conv-item {
      padding: 12px 16px; display: flex; align-items: center; gap: 10px;
      cursor: pointer; transition: background 0.15s;
      border-bottom: 1px solid rgba(255,255,255,0.04); position: relative;
    }
    .conv-item:hover  { background: rgba(255,255,255,0.05); }
    .conv-item.active { background: rgba(0,132,255,0.1); border-left: 3px solid var(--blue); }

    .conv-avatar {
      width: 42px; height: 42px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; color: #fff; font-size: 15px;
      flex-shrink: 0; position: relative;
    }
    .online-dot {
      width: 11px; height: 11px; background: #22c55e;
      border-radius: 50%; border: 2px solid var(--bg);
      position: absolute; bottom: 0; right: 0;
    }
    .unread-badge {
      background: #ef4444; color: #fff; border-radius: 50%;
      min-width: 18px; height: 18px; font-size: 10px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      padding: 0 4px; margin-left: auto; flex-shrink: 0;
    }

    /* ── CHAT AREA ── */
    .chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .chat-header {
      padding: 14px 20px; background: rgba(255,255,255,0.02);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 12px;
    }

    #messagesContainer {
      flex: 1; overflow-y: auto; padding: 20px 16px;
      display: flex; flex-direction: column; gap: 6px;
    }
    #messagesContainer::-webkit-scrollbar { width: 4px; }
    #messagesContainer::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

    .msg-wrapper {
      display: flex; align-items: flex-end; gap: 8px;
      max-width: 75%; animation: msgIn 0.2s ease-out;
    }
    @keyframes msgIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
    .msg-wrapper.mine  { align-self: flex-end;  flex-direction: row-reverse; }
    .msg-wrapper.other { align-self: flex-start; }
    .msg-wrapper.mt    { margin-top: 10px; }

    .msg-bubble {
      padding: 10px 14px; border-radius: 18px;
      font-size: 14px; line-height: 1.45; word-break: break-word;
    }
    .msg-wrapper.mine .msg-bubble  {
      background: linear-gradient(135deg,#0084ff,#0066dd);
      color: #fff; border-bottom-right-radius: 4px;
    }
    .msg-wrapper.other .msg-bubble {
      background: rgba(255,255,255,0.09); color: var(--text);
      border-bottom-left-radius: 4px; border: 1px solid rgba(255,255,255,0.07);
    }
    .msg-time   { font-size:10px; color:rgba(255,255,255,0.25); padding:0 4px; white-space:nowrap; }
    .msg-system { text-align:center; font-size:11px; color:rgba(255,255,255,0.25); padding:6px 0; font-style:italic; }

    #typingIndicator {
      padding: 4px 20px; font-size: 12px; color: rgba(255,255,255,0.35);
      height: 24px; font-style: italic;
    }

    .input-bar {
      padding: 12px 16px; border-top: 1px solid var(--border);
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,0.02);
    }
    #msgInput {
      flex: 1; background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.1); border-radius: 22px;
      padding: 11px 18px; color: #fff; font-size: 14px;
      outline: none; transition: all 0.2s; font-family: 'Sora', sans-serif;
    }
    #msgInput:focus { border-color: rgba(0,132,255,0.5); background: rgba(0,132,255,0.06); }
    #msgInput::placeholder { color: rgba(255,255,255,0.25); }
    #msgInput:disabled { opacity: 0.4; cursor: not-allowed; }

    .send-btn {
      width:42px; height:42px; background: var(--blue); border:none;
      border-radius:50%; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      transition:all 0.2s; flex-shrink:0;
    }
    .send-btn:hover:not(:disabled) {
      background:#1a8fff; transform:scale(1.05);
      box-shadow:0 4px 16px rgba(0,132,255,0.4);
    }
    .send-btn:disabled { background:rgba(255,255,255,0.1); cursor:not-allowed; transform:none; }

    .empty-state {
      flex:1; display:flex; flex-direction:column;
      align-items:center; justify-content:center;
      color:rgba(255,255,255,0.2); text-align:center; gap:10px;
    }

    /* Avatar palette */
    .av-0 { background: linear-gradient(135deg,#0084ff,#0044aa); }
    .av-1 { background: linear-gradient(135deg,#a855f7,#7c3aed); }
    .av-2 { background: linear-gradient(135deg,#22c55e,#16a34a); }
    .av-3 { background: linear-gradient(135deg,#f97316,#ea580c); }
    .av-4 { background: linear-gradient(135deg,#ec4899,#db2777); }
    .av-5 { background: linear-gradient(135deg,#06b6d4,#0891b2); }

    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }
    .new-msg-pulse { animation: pulse 1s ease-in-out 3; }
  </style>
</head>
<body>

  <!-- ══ SIDEBAR ══ -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#0084ff,#a855f7);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:14px;">
          <?= strtoupper(substr($admin_name, 0, 1)) ?>
        </div>
        <div>
          <div style="color:#fff;font-weight:600;font-size:13px;"><?= htmlspecialchars($admin_name) ?></div>
          <div style="color:var(--muted);font-size:11px;"><?= htmlspecialchars($admin_lvl) ?></div>
        </div>
        <div id="connDot" style="width:8px;height:8px;border-radius:50%;background:#eab308;margin-left:auto;flex-shrink:0;" title="Connecting..."></div>
      </div>
      <div style="color:var(--muted);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;">
        My Conversations — <span id="convCount">0</span>
      </div>
    </div>

    <!-- Search -->
    <div style="padding:10px 14px;border-bottom:1px solid var(--border);">
      <input type="text" id="searchInput" placeholder="Search users..."
        style="width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:8px 12px;color:#fff;font-size:13px;outline:none;font-family:'Sora',sans-serif;" />
    </div>

    <!-- Conversation list -->
    <div id="convList" style="flex:1;overflow-y:auto;">
      <div style="padding:40px 20px;text-align:center;color:rgba(255,255,255,0.2);font-size:13px;">
        Waiting for users to connect...
      </div>
    </div>
  </div>

  <!-- ══ CHAT AREA ══ -->
  <div class="chat-area">

    <!-- Empty state -->
    <div class="empty-state" id="emptyState">
      <svg width="52" height="52" viewBox="0 0 32 32" fill="rgba(255,255,255,0.1)">
        <path d="M16 2C8.268 2 2 7.87 2 15.07c0 4.2 2.07 7.95 5.31 10.41V30l4.85-2.66c1.29.36 2.66.55 4.08.55 7.732 0 14-5.87 14-13.07C30 7.87 23.732 2 16 2zm1.39 17.58L14 16.07l-6.5 3.51 7.14-7.58 3.39 3.51 6.5-3.51-7.14 7.58z"/>
      </svg>
      <div style="font-size:15px;font-weight:500;color:rgba(255,255,255,0.25);">Select a conversation</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.15);margin-top:4px;">Users who choose to chat with you will appear on the left</div>
    </div>

    <!-- Active chat view -->
    <div id="chatView" style="display:none;flex:1;flex-direction:column;overflow:hidden;">

      <div class="chat-header">
        <div class="conv-avatar av-0" id="activeAvatar" style="width:38px;height:38px;font-size:14px;">?</div>
        <div style="flex:1;">
          <div style="color:#fff;font-weight:600;font-size:14px;" id="activeName">—</div>
          <div style="font-size:11px;" id="activeStatus">—</div>
        </div>
      </div>

      <div id="messagesContainer"></div>
      <div id="typingIndicator"></div>

      <div class="input-bar">
        <input type="text" id="msgInput" placeholder="Reply..." maxlength="500" autocomplete="off" disabled />
        <button class="send-btn" id="sendBtn" disabled>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
    </div>

  </div>

  <script>
    const ADMIN_ID   = <?= json_encode((string)$admin_id) ?>;
    const ADMIN_NAME = <?= json_encode($admin_name) ?>;
    const ADMIN_LVL  = <?= json_encode($admin_lvl) ?>;

    let activeUserId = null;
    let allConversations = [];
    const colorMap = {};
    let colorIdx = 0;
    const COLORS = ['av-0','av-1','av-2','av-3','av-4','av-5'];

    function getColor(userId) {
      if (!colorMap[userId]) { colorMap[userId] = COLORS[colorIdx++ % COLORS.length]; }
      return colorMap[userId];
    }

    const socket = io('https://support.noblehomedepot.com');

    // ── CONNECT ──────────────────────────────────────────────
    socket.on('connect', () => {
      document.getElementById('connDot').style.background = '#22c55e';
      document.getElementById('connDot').title = 'Connected';
      socket.emit('user:join', {
        userId:   ADMIN_ID,   // clean number ID — no prefix needed, role:'admin' distinguishes it
        userName: ADMIN_NAME,
        role:     'admin',
        title:    ADMIN_LVL
      });
    });

    socket.on('disconnect', () => {
      document.getElementById('connDot').style.background = '#ef4444';
    });

    // ── MY CONVERSATION LIST (only users who picked me) ──────
    socket.on('admin:conversations', (convs) => {
      allConversations = convs;
      renderConvList(convs);
    });

    // ── HISTORY ──────────────────────────────────────────────
    socket.on('history', (msgs) => {
      const el = document.getElementById('messagesContainer');
      el.innerHTML = '';
      if (!msgs || msgs.length === 0) {
        addSystemMsg('No messages yet.');
      } else {
        msgs.forEach((m, i) => { if (m && m.text) renderMessage(m, i > 0 ? msgs[i-1] : null); });
      }
      scrollToBottom();
    });

    // ── NEW MESSAGE (only from users who picked me) ───────────
    socket.on('message:new', (msg) => {
      if (!msg || !msg.text) return;
      if (msg.userId == activeUserId) {
        renderMessage(msg);
        scrollToBottom();
      }
    });

    // ── USER ONLINE / OFFLINE ─────────────────────────────────
    socket.on('user:online', ({ userId, userName }) => {
      if (userId == activeUserId) setActiveStatus(true);
      updateConvOnlineDot(userId, true);
    });
    socket.on('user:offline', ({ userId }) => {
      if (userId == activeUserId) setActiveStatus(false);
      updateConvOnlineDot(userId, false);
    });

    // ── TYPING ────────────────────────────────────────────────
    socket.on('typing:show', ({ userId }) => {
      if (userId == activeUserId) {
        document.getElementById('typingIndicator').textContent = 'User is typing...';
      }
    });
    socket.on('typing:hide', ({ userId }) => {
      if (userId == activeUserId) document.getElementById('typingIndicator').textContent = '';
    });

    // ── OPEN CONVERSATION ─────────────────────────────────────
    function openConversation(userId, userName, colorClass, isOnline) {
      activeUserId = userId;

      document.getElementById('emptyState').style.display = 'none';
      const cv = document.getElementById('chatView');
      cv.style.display = 'flex'; cv.style.flexDirection = 'column';
      cv.style.overflow = 'hidden'; cv.style.flex = '1';

      document.getElementById('activeAvatar').className = `conv-avatar ${colorClass}`;
      document.getElementById('activeAvatar').textContent = (userName || 'U')[0].toUpperCase();
      document.getElementById('activeName').textContent   = userName || 'Unknown';
      setActiveStatus(isOnline);

      document.getElementById('msgInput').disabled = false;
      document.getElementById('sendBtn').disabled  = false;
      document.getElementById('msgInput').placeholder = `Reply to ${userName || 'user'}...`;
      document.getElementById('typingIndicator').textContent = '';

      document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
      const item = document.querySelector(`[data-userid="${userId}"]`);
      if (item) item.classList.add('active');

      socket.emit('admin:open', { userId });
    }

    // ── SEND REPLY ────────────────────────────────────────────
    function sendReply() {
      const text = document.getElementById('msgInput').value.trim();
      if (!text || !activeUserId || !socket.connected) return;
      socket.emit('admin:reply', { toUserId: activeUserId, text });
      socket.emit('typing:stop', { toUserId: activeUserId });
      document.getElementById('msgInput').value = '';
      isTyping = false;
    }

    document.getElementById('sendBtn').addEventListener('click', sendReply);
    document.getElementById('msgInput').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
    });

    let isTyping = false, typingTimer;
    document.getElementById('msgInput').addEventListener('input', () => {
      if (!activeUserId) return;
      if (!isTyping) { isTyping = true; socket.emit('typing:start', { toUserId: activeUserId }); }
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => {
        isTyping = false;
        socket.emit('typing:stop', { toUserId: activeUserId });
      }, 1500);
    });

    // ── SEARCH ────────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      renderConvList(allConversations.filter(c => (c.userName || '').toLowerCase().includes(q)));
    });

    // ── RENDER HELPERS ────────────────────────────────────────
    function renderConvList(convs) {
      const el = document.getElementById('convList');
      document.getElementById('convCount').textContent = convs.length;

      if (!convs || convs.length === 0) {
        el.innerHTML = `<div style="padding:40px 20px;text-align:center;color:rgba(255,255,255,0.2);font-size:13px;">No conversations yet</div>`;
        return;
      }

      el.innerHTML = '';
      convs.forEach(conv => {
        const color    = getColor(conv.userId);
        const userName = conv.userName || 'Unknown';
        const div = document.createElement('div');
        div.className = `conv-item${activeUserId == conv.userId ? ' active' : ''}`;
        div.setAttribute('data-userid', conv.userId);
        div.onclick = () => openConversation(conv.userId, userName, color, conv.isOnline);
        div.innerHTML = `
          <div class="conv-avatar ${color}" style="width:42px;height:42px;">
            ${userName[0].toUpperCase()}
            ${conv.isOnline ? '<div class="online-dot"></div>' : ''}
          </div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:3px;">
              <span style="color:#fff;font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(userName)}</span>
              <span style="color:rgba(255,255,255,0.25);font-size:10px;flex-shrink:0;margin-left:6px;">${conv.lastTime ? formatTime(conv.lastTime) : ''}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="color:rgba(255,255,255,0.4);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">${escapeHtml(conv.lastMessage || 'No messages yet')}</span>
              ${conv.unread > 0 ? `<span class="unread-badge new-msg-pulse">${conv.unread}</span>` : ''}
            </div>
          </div>
        `;
        el.appendChild(div);
      });
    }

    function renderMessage(msg, prevMsg) {
      if (!msg || !msg.text) return;
      const isMine  = msg.from === 'admin';
      const wrapper = document.createElement('div');
      wrapper.className = `msg-wrapper ${isMine ? 'mine' : 'other'}`;
      if (!prevMsg || prevMsg.from !== msg.from) wrapper.classList.add('mt');
      wrapper.dataset.from = msg.from;

      const time     = formatTime(msg.timestamp);
      const userName = msg.userName || 'User';
      const color    = getColor(msg.userId);

      if (isMine) {
        wrapper.innerHTML = `
          <span class="msg-time">${time}</span>
          <div class="msg-bubble">${escapeHtml(msg.text)}</div>
          <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#0084ff,#a855f7);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;">
            ${ADMIN_NAME[0].toUpperCase()}
          </div>
        `;
      } else {
        wrapper.innerHTML = `
          <div class="conv-avatar ${color}" style="width:28px;height:28px;font-size:11px;">${userName[0].toUpperCase()}</div>
          <div>
            <div style="font-size:11px;color:rgba(255,255,255,0.35);margin-bottom:3px;padding-left:12px;">${escapeHtml(userName)}</div>
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

    function setActiveStatus(online) {
      const el = document.getElementById('activeStatus');
      el.textContent = online ? '● Online' : 'Offline';
      el.style.color = online ? '#22c55e' : 'rgba(255,255,255,0.35)';
    }

    function updateConvOnlineDot(userId, online) {
      const dot = document.querySelector(`[data-userid="${userId}"] .online-dot`);
      if (dot) dot.style.display = online ? 'block' : 'none';
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