// firebase-service.js
// Helper para mag-send ng FCM push notifications

const admin = require('firebase-admin');

let initialized = false;

function initFirebase() {
  if (initialized) return;
  try {
    const serviceAccount = process.env.FIREBASE_SERVICE_ACCOUNT
      ? JSON.parse(process.env.FIREBASE_SERVICE_ACCOUNT)
      : require('./serviceAccountKey.json');

    admin.initializeApp({
      credential: admin.credential.cert(serviceAccount)
    });

    initialized = true;
    console.log('✅ Firebase Admin initialized');
  } catch (err) {
    console.error('❌ Firebase init error:', err.message);
  }
}

/**
 * Mag-send ng push notification sa isang user
 * @param {string} fcmToken  - FCM token ng device ng user
 * @param {string} title     - Notification title
 * @param {string} body      - Notification body/message
 * @param {object} data      - Extra data (optional)
 */
async function sendPushNotification(fcmToken, title, body, data = {}) {
  if (!initialized) initFirebase();
  if (!fcmToken) return;

  try {
    const message = {
      token: fcmToken,
      notification: { title, body },
      data: Object.fromEntries(
        Object.entries(data).map(([k, v]) => [k, String(v)])
      ),
      webpush: {
        headers: { Urgency: 'high' },
        notification: {
          title,
          body,
          icon: '/assets/img/logo.png',  // palitan ng logo mo
          badge: '/assets/img/badge.png', // optional badge icon
          vibrate: [200, 100, 200],
          requireInteraction: true        // hindi mabilis mawawala
        },
        fcmOptions: {
          link: '/'  // i-redirect dito kapag nag-click
        }
      }
    };

    const response = await admin.messaging().send(message);
    console.log(`[FCM] Sent to ${fcmToken.slice(0, 20)}... → ${response}`);
    return { success: true, response };
  } catch (err) {
    console.error('[FCM] Send error:', err.message);
    return { success: false, error: err.message };
  }
}

/**
 * Mag-send ng push notification sa maraming users (multicast)
 * @param {string[]} tokens - Array ng FCM tokens
 * @param {string} title
 * @param {string} body
 * @param {object} data
 */
async function sendMulticast(tokens, title, body, data = {}) {
  if (!initialized) initFirebase();
  if (!tokens || tokens.length === 0) return;

  try {
    const message = {
      tokens,
      notification: { title, body },
      data: Object.fromEntries(
        Object.entries(data).map(([k, v]) => [k, String(v)])
      ),
      webpush: {
        headers: { Urgency: 'high' },
        notification: {
          title,
          body,
          icon: '/assets/img/logo.png',
          requireInteraction: true
        }
      }
    };

    const response = await admin.messaging().sendEachForMulticast(message);
    console.log(`[FCM] Multicast: ${response.successCount} ok, ${response.failureCount} failed`);
    return response;
  } catch (err) {
    console.error('[FCM] Multicast error:', err.message);
  }
}

initFirebase();

module.exports = { sendPushNotification, sendMulticast };