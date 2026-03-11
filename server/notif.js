// notif.js
const admin   = require('firebase-admin');
const express = require('express');
const router  = express.Router();

// ── FIREBASE INIT ─────────────────────────────────────────
if (!admin.apps.length) {
  admin.initializeApp({
    credential: admin.credential.cert({
      type: "service_account",
      project_id: "noblehome-c6783",
      private_key_id: "1d77a2e8595ddf5e1fbc029a04a51fff7009d1bc",
      private_key: "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC7emxpXDKHG+/u\nvk+4c56OZbsyBeeDxwIdxMk05WwFWbPCHU4AEUjMKAq1Y0hhMo96UFSNFujGCmOn\nypUGr4tv2vhQsIb63sDpdNwBG+dvn/9yOtUQY6YQCM2bwQvH6kNyAYKv2Qzkfy1U\nESjMSWesbHGfDx67aHSPKjQYDewPsXnFaFlxkWp2suCAX4XZQYb4jiPjASmxf5yr\nv9965/WKyvGp40xrcqDjks+afXCS06/VTbHe8pfvwp2lt2URvOa1qXyPajCjASPO\nRDpcWdSygQySzt51RWKYwS8gM3x1K7idSzmm9lmxe45j/grTDNC6bTbfweFlzdzr\nxAM9LObZAgMBAAECggEASWiS2aB5wnCcfsmzEoDnNU+9QEWSlQVQHVLoDBfHN8Mb\ngWcTGzCpZhNJuiOhfDmdd6HLsaEmTSFVSyamOsNy4D4H3CR6/fFJ7T/OQ3rnIMyv\n680Aj5XNONsRkyrAT6u5dAMGZo+hHjl0CMZdSLx3ZUrjJIp5mJ06grJeSortA2lo\n+QepBlWjGO/QLmxvmmsFoDRScHis0YDx5w2/agfHUn2jynaac3S6sKvcH863Fmxk\nr/rVVC/Rb0FhYxmfLZ1/vQ+dV6p+99l1Zpi8ajc2dttFZXidfp8vvVmlQy0Kd+kB\njsVPXWGXYIkRjwWHUcdKBDcjRgRbAOohTkSRfKMWfwKBgQDwmBA2Z0PAVrSIgrgB\njiclfek8XO12C3DIXRY608pb75KVE9a4AxyHSfp58F7w46qiYRF+NOcFWI7YfQZM\nGckX5QaduQ8nkBnAP5M2l775WgyNsooPi9wZCvw9lsyeFUxnwHXctW6u+QL7XMTd\nFwPZ6uvY6Xjl6zP88BrH15QxDwKBgQDHe6jpix4HmD2Uo0Uz41N7sFh91GhbATx7\nCCFviugOHR5U1W7UXQMAz368+DzyWO1+mTdTUCE24+PNA+j5uzJRtHchqfqUsKK7\nOKMHOF2TxZNYDo/87IHMwNPqmaUQE1n2KGNlPyJK9uBp90koJ6Py5mD8JT74n4GO\nYAf8oW6ZlwKBgAdw/JdiLENHqz/Joz1REz7inRMj4KhVBED+OBDLuieLymHYAj0g\nw4IftKKO37Ddqcpp7CuWIUsWCR0DCO3Toled2s2ICsLzfwhmLvxyRxLZSSgczI5c\nigswPsr83glJqVpQJpUT+39n7kKuBNy9uH3F+VN1LSsXUj1Rg9KIhWnbAoGBALt8\nwjkeg87ni5lUCwrFsgUirUk2hg5ijxGjhqlriMcbHxLktxHpiZUNcDTzq3SrmCvQ\nnWs0eMM6VTSvZByzkIuybfW56MYvbgNBLBjxJSJqJB4zMamqMCTdZ0+rsLP3PCpb\ns2/JctW6SxnNTXjsKO93D9hsuU67u+yw3VDX+TdpAoGBAOa1xbHYMQF50BhHr+Hp\nv4yYsoyd4bXR4P60EG121aUzGmEtoKKqtyeVk+o/+rqXhnNTcoWS17lothNO65+O\nm9PUesk3gymXIrBYEFMv/SZBsWtJzws5jyh340hKQsIbkBxiIkmRw1xdmTfMz+KC\nLnYFP2Zv4KcW4mV3IU+qAstl\n-----END PRIVATE KEY-----\n",
      client_email: "firebase-adminsdk-fbsvc@noblehome-c6783.iam.gserviceaccount.com",
      client_id: "110602617921193972120",
      auth_uri: "https://accounts.google.com/o/oauth2/auth",
      token_uri: "https://oauth2.googleapis.com/token",
      auth_provider_x509_cert_url: "https://www.googleapis.com/oauth2/v1/certs",
      client_x509_cert_url: "https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-fbsvc%40noblehome-c6783.iam.gserviceaccount.com"
    })
  });
}

module.exports = function (io, db) {

  // ── SOCKET: real-time in-app ───────────────────────────
  io.on('connection', (socket) => {
    socket.on('register', (userId) => {
      socket.join(`user_${userId}`);
      console.log(`🔔 User ${userId} registered for notifications`);
    });
  });

  // ── POST /api/send-notif ───────────────────────────────
  router.post('/api/send-notif', express.json(), async (req, res) => {
    const secret = req.headers['x-api-secret'];
    if (secret !== (process.env.API_SECRET || 'noble-secret-2025')) {
      return res.status(403).json({ error: 'Forbidden' });
    }

    const { userId, actorId, type, title, message, link } = req.body;
    if (!userId || !title || !message) {
      return res.status(400).json({ error: 'Missing required fields' });
    }

    try {
      // 1️⃣ Real-time socket
      io.to(`user_${userId}`).emit('notification', {
        type:      type    || 'general',
        title,
        message,
        link:      link    || '/',
        actorId:   actorId || null,
        timestamp: new Date().toISOString()
      });

      // 2️⃣ Kunin FCM tokens
      const [rows] = await db.execute(
        `SELECT fcm_token FROM user_fcm_tokens WHERE user_id = ? AND active = 1`,
        [userId]
      );

      if (rows.length === 0) {
        return res.json({ success: true, pushed: false, reason: 'No FCM tokens' });
      }

      const tokens = rows.map(r => r.fcm_token).filter(Boolean);

      // 3️⃣ Send FCM push
      const fcmPayload = {
        notification: {
          title: title,
          body:  message,
        },
        data: {
          type:   type   || 'general',
          link:   link   || '/',
          userId: String(userId),
        },
        android: {
          priority: 'high',
          notification: {
            sound:     'default',
            channelId: 'noble_notifications',
            priority:  'high',
          }
        },
        apns: {
          headers: { 'apns-priority': '10' },
          payload: {
            aps: {
              sound:               'default',
              badge:               1,
              'content-available': 1
            }
          }
        },
        webPush: {
          headers: { Urgency: 'high' },
          notification: {
            title: title,
            body:  message,
            icon:  '/assets/img/logo.png',
          },
          fcmOptions: { link: link || '/' }
        },
        tokens: tokens
      };

      const response = await admin.messaging().sendEachForMulticast(fcmPayload);
      console.log(`✅ FCM: ${response.successCount} ok, ${response.failureCount} failed`);

      // 4️⃣ Clean up invalid tokens
      const cleanups = [];
      response.responses.forEach((resp, idx) => {
        if (!resp.success) {
          const code = resp.error?.code;
          if (
            code === 'messaging/invalid-registration-token' ||
            code === 'messaging/registration-token-not-registered'
          ) {
            cleanups.push(
              db.execute('UPDATE user_fcm_tokens SET active = 0 WHERE fcm_token = ?', [tokens[idx]])
            );
          }
        }
      });
      await Promise.all(cleanups);

      return res.json({
        success:      true,
        pushed:       true,
        successCount: response.successCount,
        failureCount: response.failureCount
      });

    } catch (err) {
      console.error('❌ FCM Error:', err);
      return res.status(500).json({ error: err.message });
    }
  });

  // ── POST /api/save-fcm-token ───────────────────────────
  router.post('/api/save-fcm-token', express.json(), async (req, res) => {
    const { userId, token } = req.body;
    if (!userId || !token) {
      return res.status(400).json({ error: 'Missing userId or token' });
    }

    try {
      await db.execute(
        `INSERT INTO user_fcm_tokens (user_id, fcm_token, active, created_at)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE active = 1, created_at = NOW()`,
        [userId, token]
      );
      console.log(`💾 Token saved for user ${userId}`);
      return res.json({ success: true });
    } catch (err) {
      console.error('❌ Save token error:', err);
      return res.status(500).json({ error: err.message });
    }
  });

  // ── GET /api/notif-health ──────────────────────────────
  router.get('/api/notif-health', (req, res) => {
    res.json({
      status:   'ok',
      firebase: admin.apps.length > 0 ? 'initialized' : 'error',
      time:     new Date().toISOString()
    });
  });

  return router;
};