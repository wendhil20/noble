/**
 * Noble Home Depot — Chat Support Logic
 * Messages are saved to MySQL (chat_messages table).
 * Exported as a factory function that accepts an http.Server instance.
 *
 * Requires: npm install socket.io mysql2
 */

const { Server } = require('socket.io');
const mysql      = require('mysql2/promise');

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
  console.log('✅ Chat MySQL pool created');
} catch (err) {
  console.error('⚠️  Chat MySQL pool failed:', err.message);
}

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
    console.error('[Chat DB] saveMessage error:', err.message);
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
    // Normalize to the same shape the client expects
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
    console.error('[Chat DB] getHistory error:', err.message);
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
    console.error('[Chat DB] markRead error:', err.message);
  }
}

async function dbUnreadCount(userId, adminId) {
  if (!pool) return 0;
  try {
    const [rows] = await pool.execute(
      `SELECT COUNT(*) as cnt FROM chat_messages
       WHERE user_id = ? AND admin_id = ? AND from_role = 'user' AND is_read = 0`,
      [userId, adminId]
    );
    return rows[0]?.cnt || 0;
  } catch (err) {
    return 0;
  }
}

// ── SOCKET.IO FACTORY ─────────────────────────────────────────────────────────
function createApp(server) {
  const io = new Server(server, {
    cors: {
      origin:      '*',
      methods:     ['GET', 'POST'],
      credentials: false
    },
    // Hostinger reverse proxy fix
    transports:        ['websocket', 'polling'],
    allowEIO3:         true,
    pingTimeout:       60000,
    pingInterval:      25000,
    upgradeTimeout:    30000,
    allowUpgrades:     true,
    perMessageDeflate: false
  });

  // ── IN-MEMORY STATE (online presence only) ────────────────────────────────
  // admins : Map<adminId, { socketId, adminName, title }>
  // users  : Map<userId,  { socketId, userName, selectedAdminId }>
  const admins = new Map();
  const users  = new Map();

  // ── HELPERS ───────────────────────────────────────────────────────────────

  function broadcastAdminList() {
    const list = [];
    admins.forEach((info, adminId) => {
      list.push({ adminId, adminName: info.adminName, title: info.title || 'Sales Representative' });
    });
    io.emit('admins:list', list);
  }

  async function sendConversationsToAdmin(adminId) {
    const info = admins.get(adminId);
    if (!info || !info.socketId) return;

    // Build conversation list from DB
    const convs = [];
    for (const [userId, uInfo] of users.entries()) {
      // Only include users that have selectedAdminId OR have history
      if (uInfo.selectedAdminId !== adminId) {
        // Check if there's any history
        if (pool) {
          try {
            const [rows] = await pool.execute(
              `SELECT COUNT(*) as cnt FROM chat_messages WHERE user_id = ? AND admin_id = ?`,
              [userId, adminId]
            );
            if (!rows[0]?.cnt) continue;
          } catch { continue; }
        } else continue;
      }

      // Get last message
      let lastMessage = null, lastTime = null;
      if (pool) {
        try {
          const [rows] = await pool.execute(
            `SELECT text, created_at FROM chat_messages
             WHERE user_id = ? AND admin_id = ?
             ORDER BY created_at DESC LIMIT 1`,
            [userId, adminId]
          );
          if (rows[0]) {
            lastMessage = rows[0].text;
            lastTime    = rows[0].created_at instanceof Date
                            ? rows[0].created_at.toISOString()
                            : String(rows[0].created_at);
          }
        } catch {}
      }

      const unread = await dbUnreadCount(userId, adminId);
      convs.push({
        userId,
        userName:    uInfo.userName,
        isOnline:    !!uInfo.socketId,
        lastMessage,
        lastTime,
        unread
      });
    }

    io.to(info.socketId).emit('admin:conversations', convs);
  }

  // ── SOCKET EVENTS ─────────────────────────────────────────────────────────
  io.on('connection', (socket) => {
    console.log(`[connect] socket ${socket.id}`);

    let selfId   = null;
    let selfRole = null;

    // ── JOIN ────────────────────────────────────────────────
    socket.on('user:join', (data) => {
      const { userId, userName, role, title } = data || {};
      if (!userId || !role) return;

      selfId   = String(userId);
      selfRole = role;

      if (role === 'admin') {
        admins.set(selfId, { socketId: socket.id, adminName: userName, title: title || 'Sales' });
        console.log(`[admin join] ${userName} (${selfId})`);
        sendConversationsToAdmin(selfId);
        broadcastAdminList();

      } else {
        const existing = users.get(selfId) || {};
        users.set(selfId, {
          socketId:        socket.id,
          userName:        userName,
          selectedAdminId: existing.selectedAdminId || null
        });
        console.log(`[user join] ${userName} (${selfId})`);

        const list = [];
        admins.forEach((info, adminId) => {
          list.push({ adminId, adminName: info.adminName, title: info.title || 'Sales Representative' });
        });
        socket.emit('admins:list', list);

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

    // ── USER SELECTS AN ADMIN ───────────────────────────────
    socket.on('user:select-admin', async ({ adminId } = {}) => {
      if (!selfId || selfRole !== 'user' || !adminId) return;
      const adminIdStr = String(adminId);

      const uInfo = users.get(selfId);
      if (uInfo) {
        uInfo.selectedAdminId = adminIdStr;
        users.set(selfId, uInfo);
      }

      // Send history from DB
      const hist = await dbGetHistory(selfId, adminIdStr);
      socket.emit('history', hist);

      const aInfo = admins.get(adminIdStr);
      if (aInfo && aInfo.socketId) {
        io.to(aInfo.socketId).emit('user:online', { userId: selfId, userName: uInfo ? uInfo.userName : 'Unknown' });
        sendConversationsToAdmin(adminIdStr);
      }

      console.log(`[select] user ${selfId} → admin ${adminIdStr}`);
    });

    // ── ADMIN OPENS A CONVERSATION ──────────────────────────
    socket.on('admin:open', async ({ userId } = {}) => {
      if (!selfId || selfRole !== 'admin' || !userId) return;
      const userIdStr = String(userId);

      const hist = await dbGetHistory(userIdStr, selfId);
      socket.emit('history', hist);

      // Mark messages as read
      await dbMarkRead(userIdStr, selfId);
      sendConversationsToAdmin(selfId);

      console.log(`[admin:open] admin ${selfId} opened conv with user ${userIdStr}`);
    });

    // ── USER SENDS A MESSAGE ────────────────────────────────
    socket.on('message:send', async ({ text } = {}) => {
      if (!selfId || selfRole !== 'user' || !text) return;
      const uInfo = users.get(selfId);
      if (!uInfo || !uInfo.selectedAdminId) return;

      const adminIdStr = String(uInfo.selectedAdminId);

      // Save to DB
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
        sendConversationsToAdmin(adminIdStr);
      }

      console.log(`[msg:user→admin] ${uInfo.userName}: ${text.substring(0, 40)}`);
    });

    // ── ADMIN SENDS A REPLY ─────────────────────────────────
    socket.on('admin:reply', async ({ toUserId, text } = {}) => {
      if (!selfId || selfRole !== 'admin' || !toUserId || !text) return;
      const userIdStr = String(toUserId);
      const aInfo     = admins.get(selfId);
      const adminName = aInfo ? aInfo.adminName : 'Support';

      // Save to DB
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
      console.log(`[msg:admin→user] ${adminName} → user ${userIdStr}: ${text.substring(0, 40)}`);
    });

    // ── TYPING ──────────────────────────────────────────────
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

    // ── DISCONNECT ──────────────────────────────────────────
    socket.on('disconnect', () => {
      console.log(`[disconnect] socket ${socket.id} (${selfRole} ${selfId})`);
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

  return io;
}

module.exports = createApp;