// noble-fcm.js
// I-upload sa: user/navbar/js/noble-fcm.js
// I-include sa navbar: <script src="../navbar/js/noble-fcm.js"></script>

const firebaseConfig = {
  apiKey:            "AIzaSyDyp9nm7eBKjIPIk2EUoGGkRiF3WL6DvNk",
  authDomain:        "noblehome-c6783.firebaseapp.com",
  projectId:         "noblehome-c6783",
  storageBucket:     "noblehome-c6783.firebasestorage.app",
  messagingSenderId: "348681411383",
  appId:             "1:348681411383:web:32d7e1c577f20ead0318b2"
};

const VAPID_KEY = "kX-61MMvdb9bM0dJgAykkgGvbN7cXFl1Q2ECq1Ct8CQ";

async function initFCM(userId, socket) {
  try {
    if (!('Notification' in window)) {
      console.warn('[FCM] Browser does not support notifications.');
      return;
    }

    if (!('serviceWorker' in navigator)) {
      console.warn('[FCM] Service Worker not supported.');
      return;
    }

    // Import Firebase
    const { initializeApp }   = await import('https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js');
    const { getMessaging, getToken, onMessage } = await import('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging.js');

    const app       = initializeApp(firebaseConfig);
    const messaging = getMessaging(app);

    // Request permission
    console.log('[FCM] Requesting permission...');
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
      console.warn('[FCM] Permission denied.');
      return;
    }

    console.log('[FCM] Permission granted!');

    // Register service worker
    const swReg = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
    console.log('[FCM] Service Worker registered.');

    // Get FCM token
    const token = await getToken(messaging, {
      vapidKey: VAPID_KEY,
      serviceWorkerRegistration: swReg
    });

    if (token) {
      console.log('[FCM] Token received:', token.slice(0, 20) + '...');

      // Save token to DB via PHP
      await fetch('/user/navbar/save-fcm-token.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ userId, token })
      });

      console.log('[FCM] Token saved to server!');

      // Also emit to socket if available
      if (socket && typeof socket.emit === 'function') {
        socket.emit('notif:register-token', { userId, token });
      }
    } else {
      console.warn('[FCM] No token received.');
    }

    // Handle foreground messages
    onMessage(messaging, (payload) => {
      console.log('[FCM] Foreground message:', payload);
      const { title, body } = payload.notification || {};
      showToastNotification(title, body);
    });

  } catch (err) {
    console.error('[FCM] Init error:', err);
  }
}

function showToastNotification(title, body) {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed; top: 20px; right: 20px; z-index: 99999;
    background: #1e293b; color: white; padding: 16px 20px;
    border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    max-width: 320px; font-family: 'Montserrat', sans-serif;
    border-left: 4px solid #f97316; cursor: pointer;
  `;
  toast.innerHTML = `
    <div style="font-weight:600; margin-bottom:4px; font-size:0.9rem;">
      🔔 ${title || 'Noble Home'}
    </div>
    <div style="font-size:0.8rem; opacity:0.85;">${body || ''}</div>
  `;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.transition = 'opacity 0.5s';
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 500);
  }, 5000);
}

window.initFCM = initFCM;