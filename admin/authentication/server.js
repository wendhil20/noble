const express    = require('express');
const http       = require('http');
const { Server } = require('socket.io');
const mysql      = require('mysql2/promise');

const app        = express();
const httpServer = http.createServer(app);
const io         = new Server(httpServer, { cors: { origin: '*' } });

// ── DATABASE ──────────────────────────────────────────────
const db = mysql.createPool({
  host:     process.env.DB_HOST     || 'localhost',
  user:     process.env.DB_USER     || 'root',
  password: process.env.DB_PASS     || '',
  database: process.env.DB_NAME     || 'noble',
  waitForConnections: true,
  connectionLimit: 10
});

// ── DB HELPERS ────────────────────────────────────────────

async function saveMessage(msg) {
  await db.execute(
    `INSERT INTO chat_messages (user_id, admin_id, from_role, user_name, text, is_read, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [msg.userId, msg.adminId, msg.from, msg.userName, msg.text, msg.read ? 1 : 0, msg.timestamp]
  );
}

async function loadHistory(userId, adminId) {
  const [rows] = await db.execute(
    `SELECT * FROM chat_messages
     WHERE user_id = ? AND admin_id = ?
     ORDER BY created_at ASC`,
    [String(userId), String(adminId)]
  );
  return rows.map(r => ({
    id:        r.id,
    from:      r.from_role,
    userId:    r.user_id,
    adminId:   r.admin_id,
    userName:  r.user_name,
    text:      r.text,
    timestamp: r.created_at,
    read:      !!r.is_read
  }));
}

async function markRead(userId, adminId) {
  await db.execute(
    `UPDATE chat_messages SET is_read = 1
     WHERE user_id = ? AND admin_id = ? AND from_role = 'user' AND is_read = 0`,
    [String(userId), String(adminId)]
  );
}

async function buildAdminConversationList(adminId) {
  const [rows] = await db.execute(
    `SELECT
       user_id,
       MAX(user_name)   AS user_name,
       MAX(text)        AS last_text,
       MAX(created_at)  AS last_time,
       SUM(CASE WHEN from_role = 'user' AND is_read = 0 THEN 1 ELSE 0 END) AS unread
     FROM chat_messages
     WHERE admin_id = ?
     GROUP BY user_id
     ORDER BY last_time DESC`,
    [String(adminId)]
  );
  return rows.map(r => ({
    userId:      r.user_id,
    userName:    onlineUsers[r.user_id]?.name || r.user_name || 'Unknown',
    lastMessage: r.last_text || '',
    lastTime:    r.last_time || new Date(0).toISOString(),
    unread:      r.unread || 0,
    isOnline:    !!onlineUsers[r.user_id]
  }));
}

// ── IN-MEMORY STORES ──────────────────────────────────────
const onlineAdmins  = {};   // adminId  → { socketId, name, title }
const onlineUsers   = {};   // userId   → { socketId, name }
const userAdminMap  = {};   // userId   → adminId   (current session mapping)
const rateLimiter   = {};   // socketId → { count, resetAt }

// ── SECURITY HELPERS ──────────────────────────────────────

function sanitize(str, maxLen = 500) {
  if (typeof str !== 'string') return '';
  return str.replace(/<[^>]*>/g, '').trim().slice(0, maxLen);
}

function isRateLimited(socketId) {
  const now = Date.now();
  if (!rateLimiter[socketId] || now > rateLimiter[socketId].resetAt) {
    rateLimiter[socketId] = { count: 1, resetAt: now + 5000 };
    return false;
  }
  rateLimiter[socketId].count++;
  return rateLimiter[socketId].count > 10;
}

function isValidId(id) {
  return id && /^[a-zA-Z0-9_-]+$/.test(String(id)) && String(id).length < 64;
}

function getAdminList() {
  return Object.entries(onlineAdmins).map(([adminId, info]) => ({
    adminId,
    adminName: info.name,
    title:     info.title || 'Sales',
    isOnline:  true
  }));
}

// ── SOCKET EVENTS ─────────────────────────────────────────

io.on('connection', (socket) => {

  // ── JOIN ──────────────────────────────────────────────
  socket.on('user:join', async ({ userId, userName, role, title }) => {
    if (!isValidId(userId)) return;
    if (typeof userName !== 'string' || !userName.trim()) return;
    if (role !== 'user' && role !== 'admin') return;

    const cleanName = sanitize(userName, 50);
    if (!cleanName) return;

    socket.userId   = String(userId);
    socket.userName = cleanName;
    socket.role     = role;

    if (role === 'admin') {
      socket.title = sanitize(title || 'Sales', 30);
      onlineAdmins[socket.userId] = { socketId: socket.id, name: cleanName, title: socket.title };

      socket.join('admins');
      socket.join(`admin_${socket.userId}`);

      const convList = await buildAdminConversationList(socket.userId);
      socket.emit('admin:conversations', convList);
      io.to('users').emit('admins:list', getAdminList());
      console.log(`[ADMIN] ${cleanName} joined (id: ${socket.userId})`);

    } else {
      onlineUsers[socket.userId] = { socketId: socket.id, name: cleanName };
      socket.join('users');
      socket.join(`user_${socket.userId}`);
      socket.emit('admins:list', getAdminList());

      // Restore previous session mapping if any
      if (userAdminMap[socket.userId]) {
        const adminId = userAdminMap[socket.userId];
        const history = await loadHistory(socket.userId, adminId);
        socket.emit('history', history);
        if (history.length > 0) {
          io.to(`admin_${adminId}`).emit('user:online', { userId: socket.userId, userName: cleanName });
          const convList = await buildAdminConversationList(adminId);
          io.to(`admin_${adminId}`).emit('admin:conversations', convList);
        }
      }

      console.log(`[USER] ${cleanName} joined (id: ${socket.userId})`);
    }
  });

  // ── USER SELECTS AN ADMIN ────────────────────────────────
  socket.on('user:select-admin', async ({ adminId }) => {
    if (socket.role !== 'user') return;
    if (!isValidId(adminId)) return;

    if (!onlineAdmins[String(adminId)]) {
      socket.emit('error:admin-offline', { message: 'This admin is no longer online.' });
      return;
    }

    const userId = socket.userId;
    const aId    = String(adminId);
    userAdminMap[userId] = aId;

    // Load history from DB (persisted across deploys!)
    const history = await loadHistory(userId, aId);
    socket.emit('history', history);

    if (history.length > 0) {
      io.to(`admin_${aId}`).emit('user:online', { userId, userName: socket.userName });
      const convList = await buildAdminConversationList(aId);
      io.to(`admin_${aId}`).emit('admin:conversations', convList);
    }

    console.log(`[SELECT] User ${socket.userName} → Admin ${aId}`);
  });

  // ── USER SENDS MESSAGE ────────────────────────────────────
  socket.on('message:send', async ({ text }) => {
    if (socket.role !== 'user') return;
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

    // ✅ Save to DB first
    await saveMessage(msg);

    socket.emit('message:new', msg);
    io.to(`admin_${adminId}`).emit('message:new', msg);
    const convList = await buildAdminConversationList(adminId);
    io.to(`admin_${adminId}`).emit('admin:conversations', convList);

    console.log(`[MSG] ${socket.userName} → admin_${adminId}: ${clean}`);
  });

  // ── ADMIN REPLIES ─────────────────────────────────────────
  socket.on('admin:reply', async ({ toUserId, text }) => {
    if (socket.role !== 'admin') return;
    if (isRateLimited(socket.id)) return;

    const clean = sanitize(text);
    if (!clean || !toUserId) return;

    const adminId = socket.userId;
    const userId  = String(toUserId);

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

    // ✅ Save to DB
    await saveMessage(msg);

    io.to(`user_${userId}`).emit('message:new', msg);
    socket.emit('message:new', msg);
    const convList = await buildAdminConversationList(adminId);
    socket.emit('admin:conversations', convList);

    console.log(`[REPLY] admin_${adminId} → user_${userId}: ${clean}`);
  });

  // ── ADMIN OPENS CONVERSATION ──────────────────────────────
  socket.on('admin:open', async ({ userId }) => {
    if (socket.role !== 'admin' || !isValidId(userId)) return;

    const adminId = socket.userId;
    const uid     = String(userId);

    const history = await loadHistory(uid, adminId);
    if (history.length === 0 && userAdminMap[uid] !== adminId) return;

    // Mark all user messages as read
    await markRead(uid, adminId);

    socket.emit('history', history);
    const convList = await buildAdminConversationList(adminId);
    socket.emit('admin:conversations', convList);
  });

  // ── TYPING ────────────────────────────────────────────────
  socket.on('typing:start', ({ toUserId, toAdminId } = {}) => {
    if (socket.role === 'user') {
      const adminId = userAdminMap[socket.userId] || toAdminId;
      if (adminId && isValidId(adminId)) {
        io.to(`admin_${adminId}`).emit('typing:show', { userId: socket.userId, userName: socket.userName });
      }
    } else if (socket.role === 'admin' && isValidId(toUserId)) {
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

  // ── DISCONNECT ────────────────────────────────────────────
  socket.on('disconnect', async () => {
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
        const convList = await buildAdminConversationList(adminId);
        io.to(`admin_${adminId}`).emit('admin:conversations', convList);
      }
      console.log(`[USER OFFLINE] ${socket.userName}`);
    }
  });

});

const PORT = process.env.PORT || 3000;
httpServer.listen(PORT, () => console.log(`🚀 Noble Support Server on port ${PORT}`));