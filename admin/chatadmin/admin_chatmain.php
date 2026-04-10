<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

if (!isset($_SESSION['noble_user'])) {
  header('Location: ../admin/index.php');
  exit;
}

$admin_id = $_SESSION['noble_id'];
$admin_name = $_SESSION['noble_user'];
$admin_lvl = $_SESSION['noble_lvl'] ?? 'Sales';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Chat Support</title>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sora: ['Sora', 'sans-serif'] },
        }
      }
    }
  </script>
  <style>
    * {
      font-family: 'Sora', sans-serif;
    }

    html,
    body {
      height: 100vh;
      overflow: hidden;
      margin: 0;
      display: flex;
      flex-direction: column;
      background: #0d0d14;
    }

    #chat-wrapper {
      display: flex;
      flex: 1;
      overflow: hidden;
      min-height: 0;
    }

    #convList::-webkit-scrollbar,
    #messagesContainer::-webkit-scrollbar {
      width: 3px;
    }

    #convList::-webkit-scrollbar-thumb,
    #messagesContainer::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.08);
      border-radius: 4px;
    }

    .conv-item.active {
      border-left: 2px solid #3b82f6 !important;
      background: rgba(59, 130, 246, 0.10) !important;
    }

    @keyframes msgIn {
      from {
        opacity: 0;
        transform: translateY(6px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .msg-in {
      animation: msgIn 0.18s ease-out;
    }

    @keyframes dotBounce {

      0%,
      60%,
      100% {
        transform: scale(1);
        opacity: .3
      }

      30% {
        transform: scale(1.3);
        opacity: 1
      }
    }

    .dot1 {
      animation: dotBounce 1.2s infinite;
    }

    .dot2 {
      animation: dotBounce 1.2s .2s infinite;
    }

    .dot3 {
      animation: dotBounce 1.2s .4s infinite;
    }

    @keyframes badgePulse {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .4
      }
    }

    .badge-pulse {
      animation: badgePulse 1s ease-in-out 3;
    }

    #msgInput:focus {
      outline: none;
      border-color: rgba(59, 130, 246, 0.5) !important;
      background: rgba(59, 130, 246, 0.06) !important;
    }

    .send-btn:hover:not(:disabled) {
      background: #2563eb !important;
      transform: scale(1.06);
    }

    .send-btn:active:not(:disabled) {
      transform: scale(0.95);
    }

    .av-0 {
      background: #2563eb;
    }

    .av-1 {
      background: #7c3aed;
    }

    .av-2 {
      background: #059669;
    }

    .av-3 {
      background: #d97706;
    }

    .av-4 {
      background: #db2777;
    }

    .av-5 {
      background: #0891b2;
    }
  </style>
</head>

<body>

  <?php include '../navbar/top.php'; ?>

  <div id="chat-wrapper">

    <!-- ══ SIDEBAR ══ -->
    <div class="w-[300px] flex-shrink-0 flex flex-col border-r"
      style="background:#16161f;border-color:rgba(255,255,255,0.07);">

      <!-- Header -->
      <div class="px-4 pt-4 pb-3 border-b" style="background:#1c1c28;border-color:rgba(255,255,255,0.07);">
        <div class="flex items-center gap-3 mb-3">
          <div
            class="av-0 w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
            <?= strtoupper(substr($admin_name, 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white font-semibold text-[13px] truncate"><?= htmlspecialchars($admin_name) ?></p>
            <span
              class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide text-blue-300"
              style="background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.25);">
              <?= htmlspecialchars($admin_lvl) ?>
            </span>
          </div>
          <div class="flex items-center gap-1.5 flex-shrink-0">
            <div id="connDot" class="w-2 h-2 rounded-full bg-yellow-400"></div>
            <span id="connLabel" class="text-[10px] text-white/30">Connecting</span>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-semibold uppercase tracking-widest text-white/20">Conversations</span>
          <span id="convCount" class="text-[11px] font-semibold rounded-full px-2 py-0.5 text-white/40"
            style="background:rgba(255,255,255,0.07);">0</span>
        </div>
      </div>

      <!-- Search -->
      <div class="px-3 py-2.5 border-b" style="border-color:rgba(255,255,255,0.07);">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-white/20" width="13" height="13"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8" />
            <path d="M21 21l-4.35-4.35" />
          </svg>
          <input id="searchInput" type="text" placeholder="Search users..."
            class="w-full rounded-lg pl-8 pr-3 py-2 text-[13px] text-white placeholder-white/20 transition-all"
            style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.07);" />
        </div>
      </div>

      <!-- Conv list -->
      <div id="convList" class="flex-1 overflow-y-auto">
        <div class="px-5 py-10 text-center text-xs leading-loose text-white/20">
          <div class="text-2xl mb-2">💬</div>
          Waiting for users to connect...
        </div>
      </div>
    </div>

    <!-- ══ CHAT AREA ══ -->
    <div class="flex-1 flex flex-col overflow-hidden" style="background:#12121c;">

      <!-- Empty State -->
      <div id="emptyState" class="flex-1 flex flex-col items-center justify-center text-center gap-3">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center"
          style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);">
          <svg width="28" height="28" viewBox="0 0 32 32" fill="rgba(255,255,255,0.2)">
            <path
              d="M16 2C8.268 2 2 7.87 2 15.07c0 4.2 2.07 7.95 5.31 10.41V30l4.85-2.66c1.29.36 2.66.55 4.08.55 7.732 0 14-5.87 14-13.07C30 7.87 23.732 2 16 2z" />
          </svg>
        </div>
        <p class="text-[15px] font-semibold text-white/20">No conversation selected</p>
        <p class="text-[12px] max-w-[200px] leading-relaxed text-white/10">Pick a user from the sidebar to start
          replying</p>
      </div>

      <!-- Active Chat View -->
      <div id="chatView" class="hidden flex-1 flex-col overflow-hidden">

        <!-- Chat Header -->
        <div class="flex items-center gap-3 px-5 py-3 border-b"
          style="background:#16161f;border-color:rgba(255,255,255,0.07);">
          <div id="activeAvatar"
            class="av-0 w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
            ?</div>
          <div class="flex-1">
            <p id="activeName" class="text-white font-semibold text-sm">—</p>
            <p id="activeStatus" class="text-[11px] mt-0.5 text-white/30">—</p>
          </div>
          <button onclick="refreshConv()" title="Refresh"
            class="w-8 h-8 rounded-lg flex items-center justify-center text-white/30 transition-all hover:text-white/70"
            style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M23 4v6h-6M1 20v-6h6" />
              <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
            </svg>
          </button>
        </div>

        <!-- Messages -->
        <div id="messagesContainer" class="flex-1 overflow-y-auto px-5 py-5 flex flex-col gap-1"></div>

        <!-- Typing -->
        <div id="typingIndicator" class="px-5 h-6 flex items-center gap-1.5"></div>

        <!-- Input Bar -->
        <div class="flex items-center gap-2.5 px-4 py-3 border-t"
          style="background:#16161f;border-color:rgba(255,255,255,0.07);">
          <input id="msgInput" type="text" maxlength="500" autocomplete="off" disabled placeholder="Write a reply..."
            class="flex-1 rounded-full px-4 py-2.5 text-[13.5px] text-white placeholder-white/20 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
            style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.07);" />
          <span id="charCount" class="text-[11px] text-white/20 min-w-[28px] text-right">500</span>
          <button id="sendBtn" disabled
            class="send-btn w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 transition-all disabled:cursor-not-allowed border-0"
            style="background:#3b82f6;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
              <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="white" stroke-width="2.2"
                stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const ADMIN_ID = <?= json_encode((string) $admin_id) ?>;
    const ADMIN_NAME = <?= json_encode($admin_name) ?>;
    const ADMIN_LVL = <?= json_encode($admin_lvl) ?>;

    let activeUserId = null, allConversations = [];
    const colorMap = {}; let colorIdx = 0;
    const COLORS = ['av-0', 'av-1', 'av-2', 'av-3', 'av-4', 'av-5'];
    function getColor(uid) {
      if (!colorMap[uid]) colorMap[uid] = COLORS[colorIdx++ % COLORS.length];
      return colorMap[uid];
    }

    const socket = io('https://support.noblehomedepot.com');

    socket.on('connect', () => {
      document.getElementById('connDot').className = 'w-2 h-2 rounded-full bg-green-400';
      document.getElementById('connLabel').textContent = 'Online';
      socket.emit('user:join', { userId: ADMIN_ID, userName: ADMIN_NAME, role: 'admin', title: ADMIN_LVL });
    });
    socket.on('disconnect', () => {
      document.getElementById('connDot').className = 'w-2 h-2 rounded-full bg-red-400';
      document.getElementById('connLabel').textContent = 'Offline';
    });

    socket.on('admin:conversations', (convs) => { allConversations = convs; renderConvList(convs); });

    socket.on('history', (msgs) => {
      const el = document.getElementById('messagesContainer');
      el.innerHTML = '';
      if (!msgs || msgs.length === 0) addSystemMsg('No messages yet.');
      else msgs.forEach((m, i) => { if (m?.text) renderMessage(m, i > 0 ? msgs[i - 1] : null); });
      scrollToBottom();
    });

    socket.on('message:new', (msg) => {
      if (!msg?.text) return;
      if (msg.userId == activeUserId) { renderMessage(msg); scrollToBottom(); }
      const conv = allConversations.find(c => c.userId == msg.userId);
      if (conv) { conv.lastMessage = msg.text; conv.lastTime = msg.timestamp; }
    });

    socket.on('user:online', ({ userId }) => { if (userId == activeUserId) setActiveStatus(true); updateDot(userId, true); });
    socket.on('user:offline', ({ userId }) => { if (userId == activeUserId) setActiveStatus(false); updateDot(userId, false); });
    socket.on('typing:show', ({ userId }) => { if (userId == activeUserId) showTyping(); });
    socket.on('typing:hide', ({ userId }) => { if (userId == activeUserId) hideTyping(); });

    function showTyping() {
      document.getElementById('typingIndicator').innerHTML =
        `<span class="text-[10px] text-white/25">typing</span>
       <span class="flex gap-0.5 items-center">
         <span class="dot1 w-1.5 h-1.5 rounded-full inline-block" style="background:rgba(255,255,255,0.3)"></span>
         <span class="dot2 w-1.5 h-1.5 rounded-full inline-block" style="background:rgba(255,255,255,0.3)"></span>
         <span class="dot3 w-1.5 h-1.5 rounded-full inline-block" style="background:rgba(255,255,255,0.3)"></span>
       </span>`;
    }
    function hideTyping() { document.getElementById('typingIndicator').innerHTML = ''; }

    function openConversation(userId, userName, colorClass, isOnline) {
      activeUserId = userId;
      document.getElementById('emptyState').classList.add('hidden');
      const cv = document.getElementById('chatView');
      cv.classList.remove('hidden'); cv.classList.add('flex');

      const av = document.getElementById('activeAvatar');
      av.className = `${colorClass} w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0`;
      av.textContent = (userName || 'U')[0].toUpperCase();
      document.getElementById('activeName').textContent = userName || 'Unknown';
      setActiveStatus(isOnline);
      document.getElementById('msgInput').disabled = false;
      document.getElementById('sendBtn').disabled = false;
      document.getElementById('sendBtn').style.background = '#3b82f6';
      document.getElementById('msgInput').placeholder = `Reply to ${userName || 'user'}...`;
      document.getElementById('msgInput').focus();
      hideTyping();

      document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
      const item = document.querySelector(`[data-userid="${userId}"]`);
      if (item) item.classList.add('active');
      socket.emit('admin:open', { userId });
    }

    function refreshConv() { if (activeUserId) socket.emit('admin:open', { userId: activeUserId }); }

    function sendReply() {
      const input = document.getElementById('msgInput');
      const text = input.value.trim();
      if (!text || !activeUserId || !socket.connected) return;
      socket.emit('admin:reply', { toUserId: activeUserId, text });
      socket.emit('typing:stop', { toUserId: activeUserId });
      input.value = '';
      document.getElementById('charCount').textContent = '500';
      document.getElementById('charCount').style.color = 'rgba(255,255,255,0.2)';
      isTyping = false;
    }

    document.getElementById('sendBtn').addEventListener('click', sendReply);
    document.getElementById('msgInput').addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
    });

    let isTyping = false, typingTimer;
    document.getElementById('msgInput').addEventListener('input', e => {
      const rem = 500 - e.target.value.length;
      const cc = document.getElementById('charCount');
      cc.textContent = rem;
      cc.style.color = rem < 50 ? '#f87171' : 'rgba(255,255,255,0.2)';
      if (!activeUserId) return;
      if (!isTyping) { isTyping = true; socket.emit('typing:start', { toUserId: activeUserId }); }
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => { isTyping = false; socket.emit('typing:stop', { toUserId: activeUserId }); }, 1500);
    });

    document.getElementById('searchInput').addEventListener('input', e => {
      const q = e.target.value.toLowerCase();
      renderConvList(allConversations.filter(c => (c.userName || '').toLowerCase().includes(q)));
    });

    function renderConvList(convs) {
      const el = document.getElementById('convList');
      document.getElementById('convCount').textContent = convs.length;
      if (!convs?.length) {
        el.innerHTML = `<p class="px-5 py-10 text-center text-xs text-white/20">No conversations yet</p>`;
        return;
      }
      el.innerHTML = '';
      convs.forEach(conv => {
        const color = getColor(conv.userId);
        const uname = conv.userName || 'Unknown';
        const div = document.createElement('div');
        div.className = 'conv-item flex items-center gap-2.5 px-3.5 py-2.5 cursor-pointer relative transition-colors';
        div.style.borderBottom = '1px solid rgba(255,255,255,0.03)';
        div.setAttribute('data-userid', conv.userId);
        div.onmouseenter = () => { if (!div.classList.contains('active')) div.style.background = 'rgba(255,255,255,0.04)'; };
        div.onmouseleave = () => { if (!div.classList.contains('active')) div.style.background = ''; };
        div.onclick = () => openConversation(conv.userId, uname, color, conv.isOnline);
        div.innerHTML = `
        <div class="${color} w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0 relative">
          ${uname[0].toUpperCase()}
          ${conv.isOnline ? `<span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-400 rounded-full" style="border:2px solid #16161f;"></span>` : ''}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between mb-0.5">
            <span class="text-white font-semibold text-[13px] truncate max-w-[140px]">${escapeHtml(uname)}</span>
            <span class="text-white/20 text-[10px] flex-shrink-0 ml-2">${conv.lastTime ? formatTime(conv.lastTime) : ''}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-[11.5px] truncate max-w-[150px] text-white/30">${escapeHtml(conv.lastMessage || 'No messages yet')}</span>
            ${conv.unread > 0 ? `<span class="badge-pulse ml-2 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 flex-shrink-0">${conv.unread}</span>` : ''}
          </div>
        </div>`;
        el.appendChild(div);
      });
    }

    function renderMessage(msg, prevMsg) {
      if (!msg?.text) return;
      const isMine = msg.from === 'admin';
      const wrapper = document.createElement('div');
      const mt = (!prevMsg || prevMsg.from !== msg.from) ? 'mt-3' : '';
      wrapper.className = `msg-in flex items-end gap-2 max-w-[72%] ${mt} ${isMine ? 'self-end flex-row-reverse' : 'self-start'}`;

      const time = formatTime(msg.timestamp);
      const uname = msg.userName || 'User';
      const color = getColor(msg.userId);

      if (isMine) {
        wrapper.innerHTML = `
        <span class="text-[10px] text-white/20 px-1 whitespace-nowrap">${time}</span>
        <div class="text-white px-3.5 py-2 rounded-2xl rounded-br text-[13.5px] leading-snug break-words" style="background:#3b82f6;border-bottom-right-radius:4px;">${escapeHtml(msg.text)}</div>
        <div class="av-0 w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">${ADMIN_NAME[0].toUpperCase()}</div>`;
      } else {
        wrapper.innerHTML = `
        <div class="${color} w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">${uname[0].toUpperCase()}</div>
        <div>
          <p class="text-[10px] text-white/25 mb-1 pl-2.5">${escapeHtml(uname)}</p>
          <div class="flex items-end gap-1.5">
            <div class="px-3.5 py-2 rounded-2xl text-[13.5px] leading-snug break-words" style="background:#1c1c28;border:1px solid rgba(255,255,255,0.07);color:#e2e2f0;border-bottom-left-radius:4px;">${escapeHtml(msg.text)}</div>
            <span class="text-[10px] text-white/20 px-1 whitespace-nowrap">${time}</span>
          </div>
        </div>`;
      }
      document.getElementById('messagesContainer').appendChild(wrapper);
    }

    function addSystemMsg(text) {
      const d = document.createElement('div');
      d.className = 'text-center text-[11px] py-2 italic flex items-center gap-2 text-white/20';
      d.innerHTML = `<span class="flex-1 h-px" style="background:rgba(255,255,255,0.06)"></span>${escapeHtml(text)}<span class="flex-1 h-px" style="background:rgba(255,255,255,0.06)"></span>`;
      document.getElementById('messagesContainer').appendChild(d);
    }

    function setActiveStatus(online) {
      const el = document.getElementById('activeStatus');
      el.innerHTML = online
        ? `<span class="text-green-400">● Online</span>`
        : `<span class="text-white/25">Offline</span>`;
    }

    function updateDot(userId, online) {
      const item = document.querySelector(`[data-userid="${userId}"]`);
      if (!item) return;
      const av = item.querySelector('div');
      let dot = av?.querySelector('span');
      if (online && !dot && av) {
        dot = document.createElement('span');
        dot.className = 'absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-400 rounded-full';
        dot.style.border = '2px solid #16161f';
        av.appendChild(dot);
      } else if (!online && dot) dot.remove();
    }

    function scrollToBottom() {
      const el = document.getElementById('messagesContainer');
      el.scrollTop = el.scrollHeight;
    }
    function formatTime(ts) {
      try { return new Date(ts).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; }
    }
    function escapeHtml(str) {
      if (!str) return '';
      return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/\n/g, '<br>');
    }
  </script>
</body>

</html>