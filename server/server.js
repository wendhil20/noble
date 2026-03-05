const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const httpServer = http.createServer(app);
const io = new Server(httpServer, { cors: { origin: '*' } });

// onlineAdmins: adminId → { socketId, name, title }
const onlineAdmins = {};

// onlineUsers: userId → { socketId, name }
const onlineUsers = {};

// conversations: `u${userId}__a${adminId}` → [ msg, ... ]
// Prefix u= user, a= admin — no collision possible
const conversations = {};

// Which admin each user is talking to: userId → adminId
const userAdminMap = {};

// ── HELPERS ───────────────────────────────────────────────

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

      // ALWAYS get userName from a user message — never from admin reply
      const userMsg  = msgs.find(m => m.from === 'user');
      const userName = userMsg?.userName
        || onlineUsers[userId]?.name
        || 'Unknown';

      return {
        userId,
        userName,                              // always the user's real name
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
    if (!userId || !userName || !role) return;

    socket.userId   = String(userId);
    socket.userName = userName;
    socket.role     = role;

    if (role === 'admin') {
      socket.title = title || 'Sales';
      onlineAdmins[socket.userId] = {
        socketId: socket.id,
        name:     userName,
        title:    socket.title
      };

      socket.join('admins');
      socket.join(`admin_${socket.userId}`);

      // Send admin only their own conversations
      socket.emit('admin:conversations', buildAdminConversationList(socket.userId));

      // Tell all users the admin list updated
      io.to('users').emit('admins:list', getAdminList());

      console.log(`[ADMIN] ${userName} joined (id: ${socket.userId})`);

    } else {
      // Regular user
      onlineUsers[socket.userId] = { socketId: socket.id, name: userName };

      socket.join('users');
      socket.join(`user_${socket.userId}`);

      // Send updated admin list
      socket.emit('admins:list', getAdminList());

      // If user already had a selected admin session, restore it
      if (userAdminMap[socket.userId]) {
        const adminId = userAdminMap[socket.userId];
        socket.join(roomName(socket.userId, adminId));
        socket.emit('history', conversations[convKey(socket.userId, adminId)] || []);
        // Notify that admin their user came back online
        io.to(`admin_${adminId}`).emit('user:online', { userId: socket.userId, userName });
        io.to(`admin_${adminId}`).emit('admin:conversations', buildAdminConversationList(adminId));
      }

      console.log(`[USER] ${userName} joined (id: ${socket.userId})`);
    }
  });

  // ── USER SELECTS AN ADMIN ────────────────────────────────
  socket.on('user:select-admin', ({ adminId }) => {
    if (socket.role !== 'user' || !adminId) return;

    const userId  = socket.userId;
    const aId     = String(adminId);
    userAdminMap[userId] = aId;

    socket.join(roomName(userId, aId));

    if (!conversations[convKey(userId, aId)]) {
      conversations[convKey(userId, aId)] = [];
    }

    // Send history of this private conversation to the user
    socket.emit('history', conversations[convKey(userId, aId)]);

    // Only notify admin if there are existing messages (returning user)
    // New users will appear in admin list only after first message is sent
    const existingMsgs = conversations[convKey(userId, aId)];
    if (existingMsgs.length > 0) {
      io.to(`admin_${aId}`).emit('user:online', { userId, userName: socket.userName });
      io.to(`admin_${aId}`).emit('admin:conversations', buildAdminConversationList(aId));
    }

    console.log(`[SELECT] User ${socket.userName} → Admin ${aId}`);
  });

  // ── USER SENDS MESSAGE ───────────────────────────────────
  socket.on('message:send', ({ text }) => {
    if (socket.role !== 'user') return;
    if (!text || !text.trim()) return;

    const userId  = socket.userId;
    const adminId = userAdminMap[userId];

    if (!adminId) {
      socket.emit('error:no-admin', { message: 'Please select an admin first.' });
      return;
    }

    const msg = {
      id:        Date.now(),
      from:      'user',
      userId,
      adminId,
      userName:  socket.userName || 'User',
      text:      text.trim(),
      timestamp: new Date().toISOString(),
      read:      false
    };

    if (!conversations[convKey(userId, adminId)]) {
      conversations[convKey(userId, adminId)] = [];
    }
    conversations[convKey(userId, adminId)].push(msg);

    // Echo back to sender
    socket.emit('message:new', msg);

    // Send to that specific admin ONLY
    io.to(`admin_${adminId}`).emit('message:new', msg);
    io.to(`admin_${adminId}`).emit('admin:conversations', buildAdminConversationList(adminId));

    console.log(`[MSG] ${socket.userName} → admin_${adminId}: ${text.trim()}`);
  });

  // ── ADMIN REPLIES ────────────────────────────────────────
  socket.on('admin:reply', ({ toUserId, text }) => {
    if (socket.role !== 'admin') return;
    if (!text || !text.trim() || !toUserId) return;

    const adminId = socket.userId;
    const userId  = String(toUserId);

    const msg = {
      id:        Date.now(),
      from:      'admin',
      userId,
      adminId,
      userName:  socket.userName || 'Support',
      text:      text.trim(),
      timestamp: new Date().toISOString(),
      read:      false
    };

    if (!conversations[convKey(userId, adminId)]) {
      conversations[convKey(userId, adminId)] = [];
    }
    conversations[convKey(userId, adminId)].push(msg);

    // Send to the specific user
    io.to(`user_${userId}`).emit('message:new', msg);

    // Echo back to admin
    socket.emit('message:new', msg);
    socket.emit('admin:conversations', buildAdminConversationList(adminId));

    console.log(`[REPLY] admin_${adminId} → user_${userId}: ${text.trim()}`);
  });

  // ── ADMIN OPENS CONVERSATION ─────────────────────────────
  socket.on('admin:open', ({ userId }) => {
    if (socket.role !== 'admin' || !userId) return;

    const adminId = socket.userId;
    const key     = convKey(String(userId), adminId);
    const history = conversations[key] || [];

    // Mark user messages as read
    history.forEach(m => { if (m.from === 'user') m.read = true; });

    socket.emit('history', history);
    socket.emit('admin:conversations', buildAdminConversationList(adminId));
  });

  // ── TYPING ───────────────────────────────────────────────
  socket.on('typing:start', ({ toUserId, toAdminId } = {}) => {
    if (socket.role === 'user') {
      const adminId = userAdminMap[socket.userId] || toAdminId;
      if (adminId) {
        io.to(`admin_${adminId}`).emit('typing:show', {
          userId:   socket.userId,
          userName: socket.userName || 'User'
        });
      }
    } else if (socket.role === 'admin' && toUserId) {
      io.to(`user_${toUserId}`).emit('typing:show', {
        userName: socket.userName || 'Support'
      });
    }
  });

  socket.on('typing:stop', ({ toUserId, toAdminId } = {}) => {
    if (socket.role === 'user') {
      const adminId = userAdminMap[socket.userId] || toAdminId;
      if (adminId) {
        io.to(`admin_${adminId}`).emit('typing:hide', { userId: socket.userId });
      }
    } else if (socket.role === 'admin' && toUserId) {
      io.to(`user_${toUserId}`).emit('typing:hide', {});
    }
  });

  // ── DISCONNECT ───────────────────────────────────────────
  socket.on('disconnect', () => {
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