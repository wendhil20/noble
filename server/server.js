/**
 * Noble Home Depot — Chat Support Server
 * Node.js + Express + Socket.IO + MySQL
 * Compatible with Hostinger VPS / Node hosting
 *
 * Required env vars (or edit defaults below):
 *   DB_HOST, DB_USER, DB_PASS, DB_NAME, PORT
 *
 * npm install express socket.io mysql2
 */

const express        = require('express');
const http           = require('http');
const { Server }     = require('socket.io');
const mysql          = require('mysql2/promise');

const app    = express();
const server = http.createServer(app);

// ── PORT ──────────────────────────────────────────────────────────────────────
const PORT = process.env.PORT || 3000;

// ── MYSQL POOL ────────────────────────────────────────────────────────────────
let pool = null;
try {
  pool = mysql.createPool({
    host:               process.env.DB_HOST || 'localhost',
    user:               process.env.DB_USER || 'root',
    password:           process.env.DB_PASS || '',
    database:           process.env.DB_NAME || 'noble',
    waitForConnections: true,
    connectionLimit:    10,
    queueLimit:         0
  });
  console.log('✅ MySQL pool created');
} catch (err) {
  console.error('⚠️  MySQL pool failed:', err.message);
}

// ── SOCKET.IO ─────────────────────────────────────────────────────────────────
const io = new Server(server, {
  cors: {
    origin:      '*',
    methods:     ['GET', 'POST'],
    credentials: false
  },
  transports:        ['websocket', 'polling'],
  allowEIO3:         true,
  pingTimeout:       60000,
  pingInterval:      25000,
  upgradeTimeout:    30000,
  allowUpgrades:     true,
  perMessageDeflate: false
});

// ── HEALTH CHECK ──────────────────────────────────────────────────────────────
app.get('/', (req, res) => res.send('Noble Chat Server is running ✅'));

// ── IN-MEMORY PRESENCE (online status only — NOT messages) ───────────────────
// admins : Map<adminId, { socketId, adminName, title }>
// users  : Map<userId,  { socketId, userName, selectedAdminId }>
const admins = new Map();
const users  = new Map();

// ── DB HELPERS ────────────────────────────────────────────────────────────────

async function dbSaveMessage(userId, adminId, fromRole, userName, text) {
  if (!pool) return null;
  try {
    const [result] = await pool.execute(
      `INSERT INTO chat_messages (user_id, admin_id, from_role, user_name, text, is_read, created_at)
       VALUES (?, ?, ?, ?, ?, 0, NOW())`,
      [userId, adminId, fromRole, userName, text.substring(0, 500)]
    );
    return result.insertId;
  } catch (err) {
    console.error('[DB] saveMessage:', err.message);
    return null;
  }
}

async function dbGetHistory(userId, adminId, limit = 100) {
  if (!pool) return [];
  try {
    const [rows] = await pool.execute(
      `SELECT id, user_id, admin_id, from_role, user_name, text, is_read, created_at
       FROM chat_messages
       WHERE user_id = ? AND admin_id = ?
       ORDER BY created_at ASC
       LIMIT ?`,
      [userId, adminId, limit]
    );
    return rows.map(r => ({
      from:      r.from_role,
      userId:    String(r.user_id),
      adminId:   String(r.admin_id),
      userName:  r.user_name,
      text:      r.text,
      timestamp: r.created_at instanceof Date
                   ? r.created_at.toISOString()
                   : String(r.created_at),
      isRead:    !!r.is_read
    }));
  } catch (err) {
    console.error('[DB] getHistory:', err.message);
    return [];
  }
}

async function dbMarkRead(userId, adminId) {
  if (!pool) return;
  try {
    await pool.execute(
      `UPDATE chat_messages SET is_read = 1
       WHERE user_id = ? AND admin_id = ? AND from_role = 'user' AND is_read = 0`,
      [userId, adminId]
    );
  } catch (err) {
    console.error('[DB] markRead:', err.message);
  }
}

/**
 * Build sidebar conversation list for an admin — DB only.
 * Only users who actually sent/received messages will appear.
 */
async function sendConversationsToAdmin(adminId) {
  const info = admins.get(adminId);
  if (!info || !info.socketId) return;

  const convs = [];

  if (pool) {
    try {
      const [rows] = await pool.execute(
        `SELECT
           m.user_id,
           m.user_name,
           MAX(m.created_at) as lastTime,
           (
             SELECT m2.text FROM chat_messages m2
             WHERE m2.user_id = m.user_id AND m2.admin_id = m.admin_id
             ORDER BY m2.created_at DESC LIMIT 1
           ) as lastMessage,
           SUM(CASE WHEN m.from_role = 'user' AND m.is_read = 0 THEN 1 ELSE 0 END) as unread
         FROM chat_messages m
         WHERE m.admin_id = ?
         GROUP BY m.user_id, m.user_name
         ORDER BY MAX(m.created_at) DESC`,
        [adminId]
      );

      for (const row of rows) {
        const uInfo = users.get(String(row.user_id));
        convs.push({
          userId:      String(row.user_id),
          userName:    row.user_name,
          isOnline:    !!(uInfo && uInfo.socketId),
          lastMessage: row.lastMessage,
          lastTime:    row.lastTime instanceof Date
                         ? row.lastTime.toISOString()
                         : String(row.lastTime),
          unread:      Number(row.unread) || 0
        });
      }
    } catch (err) {
      console.error('[sendConversations]', err.message);
    }
  }

  io.to(info.socketId).emit('admin:conversations', convs);
}

function broadcastAdminList() {
  const list = [];
  admins.forEach((info, adminId) => {
    list.push({ adminId, adminName: info.adminName, title: info.title || 'Sales Representative' });
  });
  io.emit('admins:list', list);
}

// ── SOCKET EVENTS ─────────────────────────────────────────────────────────────
io.on('connection', (socket) => {
  console.log(`[connect] ${socket.id}`);

  let selfId   = null;
  let selfRole = null;

  // ── JOIN ────────────────────────────────────────────────────────────────────
  socket.on('user:join', (data) => {
    const { userId, userName, role, title } = data || {};
    if (!userId || !role) return;

    selfId   = String(userId);
    selfRole = role;

    if (role === 'admin') {
      admins.set(selfId, { socketId: socket.id, adminName: userName, title: title || 'Sales' });
      console.log(`[admin join] ${userName} (${selfId})`);
      sendConversationsToAdmin(selfId);   // fetch from DB on login
      broadcastAdminList();

    } else {
      const existing = users.get(selfId) || {};
      users.set(selfId, {
        socketId:        socket.id,
        userName:        userName,
        selectedAdminId: existing.selectedAdminId || null
      });
      console.log(`[user join] ${userName} (${selfId})`);

      // Send online admins to this user
      const list = [];
      admins.forEach((info, adminId) => {
        list.push({ adminId, adminName: info.adminName, title: info.title || 'Sales Representative' });
      });
      socket.emit('admins:list', list);

      // Notify admin if user was already chatting with them
      const uInfo = users.get(selfId);
      if (uInfo && uInfo.selectedAdminId) {
        const aInfo = admins.get(String(uInfo.selectedAdminId));
        if (aInfo && aInfo.socketId) {
          io.to(aInfo.socketId).emit('user:online', { userId: selfId, userName });
        }
      }
    }
  });

  // ── USER SELECTS AN ADMIN ────────────────────────────────────────────────────
  // NOTE: We do NOT update admin sidebar here — only when a real message is sent.
  socket.on('user:select-admin', async ({ adminId } = {}) => {
    if (!selfId || selfRole !== 'user' || !adminId) return;
    const adminIdStr = String(adminId);

    const uInfo = users.get(selfId);
    if (uInfo) {
      uInfo.selectedAdminId = adminIdStr;
      users.set(selfId, uInfo);
    }

    // Send full chat history from DB to user
    const hist = await dbGetHistory(selfId, adminIdStr);
    socket.emit('history', hist);

    // Let admin know user is online (presence only — no sidebar update yet)
    const aInfo = admins.get(adminIdStr);
    if (aInfo && aInfo.socketId) {
      io.to(aInfo.socketId).emit('user:online', {
        userId:   selfId,
        userName: uInfo ? uInfo.userName : 'Unknown'
      });
    }

    console.log(`[select] user ${selfId} → admin ${adminIdStr}`);
  });

  // ── ADMIN OPENS A CONVERSATION ───────────────────────────────────────────────
  socket.on('admin:open', async ({ userId } = {}) => {
    if (!selfId || selfRole !== 'admin' || !userId) return;
    const userIdStr = String(userId);

    const hist = await dbGetHistory(userIdStr, selfId);
    socket.emit('history', hist);

    await dbMarkRead(userIdStr, selfId);
    sendConversationsToAdmin(selfId);   // refresh unread counts

    console.log(`[admin:open] admin ${selfId} ↔ user ${userIdStr}`);
  });

  // ── USER SENDS A MESSAGE ─────────────────────────────────────────────────────
  socket.on('message:send', async ({ text } = {}) => {
    if (!selfId || selfRole !== 'user' || !text) return;
    const uInfo = users.get(selfId);
    if (!uInfo || !uInfo.selectedAdminId) return;

    const adminIdStr = String(uInfo.selectedAdminId);

    await dbSaveMessage(selfId, adminIdStr, 'user', uInfo.userName, text);

    const msg = {
      from:      'user',
      userId:    selfId,
      adminId:   adminIdStr,
      userName:  uInfo.userName,
      text:      text.substring(0, 500),
      timestamp: new Date().toISOString()
    };

    socket.emit('message:new', msg);

    const aInfo = admins.get(adminIdStr);
    if (aInfo && aInfo.socketId) {
      io.to(aInfo.socketId).emit('message:new', msg);
      sendConversationsToAdmin(adminIdStr);   // now update sidebar
    }

    console.log(`[msg user→admin] ${uInfo.userName}: ${text.substring(0, 40)}`);
  });

  // ── ADMIN SENDS A REPLY ──────────────────────────────────────────────────────
  socket.on('admin:reply', async ({ toUserId, text } = {}) => {
    if (!selfId || selfRole !== 'admin' || !toUserId || !text) return;
    const userIdStr = String(toUserId);
    const aInfo     = admins.get(selfId);
    const adminName = aInfo ? aInfo.adminName : 'Support';

    await dbSaveMessage(userIdStr, selfId, 'admin', adminName, text);

    const msg = {
      from:      'admin',
      userId:    userIdStr,
      adminId:   selfId,
      userName:  adminName,
      text:      text.substring(0, 500),
      timestamp: new Date().toISOString()
    };

    socket.emit('message:new', msg);

    const uInfo = users.get(userIdStr);
    if (uInfo && uInfo.socketId) {
      io.to(uInfo.socketId).emit('message:new', msg);
    }

    sendConversationsToAdmin(selfId);
    console.log(`[msg admin→user] ${adminName} → user ${userIdStr}: ${text.substring(0, 40)}`);
  });

  // ── TYPING ───────────────────────────────────────────────────────────────────
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

  // ── DISCONNECT ───────────────────────────────────────────────────────────────
  socket.on('disconnect', () => {
    console.log(`[disconnect] ${socket.id} (${selfRole} ${selfId})`);
    if (!selfId) return;

    if (selfRole === 'admin') {
      admins.delete(selfId);
      broadcastAdminList();
    } else {
      const uInfo = users.get(selfId);
      if (uInfo) {
        uInfo.socketId = null;
        users.set(selfId, uInfo);
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