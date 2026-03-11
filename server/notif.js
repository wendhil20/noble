// notif.js
// Nasa server/ folder ito

const { sendPushNotification } = require('./firebase-service');

module.exports = function (io, db) {

  // ─── Register FCM Token ───────────────────────────────────────────────────
  io.on('connection', (socket) => {

    socket.on('notif:register-token', async ({ userId, token }) => {
      if (!userId || !token) return;

      try {
        await db.promise().query(`
          INSERT INTO fcm_tokens (user_id, token, created_at)
          VALUES (?, ?, NOW())
          ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = NOW()
        `, [userId, token]);

        console.log(`[NOTIF] FCM token saved for user ${userId}`);
      } catch (err) {
        console.error('[NOTIF] Token save error:', err.message);
      }
    });

  });

  // ─── Send Notification Function ───────────────────────────────────────────
  async function sendNotification({ userId, actorId, type, title, message, link }) {
    try {
      // 1. Save to notifications table
      await db.promise().query(`
        INSERT INTO notifications (user_id, actor_id, type, message, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
      `, [userId, actorId || null, type || 'general', message, link || '/']);

      // 2. Emit via Socket.io (real-time kung naka-bukas ang browser)
      io.to(`user_${userId}`).emit('notification', {
        title,
        message,
        link,
        type
      });

      // 3. Send FCM push (kahit sarado ang browser)
      const [tokens] = await db.promise().query(
        'SELECT token FROM fcm_tokens WHERE user_id = ?',
        [userId]
      );

      if (tokens.length > 0) {
        for (const row of tokens) {
          await sendPushNotification(row.token, title, message, { link: link || '/' });
        }
        console.log(`[NOTIF] Push sent to user ${userId} (${tokens.length} device/s)`);
      } else {
        console.log(`[NOTIF] No FCM token for user ${userId}`);
      }

      return { success: true };
    } catch (err) {
      console.error('[NOTIF] Error:', err.message);
      return { success: false, error: err.message };
    }
  }

  return { sendNotification };
};