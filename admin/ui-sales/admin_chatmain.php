<?php
include ROOT_PATH . '/connection/connect.php';

if (!isset($_SESSION['noble_user'])) {
  header('Location: ' . BASE_URL . '/main');
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
  <title>Chat Support — Admin</title>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>

  <style>
    /* ── Scrollbars ── */
    #convList::-webkit-scrollbar,
    #messagesContainer::-webkit-scrollbar {
      width: 3px;
    }

    #convList::-webkit-scrollbar-thumb,
    #messagesContainer::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.08);
      border-radius: 99px;
    }

    /* ── Active conversation row ── */
    .conv-item.active {
      background: rgba(99, 102, 241, 0.12) !important;
      border-left-color: #6366f1 !important;
    }

    /* ── Message slide-in ── */
    @keyframes msgIn {
      from {
        opacity: 0;
        transform: translateY(5px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .msg-in {
      animation: msgIn .16s ease-out;
    }

    /* ── Typing dots ── */
    @keyframes dotBounce {

      0%,
      60%,
      100% {
        transform: scale(1);
        opacity: .3;
      }

      30% {
        transform: scale(1.4);
        opacity: 1;
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

    /* ── Unread badge pulse ── */
    @keyframes badgePop {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: .4;
      }
    }

    .badge-pulse {
      animation: badgePop 1s ease-in-out 3;
    }

    /* ── Send button ── */
    #sendBtn:hover:not(:disabled) {
      background: #4f46e5 !important;
      transform: scale(1.06);
    }

    #sendBtn:active:not(:disabled) {
      transform: scale(.95);
    }

    /* ── Message input focus ── */
    #msgInput:focus {
      outline: none;
      border-color: rgba(99, 102, 241, .5) !important;
      background: rgba(99, 102, 241, .06) !important;
    }

    /* ── Avatar colors ── */
    .av-0 {
      background: #6366f1;
    }

    .av-1 {
      background: #8b5cf6;
    }

    .av-2 {
      background: #06b6d4;
    }

    .av-3 {
      background: #10b981;
    }

    .av-4 {
      background: #f59e0b;
    }

    .av-5 {
      background: #ef4444;
    }
  </style>
</head>

<body class="bg-zinc-950 text-white flex flex-col h-screen overflow-hidden">

  <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

  <!-- ══════════════════════════════════════════ -->
  <!-- MAIN LAYOUT                                -->
  <!-- ══════════════════════════════════════════ -->
  <div class="flex flex-1 overflow-hidden min-h-0">

    <!-- ─── SIDEBAR ─────────────────────────────────────── -->
    <aside class="w-72 shrink-0 flex flex-col bg-zinc-900 border-r border-white/[.06]">

      <!-- Admin info -->
      <div class="px-4 py-4 border-b border-white/[.06] bg-zinc-900/80">
        <div class="flex items-center gap-3 mb-4">
          <div class="av-0 w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0">
            <?= strtoupper(substr($admin_name, 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm truncate"><?= htmlspecialchars($admin_name) ?></p>
            <span
              class="text-[11px] text-indigo-300 bg-indigo-500/15 border border-indigo-500/20 rounded-full px-2 py-0.5 font-medium uppercase tracking-wide">
              <?= htmlspecialchars($admin_lvl) ?>
            </span>
          </div>
          <!-- Connection status -->
          <div class="flex items-center gap-1.5 shrink-0">
            <div id="connDot" class="w-2 h-2 rounded-full bg-amber-400"></div>
            <span id="connLabel" class="text-[10px] text-white/30">Connecting</span>
          </div>
        </div>

        <!-- Conversations count label -->
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-semibold uppercase tracking-widest text-white/25">Conversations</span>
          <span id="convCount"
            class="text-[11px] font-semibold bg-white/[.07] text-white/40 rounded-full px-2 py-0.5">0</span>
        </div>
      </div>

      <!-- Search -->
      <div class="px-3 py-2.5 border-b border-white/[.06]">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-white/20 pointer-events-none" width="13" height="13"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8" />
            <path d="M21 21l-4.35-4.35" />
          </svg>
          <input id="searchInput" type="text" placeholder="Search users…" class="w-full rounded-lg pl-8 pr-3 py-2 text-[13px] bg-white/[.05] border border-white/[.06]
                   text-white placeholder-white/20 focus:outline-none focus:border-indigo-500/40 transition-colors" />
        </div>
      </div>

      <!-- Conversation list -->
      <div id="convList" class="flex-1 overflow-y-auto">
        <div class="px-5 py-12 text-center text-xs text-white/20 leading-loose">
          <div class="text-3xl mb-2">💬</div>
          Waiting for users to connect…
        </div>
      </div>
    </aside>

    <!-- ─── CHAT AREA ─────────────────────────────────────── -->
    <main class="flex-1 flex flex-col overflow-hidden bg-zinc-950">

      <!-- Empty state -->
      <div id="emptyState" class="flex-1 flex flex-col items-center justify-center gap-4 text-center">
        <div class="w-16 h-16 rounded-2xl bg-white/[.03] border border-white/[.06] flex items-center justify-center">
          <svg width="28" height="28" fill="rgba(255,255,255,.18)" viewBox="0 0 32 32">
            <path
              d="M16 2C8.27 2 2 7.87 2 15.07c0 4.2 2.07 7.95 5.31 10.41V30l4.85-2.66c1.29.36 2.66.55 4.08.55C23.73 27.89 30 22.02 30 14.82 30 7.87 23.73 2 16 2z" />
          </svg>
        </div>
        <div>
          <p class="text-[15px] font-semibold text-white/20">No conversation selected</p>
          <p class="text-[12px] text-white/10 mt-1 max-w-[180px] leading-relaxed">Pick a user from the sidebar to start
            replying</p>
        </div>
      </div>

      <!-- Active chat view (hidden until a convo is opened) -->
      <div id="chatView" class="hidden flex-1 flex-col overflow-hidden">

        <!-- Chat header -->
        <div class="flex items-center gap-3 px-5 py-3 bg-zinc-900 border-b border-white/[.06] shrink-0">
          <div id="activeAvatar"
            class="av-0 w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shrink-0">?</div>
          <div class="flex-1 min-w-0">
            <p id="activeName" class="font-semibold text-sm text-white truncate">—</p>
            <p id="activeStatus" class="text-[11px] text-white/30 mt-0.5">—</p>
          </div>
          <!-- Refresh button -->
          <button onclick="refreshConv()" title="Refresh conversation" class="w-8 h-8 rounded-lg bg-white/[.04] border border-white/[.06] flex items-center justify-center
                   text-white/30 hover:text-white/60 hover:bg-white/[.07] transition-all">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M23 4v6h-6M1 20v-6h6" />
              <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
            </svg>
          </button>
        </div>

        <!-- Messages -->
        <div id="messagesContainer" class="flex-1 overflow-y-auto px-5 py-5 flex flex-col gap-0.5"></div>

        <!-- Typing indicator -->
        <div id="typingIndicator" class="px-5 h-7 flex items-center gap-2"></div>

        <!-- Message input bar -->
        <div class="flex items-center gap-2.5 px-4 py-3 bg-zinc-900 border-t border-white/[.06] shrink-0">
          <input id="msgInput" type="text" maxlength="500" autocomplete="off" disabled placeholder="Write a reply…"
            class="flex-1 rounded-full px-4 py-2.5 text-[13.5px] text-white placeholder-white/20
                   bg-white/[.06] border border-white/[.06] transition-all
                   disabled:opacity-30 disabled:cursor-not-allowed" />
          <span id="charCount" class="text-[11px] text-white/20 min-w-[28px] text-right">500</span>
          <button id="sendBtn" disabled class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 transition-all
                   disabled:opacity-30 disabled:cursor-not-allowed border-0 bg-indigo-600">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
              <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13" stroke="white" stroke-width="2.2"
                stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </div>
      </div>
    </main>
  </div>

  <!-- ══════════════════════════════════════════ -->
  <!-- JAVASCRIPT                                 -->
  <!-- ══════════════════════════════════════════ -->
  <script>
    const ADMIN_ID = <?= json_encode((string) $admin_id) ?>;
    const ADMIN_NAME = <?= json_encode($admin_name) ?>;
    const ADMIN_LVL = <?= json_encode($admin_lvl) ?>;

    /* ── State ── */
    let activeUserId = null, allConversations = [];
    const colorMap = {}; let colorIdx = 0;
    const COLORS = ['av-0', 'av-1', 'av-2', 'av-3', 'av-4', 'av-5'];
    function getColor(uid) {
      if (!colorMap[uid]) colorMap[uid] = COLORS[colorIdx++ % COLORS.length];
      return colorMap[uid];
    }

    /* ── Socket ── */
    const socket = io('https://support.noblehomedepot.com');

    socket.on('connect', () => {
      setConn('green', 'Online');
      socket.emit('user:join', { userId: ADMIN_ID, userName: ADMIN_NAME, role: 'admin', title: ADMIN_LVL });
    });
    socket.on('disconnect', () => setConn('red', 'Offline'));

    function setConn(color, label) {
      document.getElementById('connDot').className = `w-2 h-2 rounded-full bg-${color}-400`;
      document.getElementById('connLabel').textContent = label;
    }

    /* ── Conversation list from server ── */
    socket.on('admin:conversations', convs => { allConversations = convs; renderConvList(convs); });

    /* ── Load message history ── */
    socket.on('history', msgs => {
      const el = document.getElementById('messagesContainer');
      el.innerHTML = '';
      if (!msgs?.length) addSystemMsg('No messages yet.');
      else msgs.forEach((m, i) => m?.text && renderMessage(m, msgs[i - 1] ?? null));
      scrollToBottom();
    });

    /* ── Incoming message ── */
    socket.on('message:new', msg => {
      if (!msg?.text) return;
      if (msg.userId == activeUserId) { renderMessage(msg); scrollToBottom(); }
      const conv = allConversations.find(c => c.userId == msg.userId);
      if (conv) { conv.lastMessage = msg.text; conv.lastTime = msg.timestamp; }
    });

    /* ── Online / offline presence ── */
    socket.on('user:online', ({ userId }) => { if (userId == activeUserId) setActiveStatus(true); updateDot(userId, true); });
    socket.on('user:offline', ({ userId }) => { if (userId == activeUserId) setActiveStatus(false); updateDot(userId, false); });

    /* ── Typing ── */
    socket.on('typing:show', ({ userId }) => { if (userId == activeUserId) showTyping(); });
    socket.on('typing:hide', ({ userId }) => { if (userId == activeUserId) hideTyping(); });

    function showTyping() {
      document.getElementById('typingIndicator').innerHTML = `
        <span class="text-[10px] text-white/25">typing</span>
        <span class="flex gap-0.5 items-center">
          <span class="dot1 w-1.5 h-1.5 rounded-full inline-block bg-white/30"></span>
          <span class="dot2 w-1.5 h-1.5 rounded-full inline-block bg-white/30"></span>
          <span class="dot3 w-1.5 h-1.5 rounded-full inline-block bg-white/30"></span>
        </span>`;
    }
    function hideTyping() { document.getElementById('typingIndicator').innerHTML = ''; }

    /* ── Open a conversation ── */
    function openConversation(userId, userName, colorClass, isOnline) {
      activeUserId = userId;
      document.getElementById('emptyState').classList.add('hidden');

      const cv = document.getElementById('chatView');
      cv.classList.remove('hidden');
      cv.classList.add('flex');

      const av = document.getElementById('activeAvatar');
      av.className = `${colorClass} w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shrink-0`;
      av.textContent = (userName || 'U')[0].toUpperCase();

      document.getElementById('activeName').textContent = userName || 'Unknown';
      setActiveStatus(isOnline);

      const input = document.getElementById('msgInput');
      input.disabled = false;
      input.placeholder = `Reply to ${userName || 'user'}…`;
      input.focus();

      document.getElementById('sendBtn').disabled = false;

      hideTyping();

      document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
      document.querySelector(`[data-userid="${userId}"]`)?.classList.add('active');

      socket.emit('admin:open', { userId });
    }

    function refreshConv() {
      if (activeUserId) socket.emit('admin:open', { userId: activeUserId });
    }

    /* ── Send message ── */
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

    /* ── Typing emit ── */
    let isTyping = false, typingTimer;
    document.getElementById('msgInput').addEventListener('input', e => {
      const rem = 500 - e.target.value.length;
      const cc = document.getElementById('charCount');
      cc.textContent = rem;
      cc.style.color = rem < 50 ? '#f87171' : 'rgba(255,255,255,0.2)';
      if (!activeUserId) return;
      if (!isTyping) { isTyping = true; socket.emit('typing:start', { toUserId: activeUserId }); }
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => {
        isTyping = false;
        socket.emit('typing:stop', { toUserId: activeUserId });
      }, 1500);
    });

    /* ── Search filter ── */
    document.getElementById('searchInput').addEventListener('input', e => {
      const q = e.target.value.toLowerCase();
      renderConvList(allConversations.filter(c => (c.userName || '').toLowerCase().includes(q)));
    });

    /* ─────────────────────────── RENDER FUNCTIONS ─────────────────────────── */

    function renderConvList(convs) {
      const el = document.getElementById('convList');
      document.getElementById('convCount').textContent = convs.length;

      if (!convs?.length) {
        el.innerHTML = `<p class="px-5 py-12 text-center text-xs text-white/20">No conversations yet</p>`;
        return;
      }

      el.innerHTML = '';
      convs.forEach(conv => {
        const color = getColor(conv.userId);
        const uname = conv.userName || 'Unknown';

        const div = document.createElement('div');
        div.className = 'conv-item flex items-center gap-3 px-4 py-3 cursor-pointer border-l-2 border-transparent transition-all';
        div.style.borderBottom = '1px solid rgba(255,255,255,0.04)';
        div.setAttribute('data-userid', conv.userId);

        div.onmouseenter = () => { if (!div.classList.contains('active')) div.style.background = 'rgba(255,255,255,0.03)'; };
        div.onmouseleave = () => { if (!div.classList.contains('active')) div.style.background = ''; };
        div.onclick = () => openConversation(conv.userId, uname, color, conv.isOnline);

        div.innerHTML = `
          <div class="${color} w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm shrink-0 relative">
            ${uname[0].toUpperCase()}
            ${conv.isOnline
            ? `<span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-400 rounded-full ring-2 ring-zinc-900"></span>`
            : ''}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-0.5">
              <span class="font-semibold text-[13px] truncate max-w-[130px]">${escapeHtml(uname)}</span>
              <span class="text-white/20 text-[10px] shrink-0 ml-2">${conv.lastTime ? formatTime(conv.lastTime) : ''}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[11.5px] text-white/30 truncate max-w-[140px]">${escapeHtml(conv.lastMessage || 'No messages yet')}</span>
              ${conv.unread > 0
            ? `<span class="badge-pulse ml-2 bg-indigo-500 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 shrink-0">${conv.unread}</span>`
            : ''}
            </div>
          </div>`;
        el.appendChild(div);
      });
    }

    function renderMessage(msg, prevMsg) {
      if (!msg?.text) return;
      const isMine = msg.from === 'admin';
      const sameAuthor = prevMsg && prevMsg.from === msg.from;
      const wrapper = document.createElement('div');

      wrapper.className = `msg-in flex items-end gap-2 max-w-[70%] ${sameAuthor ? 'mt-0.5' : 'mt-3'} ${isMine ? 'self-end flex-row-reverse' : 'self-start'}`;

      const time = formatTime(msg.timestamp);
      const uname = msg.userName || 'User';
      const color = getColor(msg.userId);

      if (isMine) {
        wrapper.innerHTML = `
          <span class="text-[10px] text-white/20 px-1 whitespace-nowrap">${time}</span>
          <div class="bg-indigo-600 text-white px-3.5 py-2 rounded-2xl rounded-br-sm text-[13.5px] leading-snug break-words">
            ${escapeHtml(msg.text)}
          </div>
          <div class="av-0 w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0">
            ${ADMIN_NAME[0].toUpperCase()}
          </div>`;
      } else {
        wrapper.innerHTML = `
          <div class="${color} w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0">
            ${uname[0].toUpperCase()}
          </div>
          <div>
            ${sameAuthor ? '' : `<p class="text-[10px] text-white/25 mb-1 pl-2.5">${escapeHtml(uname)}</p>`}
            <div class="flex items-end gap-1.5">
              <div class="bg-zinc-800 border border-white/[.06] px-3.5 py-2 rounded-2xl rounded-bl-sm text-[13.5px] leading-snug break-words text-zinc-100">
                ${escapeHtml(msg.text)}
              </div>
              <span class="text-[10px] text-white/20 px-1 whitespace-nowrap">${time}</span>
            </div>
          </div>`;
      }

      document.getElementById('messagesContainer').appendChild(wrapper);
    }

    function addSystemMsg(text) {
      const d = document.createElement('div');
      d.className = 'flex items-center gap-3 text-[11px] italic text-white/20 py-2';
      d.innerHTML = `<span class="flex-1 h-px bg-white/[.05]"></span>${escapeHtml(text)}<span class="flex-1 h-px bg-white/[.05]"></span>`;
      document.getElementById('messagesContainer').appendChild(d);
    }

    function setActiveStatus(online) {
      document.getElementById('activeStatus').innerHTML = online
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
        dot.className = 'absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-400 rounded-full ring-2 ring-zinc-900';
        av.appendChild(dot);
      } else if (!online && dot) {
        dot.remove();
      }
    }

    /* ── Utilities ── */
    function scrollToBottom() {
      const el = document.getElementById('messagesContainer');
      el.scrollTop = el.scrollHeight;
    }
    function formatTime(ts) {
      try { return new Date(ts).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }); }
      catch { return ''; }
    }
    function escapeHtml(str) {
      if (!str) return '';
      return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/\n/g, '<br>');
    }
  </script>
</body>

</html>