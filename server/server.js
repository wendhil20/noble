/**
 * Noble Home Depot — Chat Support Server
 * Node.js + Express + Socket.IO
 * Compatible with Hostinger VPS / Node hosting
 */

const express   = require('express');
const http      = require('http');
const { Server } = require('socket.io');

const app    = express();
const server = http.createServer(app);

const io = new Server(server, {
  cors: {
    origin: '*',          // Change to your domain in production, e.g. 'https://noblehomedepot.com'
    methods: ['GET', 'POST']
  }
});

// ── PORT ─────────────────────────────────────────────────────────────────────
const PORT = process.env.PORT || 3000;

// ── HEALTH CHECK ─────────────────────────────────────────────────────────────
app.get('/', (req, res) => res.send('Noble Chat Server is running ✅'));

// ── IN-MEMORY STATE ───────────────────────────────────────────────────────────
/**
 * admins  : Map<adminId, { socketId, adminName, title }>
 * users   : Map<userId,  { socketId, userName, selectedAdminId }>
 * messages: Map<"userId_adminId", Array<msgObject>>
 *
 * Message object shape:
 * { from, userId, adminId, userName, text, timestamp }
 */
const admins   = new Map();
const users    = new Map();
const messages = new Map();

// Helpers
function getRoomKey(userId, adminId) {
  return `${userId}_${adminId}`;
}

function getHistory(userId, adminId) {
  return messages.get(getRoomKey(userId, adminId)) || [];
}

function saveMessage(userId, adminId, msgObj) {
  const key  = getRoomKey(userId, adminId);
  const hist = messages.get(key) || [];
  hist.push(msgObj);
  // Keep last 200 messages per room
  if (hist.length > 200) hist.splice(0, hist.length - 200);
  messages.set(key, hist);
  return msgObj;
}

/**
 * Build the conversation list for a specific admin.
 * Returns every user that has ever chatted with (or selected) this admin,
 * with last-message preview and unread count (unread = simple last-msg check).
 */
function buildConversationsForAdmin(adminId) {
  const convs = [];

  users.forEach((uInfo, userId) => {
    // Include user if they currently have this admin selected OR have history
    const hist = getHistory(userId, adminId);
    if (uInfo.selectedAdminId == adminId || hist.length > 0) {
      const last = hist[hist.length - 1] || null;
      convs.push({
        userId,
        userName:    uInfo.userName,
        isOnline:    !!uInfo.socketId,
        lastMessage: last ? last.text : null,
        lastTime:    last ? last.timestamp : null,
        unread:      0   // Extend this if you add read-receipt tracking
      });
    }
  });

  return convs;
}

/** Send updated admin list to all connected users */
function broadcastAdminList() {
  const list = [];
  admins.forEach((info, adminId) => {
    list.push({
      adminId,
      adminName: info.adminName,
      title:     info.title || 'Sales Representative'
    });
  });
  io.emit('admins:list', list);
}

/** Send updated conversation list to a specific admin socket */
function sendConversationsToAdmin(adminId) {
  const info = admins.get(adminId);
  if (!info || !info.socketId) return;
  const convs = buildConversationsForAdmin(adminId);
  io.to(info.socketId).emit('admin:conversations', convs);
}

// ── SOCKET EVENTS ─────────────────────────────────────────────────────────────
io.on('connection', (socket) => {
  console.log(`[connect] socket ${socket.id}`);

  let selfId   = null;
  let selfRole = null;

  // ── JOIN ──────────────────────────────────────────────────
  /**
   * Emitted by both admin and user on page load.
   * Payload: { userId, userName, role: 'admin'|'user', title? }
   */
  socket.on('user:join', (data) => {
    const { userId, userName, role, title } = data || {};
    if (!userId || !role) return;

    selfId   = String(userId);
    selfRole = role;

    if (role === 'admin') {
      admins.set(selfId, { socketId: socket.id, adminName: userName, title: title || 'Sales' });
      console.log(`[admin join] ${userName} (${selfId})`);

      // Send this admin their existing conversations
      sendConversationsToAdmin(selfId);
      // Tell all users a new admin is online
      broadcastAdminList();

    } else {
      // user
      const existing = users.get(selfId) || {};
      users.set(selfId, {
        socketId:        socket.id,
        userName:        userName,
        selectedAdminId: existing.selectedAdminId || null
      });
      console.log(`[user join] ${userName} (${selfId})`);

      // Send current admin list to this user
      const list = [];
      admins.forEach((info, adminId) => {
        list.push({ adminId, adminName: info.adminName, title: info.title || 'Sales Representative' });
      });
      socket.emit('admins:list', list);

      // Notify the admin this user was already chatting with that they're back online
      const uInfo = users.get(selfId);
      if (uInfo && uInfo.selectedAdminId) {
        const aInfo = admins.get(String(uInfo.selectedAdminId));
        if (aInfo && aInfo.socketId) {
          io.to(aInfo.socketId).emit('user:online', { userId: selfId, userName });
          sendConversationsToAdmin(String(uInfo.selectedAdminId));
        }
      }
    }
  });

  // ── USER SELECTS AN ADMIN ─────────────────────────────────
  /**
   * Emitted by user when they click an admin card.
   * Payload: { adminId }
   */
  socket.on('user:select-admin', ({ adminId } = {}) => {
    if (!selfId || selfRole !== 'user' || !adminId) return;
    const adminIdStr = String(adminId);

    const uInfo = users.get(selfId);
    if (uInfo) {
      uInfo.selectedAdminId = adminIdStr;
      users.set(selfId, uInfo);
    }

    // Send chat history for this pair
    const hist = getHistory(selfId, adminIdStr);
    socket.emit('history', hist);

    // Notify admin this user is chatting with them
    const aInfo = admins.get(adminIdStr);
    if (aInfo && aInfo.socketId) {
      io.to(aInfo.socketId).emit('user:online', { userId: selfId, userName: uInfo ? uInfo.userName : 'Unknown' });
      sendConversationsToAdmin(adminIdStr);
    }

    console.log(`[select] user ${selfId} → admin ${adminIdStr}`);
  });

  // ── ADMIN OPENS A CONVERSATION ────────────────────────────
  /**
   * Emitted by admin when they click a user in the sidebar.
   * Payload: { userId }
   */
  socket.on('admin:open', ({ userId } = {}) => {
    if (!selfId || selfRole !== 'admin' || !userId) return;
    const userIdStr = String(userId);
    const hist = getHistory(userIdStr, selfId);
    socket.emit('history', hist);
    console.log(`[admin:open] admin ${selfId} opened conv with user ${userIdStr}`);
  });

  // ── USER SENDS A MESSAGE ──────────────────────────────────
  /**
   * Emitted by user.
   * Payload: { text }
   */
  socket.on('message:send', ({ text } = {}) => {
    if (!selfId || selfRole !== 'user' || !text) return;
    const uInfo = users.get(selfId);
    if (!uInfo || !uInfo.selectedAdminId) return;

    const adminIdStr = String(uInfo.selectedAdminId);
    const msg = saveMessage(selfId, adminIdStr, {
      from:      'user',
      userId:    selfId,
      adminId:   adminIdStr,
      userName:  uInfo.userName,
      text:      text.substring(0, 500),
      timestamp: new Date().toISOString()
    });

    // Echo back to sender
    socket.emit('message:new', msg);

    // Send to admin
    const aInfo = admins.get(adminIdStr);
    if (aInfo && aInfo.socketId) {
      io.to(aInfo.socketId).emit('message:new', msg);
      sendConversationsToAdmin(adminIdStr);
    }

    console.log(`[msg:user→admin] ${uInfo.userName}: ${text.substring(0, 40)}`);
  });

  // ── ADMIN SENDS A REPLY ───────────────────────────────────
  /**
   * Emitted by admin.
   * Payload: { toUserId, text }
   */
  socket.on('admin:reply', ({ toUserId, text } = {}) => {
    if (!selfId || selfRole !== 'admin' || !toUserId || !text) return;
    const userIdStr  = String(toUserId);
    const aInfo      = admins.get(selfId);

    const msg = saveMessage(userIdStr, selfId, {
      from:      'admin',
      userId:    userIdStr,
      adminId:   selfId,
      userName:  aInfo ? aInfo.adminName : 'Support',
      text:      text.substring(0, 500),
      timestamp: new Date().toISOString()
    });

    // Echo back to admin sender
    socket.emit('message:new', msg);

    // Send to the user
    const uInfo = users.get(userIdStr);
    if (uInfo && uInfo.socketId) {
      io.to(uInfo.socketId).emit('message:new', msg);
    }

    // Update admin's own conversation list
    sendConversationsToAdmin(selfId);

    console.log(`[msg:admin→user] ${aInfo ? aInfo.adminName : selfId} → user ${userIdStr}: ${text.substring(0, 40)}`);
  });

  // ── TYPING ────────────────────────────────────────────────
  /**
   * User side: socket.emit('typing:start', { toAdminId })
   * Admin side: socket.emit('typing:start', { toUserId })
   */
  socket.on('typing:start', (payload = {}) => {
    if (!selfId) return;

    if (selfRole === 'user') {
      const uInfo      = users.get(selfId);
      const adminIdStr = String(payload.toAdminId || (uInfo && uInfo.selectedAdminId) || '');
      const aInfo      = admins.get(adminIdStr);
      if (aInfo && aInfo.socketId) {
        io.to(aInfo.socketId).emit('typing:show', { userId: selfId, userName: uInfo ? uInfo.userName : 'User' });
      }
    } else {
      // admin typing to user
      const userIdStr = String(payload.toUserId || '');
      const uInfo     = users.get(userIdStr);
      const aInfo     = admins.get(selfId);
      if (uInfo && uInfo.socketId) {
        io.to(uInfo.socketId).emit('typing:show', { adminId: selfId, userName: aInfo ? aInfo.adminName : 'Support' });
      }
    }
  });

  socket.on('typing:stop', (payload = {}) => {
    if (!selfId) return;

    if (selfRole === 'user') {
      const uInfo      = users.get(selfId);
      const adminIdStr = String(payload.toAdminId || (uInfo && uInfo.selectedAdminId) || '');
      const aInfo      = admins.get(adminIdStr);
      if (aInfo && aInfo.socketId) {
        io.to(aInfo.socketId).emit('typing:hide', { userId: selfId });
      }
    } else {
      const userIdStr = String(payload.toUserId || '');
      const uInfo     = users.get(userIdStr);
      if (uInfo && uInfo.socketId) {
        io.to(uInfo.socketId).emit('typing:hide', { adminId: selfId });
      }
    }
  });

  // ── DISCONNECT ────────────────────────────────────────────
  socket.on('disconnect', () => {
    console.log(`[disconnect] socket ${socket.id} (${selfRole} ${selfId})`);

    if (!selfId) return;

    if (selfRole === 'admin') {
      admins.delete(selfId);
      broadcastAdminList();

    } else {
      const uInfo = users.get(selfId);
      if (uInfo) {
        uInfo.socketId = null;   // Mark as offline but keep selectedAdminId
        users.set(selfId, uInfo);

        // Notify admin their user went offline
        if (uInfo.selectedAdminId) {
          const aInfo = admins.get(String(uInfo.selectedAdminId));
          if (aInfo && aInfo.socketId) {
            io.to(aInfo.socketId).emit('user:offline', { userId: selfId });
            sendConversationsToAdmin(String(uInfo.selectedAdminId));
          }
        }
      }
    }
  });
});

// ── START ─────────────────────────────────────────────────────────────────────
server.listen(PORT, () => {
  console.log(`✅ Noble Chat Server listening on port ${PORT}`);
});