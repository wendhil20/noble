// firebase-service.js
// Nasa server/ folder ito

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

async function sendPushNotification(fcmToken, title, body, data = {}) {
  if (!initialized) initFirebase();
  if (!fcmToken) return { success: false, error: 'No token' };

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
          icon:               '/user/img/logo.png',
          badge:              '/user/img/logo.png',
          vibrate:            [200, 100, 200],
          requireInteraction: true
        },
        fcmOptions: {
          link: data.link || '/'
        }
      }
    };

    const response = await admin.messaging().send(message);
    console.log(`[FCM] Sent OK → ${response}`);
    return { success: true, response };
  } catch (err) {
    console.error('[FCM] Send error:', err.message);
    return { success: false, error: err.message };
  }
}

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
          icon:               '/user/img/logo.png',
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