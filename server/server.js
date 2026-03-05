const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const httpServer = http.createServer(app);
const io = new Server(httpServer, { cors: { origin: '*' } });

const onlineUsers    = {};   // socket.id → { id, name, role }
const conversations  = {};   // userId   → [ msg, msg, ... ]

// ── HELPERS ──────────────────────────────────────────────

function buildConversationList() {
  return Object.entries(conversations).map(([userId, msgs]) => {
    const last   = msgs[msgs.length - 1];
    const unread = msgs.filter(m => !m.read && m.from === 'user').length;
    const isOnline = Object.values(onlineUsers).some(u => u.id == userId && u.role === 'user');
    return {
      userId,
      userName:    last?.userName || 'Unknown',
      lastMessage: last?.text     || '',
      lastTime:    last?.timestamp || new Date(0).toISOString(),
      unread,
      isOnline
    };
  }).sort((a, b) => new Date(b.lastTime) - new Date(a.lastTime));
}

function roomName(userId) { return `conv_${userId}`; }

function isAnyAdminOnline() {
  return Object.values(onlineUsers).some(u => u.role === 'admin');
}

// ── SOCKET EVENTS ─────────────────────────────────────────

io.on('connection', (socket) => {

  socket.on('user:join', ({ userId, userName, role }) => {
    if (!userId || !userName || !role) return;

    socket.userId   = userId;
    socket.userName = userName;
    socket.role     = role;
    onlineUsers[socket.id] = { id: userId, name: userName, role };

    socket.join(roomName(userId));

    if (role === 'admin') {
      socket.join('admins');
      Object.keys(conversations).forEach(uid => socket.join(roomName(uid)));
      socket.emit('admin:conversations', buildConversationList());
      io.to('users').emit('admin:online');
      console.log(`[ADMIN] ${userName} connected`);
    } else {
      socket.join('users');
      const history = conversations[userId] || [];
      socket.emit('history', history);
      io.to('admins').emit('user:online', { userId, userName });
      if (isAnyAdminOnline()) socket.emit('admin:online');
      console.log(`[USER] ${userName} (${userId}) connected`);
    }
  });

  // User sends message to admin
  socket.on('message:send', ({ text }) => {
    if (socket.role !== 'user') return;
    if (!text || !text.trim()) return;

    const msg = {
      id:        Date.now(),
      from:      'user',
      userId:    socket.userId,
      userName:  socket.userName || 'User',
      text:      text.trim(),
      timestamp: new Date().toISOString(),
      read:      false
    };

    if (!conversations[socket.userId]) conversations[socket.userId] = [];
    conversations[socket.userId].push(msg);

    socket.emit('message:new', msg);
    io.to('admins').emit('message:new', msg);
    io.to('admins').emit('admin:conversations', buildConversationList());
  });

  // Admin replies to specific user
  socket.on('admin:reply', ({ toUserId, text }) => {
    if (socket.role !== 'admin') return;
    if (!text || !text.trim() || !toUserId) return;

    const msg = {
      id:        Date.now(),
      from:      'admin',
      userId:    toUserId,
      userName:  socket.userName || 'Support',
      text:      text.trim(),
      timestamp: new Date().toISOString(),
      read:      false
    };

    if (!conversations[toUserId]) conversations[toUserId] = [];
    conversations[toUserId].push(msg);

    io.to(roomName(toUserId)).emit('message:new', msg);
    io.to('admins').emit('message:new', msg);
    io.to('admins').emit('admin:conversations', buildConversationList());
  });

  // Admin opens a conversation — mark as read
  socket.on('admin:open', ({ userId }) => {
    if (socket.role !== 'admin' || !userId) return;
    const history = conversations[userId] || [];
    history.forEach(m => { if (m.from === 'user') m.read = true; });
    socket.emit('history', history);
    socket.emit('admin:conversations', buildConversationList());
  });

  // Typing
  socket.on('typing:start', ({ toUserId } = {}) => {
    if (socket.role === 'user') {
      io.to('admins').emit('typing:show', { userId: socket.userId, userName: socket.userName || 'User' });
    } else if (socket.role === 'admin' && toUserId) {
      io.to(roomName(toUserId)).emit('typing:show', { userName: socket.userName || 'Support' });
    }
  });

  socket.on('typing:stop', ({ toUserId } = {}) => {
    if (socket.role === 'user') {
      io.to('admins').emit('typing:hide', { userId: socket.userId });
    } else if (socket.role === 'admin' && toUserId) {
      io.to(roomName(toUserId)).emit('typing:hide', {});
    }
  });

  // Disconnect
  socket.on('disconnect', () => {
    const user = onlineUsers[socket.id];
    if (!user) return;
    delete onlineUsers[socket.id];

    if (user.role === 'user') {
      io.to('admins').emit('user:offline', { userId: user.id });
      console.log(`[USER OFFLINE] ${user.name} (${user.id})`);
    } else if (user.role === 'admin') {
      if (!isAnyAdminOnline()) io.to('users').emit('admin:offline');
      console.log(`[ADMIN OFFLINE] ${user.name}`);
    }
  });

});

const PORT = process.env.PORT || 3000;
httpServer.listen(PORT, () => console.log(`🚀 Noble Support Server running on port ${PORT}`));