const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const httpServer = http.createServer(app);
const io = new Server(httpServer, { cors: { origin: '*' } });

// ── IN-MEMORY STORES ──────────────────────────────────────
const onlineAdmins   = {};   // adminId  → { socketId, name, title }
const onlineUsers    = {};   // userId   → { socketId, name }
const conversations  = {};   // convKey  → [ msg, ... ]
const userAdminMap   = {};   // userId   → adminId
const rateLimiter    = {};   // socketId → { count, resetAt }

// ── SECURITY HELPERS ──────────────────────────────────────

// Sanitize text — strip HTML tags, trim, limit length
function sanitize(str, maxLen = 500) {
  if (typeof str !== 'string') return '';
  return str.replace(/<[^>]*>/g, '').trim().slice(0, maxLen);
}

// Rate limit: max 10 messages per 5 seconds per socket
function isRateLimited(socketId) {
  const now = Date.now();
  if (!rateLimiter[socketId] || now > rateLimiter[socketId].resetAt) {
    rateLimiter[socketId] = { count: 1, resetAt: now + 5000 };
    return false;
  }
  rateLimiter[socketId].count++;
  if (rateLimiter[socketId].count > 10) return true;
  return false;
}

// Validate that a userId is a safe string (digits only for DB IDs)
function isValidId(id) {
  return id && /^[a-zA-Z0-9_-]+$/.test(String(id)) && String(id).length < 64;
}

// ── CONVERSATION HELPERS ──────────────────────────────────

function convKey(userId, adminId) {
  return `u${userId}__a${adminId}`;
}

function roomName(userId, adminId) {
  return `room__u${userId}__a${adminId}`;
}

function getAdminList() {
  return Object.entries(onlineAdmins).map(([adminId, info]) => ({
    adminId,
    adminName: info.name,
    title:     info.title || 'Sales',
    isOnline:  true
  }));
}

function buildAdminConversationList(adminId) {
  const suffix = `__a${adminId}`;
  return Object.entries(conversations)
    .filter(([key, msgs]) => key.endsWith(suffix) && msgs.length > 0)
    .map(([key, msgs]) => {
      const userId   = key.replace(/^u/, '').replace(suffix, '');
      const last     = msgs[msgs.length - 1];
      const unread   = msgs.filter(m => !m.read && m.from === 'user').length;
      const isOnline = !!onlineUsers[userId];
      // Always get name from a user message — never from admin reply
      const userMsg  = msgs.find(m => m.from === 'user');
      const userName = userMsg?.userName || onlineUsers[userId]?.name || 'Unknown';
      return {
        userId,
        userName,
        lastMessage: last?.text     || '',
        lastTime:    last?.timestamp || new Date(0).toISOString(),
        unread,
        isOnline
      };
    })
    .sort((a, b) => new Date(b.lastTime) - new Date(a.lastTime));
}

// ── SOCKET EVENTS ──────────────────────────────────────────

io.on('connection', (socket) => {

  // ── JOIN ────────────────────────────────────────────────
  socket.on('user:join', ({ userId, userName, role, title }) => {
    // ✅ SECURITY: Validate all inputs
    if (!isValidId(userId)) return;
    if (typeof userName !== 'string' || !userName.trim()) return;
    if (role !== 'user' && role !== 'admin') return; // only accept known roles

    // ✅ SECURITY: Sanitize userName — prevent XSS via username
    const cleanName = sanitize(userName, 50);
    if (!cleanName) return;

    socket.userId   = String(userId);
    socket.userName = cleanName;
    socket.role     = role;

    if (role === 'admin') {
      socket.title = sanitize(title || 'Sales', 30);
      onlineAdmins[socket.userId] = {
        socketId: socket.id,
        name:     cleanName,
        title:    socket.title
      };

      socket.join('admins');
      socket.join(`admin_${socket.userId}`);

      socket.emit('admin:conversations', buildAdminConversationList(socket.userId));
      io.to('users').emit('admins:list', getAdminList());
      console.log(`[ADMIN] ${cleanName} joined (id: ${socket.userId})`);

    } else {
      onlineUsers[socket.userId] = { socketId: socket.id, name: cleanName };
      socket.join('users');
      socket.join(`user_${socket.userId}`);
      socket.emit('admins:list', getAdminList());

      // Restore previous conversation if exists
      if (userAdminMap[socket.userId]) {
        const adminId = userAdminMap[socket.userId];
        socket.join(roomName(socket.userId, adminId));
        const existing = conversations[convKey(socket.userId, adminId)] || [];
        socket.emit('history', existing);
        if (existing.length > 0) {
          io.to(`admin_${adminId}`).emit('user:online', { userId: socket.userId, userName: cleanName });
          io.to(`admin_${adminId}`).emit('admin:conversations', buildAdminConversationList(adminId));
        }
      }

      console.log(`[USER] ${cleanName} joined (id: ${socket.userId})`);
    }
  });

  // ── USER SELECTS AN ADMIN ────────────────────────────────
  socket.on('user:select-admin', ({ adminId }) => {
    if (socket.role !== 'user') return;
    if (!isValidId(adminId)) return;

    // ✅ SECURITY: Verify the selected admin actually exists and is online
    if (!onlineAdmins[String(adminId)]) {
      socket.emit('error:admin-offline', { message: 'This admin is no longer online.' });
      return;
    }

    const userId = socket.userId;
    const aId    = String(adminId);
    userAdminMap[userId] = aId;

    socket.join(roomName(userId, aId));

    if (!conversations[convKey(userId, aId)]) {
      conversations[convKey(userId, aId)] = [];
    }

    socket.emit('history', conversations[convKey(userId, aId)]);

    const existing = conversations[convKey(userId, aId)];
    if (existing.length > 0) {
      io.to(`admin_${aId}`).emit('user:online', { userId, userName: socket.userName });
      io.to(`admin_${aId}`).emit('admin:conversations', buildAdminConversationList(aId));
    }

    console.log(`[SELECT] User ${socket.userName} → Admin ${aId}`);
  });

  // ── USER SENDS MESSAGE ───────────────────────────────────
  socket.on('message:send', ({ text }) => {
    if (socket.role !== 'user') return;

    // ✅ SECURITY: Rate limit
    if (isRateLimited(socket.id)) {
      socket.emit('error:rate-limit', { message: 'Sending too fast. Please slow down.' });
      return;
    }

    const clean = sanitize(text);
    if (!clean) return;

    const userId  = socket.userId;
    const adminId = userAdminMap[userId];

    if (!adminId) {
      socket.emit('error:no-admin', { message: 'Please select an admin first.' });
      return;
    }

    // ✅ SECURITY: Verify admin is still online (optional — allows offline messages)
    // Remove this block if you want to allow messages even when admin is offline
    // if (!onlineAdmins[adminId]) {
    //   socket.emit('error:admin-offline', { message: 'Admin is offline.' });
    //   return;
    // }

    const msg = {
      id:        Date.now(),
      from:      'user',
      userId,
      adminId,
      userName:  socket.userName,
      text:      clean,
      timestamp: new Date().toISOString(),
      read:      false
    };

    if (!conversations[convKey(userId, adminId)]) {
      conversations[convKey(userId, adminId)] = [];
    }
    conversations[convKey(userId, adminId)].push(msg);

    socket.emit('message:new', msg);
    io.to(`admin_${adminId}`).emit('message:new', msg);
    io.to(`admin_${adminId}`).emit('admin:conversations', buildAdminConversationList(adminId));

    console.log(`[MSG] ${socket.userName} → admin_${adminId}: ${clean}`);
  });

  // ── ADMIN REPLIES ────────────────────────────────────────
  socket.on('admin:reply', ({ toUserId, text }) => {
    if (socket.role !== 'admin') return;

    // ✅ SECURITY: Rate limit admins too
    if (isRateLimited(socket.id)) return;

    const clean = sanitize(text);
    if (!clean || !toUserId) return;

    const adminId = socket.userId;
    const userId  = String(toUserId);

    // ✅ SECURITY: Verify this user actually chose THIS admin
    // Prevents admin from injecting messages into other admin's conversations
    if (userAdminMap[userId] !== adminId) {
      console.warn(`[SECURITY] Admin ${adminId} tried to reply to user ${userId} who didn't select them`);
      return;
    }

    const msg = {
      id:        Date.now(),
      from:      'admin',
      userId,
      adminId,
      userName:  socket.userName,
      text:      clean,
      timestamp: new Date().toISOString(),
      read:      false
    };

    if (!conversations[convKey(userId, adminId)]) {
      conversations[convKey(userId, adminId)] = [];
    }
    conversations[convKey(userId, adminId)].push(msg);

    io.to(`user_${userId}`).emit('message:new', msg);
    socket.emit('message:new', msg);
    socket.emit('admin:conversations', buildAdminConversationList(adminId));

    console.log(`[REPLY] admin_${adminId} → user_${userId}: ${clean}`);
  });

  // ── ADMIN OPENS CONVERSATION ─────────────────────────────
  socket.on('admin:open', ({ userId }) => {
    if (socket.role !== 'admin' || !isValidId(userId)) return;

    const adminId = socket.userId;
    const uid     = String(userId);

    // ✅ SECURITY: Only allow admin to open conversations that belong to them
    if (userAdminMap[uid] !== adminId) {
      // Allow if there are existing messages between this pair (history)
      const existing = conversations[convKey(uid, adminId)] || [];
      if (existing.length === 0) return;
    }

    const key     = convKey(uid, adminId);
    const history = conversations[key] || [];
    history.forEach(m => { if (m.from === 'user') m.read = true; });

    socket.emit('history', history);
    socket.emit('admin:conversations', buildAdminConversationList(adminId));
  });

  // ── TYPING ───────────────────────────────────────────────
  socket.on('typing:start', ({ toUserId, toAdminId } = {}) => {
    if (socket.role === 'user') {
      const adminId = userAdminMap[socket.userId] || toAdminId;
      if (adminId && isValidId(adminId)) {
        io.to(`admin_${adminId}`).emit('typing:show', {
          userId:   socket.userId,
          userName: socket.userName
        });
      }
    } else if (socket.role === 'admin' && isValidId(toUserId)) {
      // ✅ SECURITY: Only allow typing indicator to user assigned to this admin
      if (userAdminMap[String(toUserId)] === socket.userId) {
        io.to(`user_${toUserId}`).emit('typing:show', { userName: socket.userName });
      }
    }
  });

  socket.on('typing:stop', ({ toUserId, toAdminId } = {}) => {
    if (socket.role === 'user') {
      const adminId = userAdminMap[socket.userId] || toAdminId;
      if (adminId && isValidId(adminId)) {
        io.to(`admin_${adminId}`).emit('typing:hide', { userId: socket.userId });
      }
    } else if (socket.role === 'admin' && isValidId(toUserId)) {
      if (userAdminMap[String(toUserId)] === socket.userId) {
        io.to(`user_${toUserId}`).emit('typing:hide', {});
      }
    }
  });

  // ── DISCONNECT ───────────────────────────────────────────
  socket.on('disconnect', () => {
    // Clean up rate limiter
    delete rateLimiter[socket.id];

    if (!socket.role) return;

    if (socket.role === 'admin') {
      delete onlineAdmins[socket.userId];
      io.to('users').emit('admins:list', getAdminList());
      console.log(`[ADMIN OFFLINE] ${socket.userName}`);

    } else if (socket.role === 'user') {
      delete onlineUsers[socket.userId];
      const adminId = userAdminMap[socket.userId];
      if (adminId) {
        io.to(`admin_${adminId}`).emit('user:offline', { userId: socket.userId });
        io.to(`admin_${adminId}`).emit('admin:conversations', buildAdminConversationList(adminId));
      }
      console.log(`[USER OFFLINE] ${socket.userName}`);
    }
  });

});

const PORT = process.env.PORT || 3000;
httpServer.listen(PORT, () => console.log(`🚀 Noble Support Server on port ${PORT}`));