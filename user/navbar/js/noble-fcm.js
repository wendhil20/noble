// noble-fcm.js
// I-include ito sa mga pages ng user (hindi admin)
// Example: <script src="/js/noble-fcm.js"></script>

// Firebase config — same sa firebaseConfig mo
const firebaseConfig = {
  apiKey:            "AIzaSyDyp9nm7eBKjIPIk2EUoGGkRiF3WL6DvNk",
  authDomain:        "noblehome-c6783.firebaseapp.com",
  projectId:         "noblehome-c6783",
  storageBucket:     "noblehome-c6783.firebasestorage.app",
  messagingSenderId: "348681411383",
  appId:             "1:348681411383:web:32d7e1c577f20ead0318b2"
};

// VAPID key mo
const VAPID_KEY = "kX-61MMvdb9bM0dJgAykkgGvbN7cXFl1Q2ECq1Ct8CQ";

// I-initialize ang FCM
async function initFCM(userId, socket) {
  try {
    // I-import ang Firebase modules
    const { initializeApp }   = await import('https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js');
    const { getMessaging, getToken, onMessage } = await import('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging.js');

    const app       = initializeApp(firebaseConfig);
    const messaging = getMessaging(app);

    // Humingi ng permission sa user
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      console.warn('[FCM] Notification permission denied.');
      return;
    }

    // Kunin ang FCM token
    const token = await getToken(messaging, {
      vapidKey:          VAPID_KEY,
      serviceWorkerRegistration: await navigator.serviceWorker.register('/firebase-messaging-sw.js')
    });

    if (token) {
      console.log('[FCM] Token received:', token.slice(0, 20) + '...');

      // I-send ang token sa server via Socket.io
      socket.emit('notif:register-token', { userId, token });
    }

    // Handle foreground messages (kapag naka-bukas ang site)
    onMessage(messaging, (payload) => {
      console.log('[FCM] Foreground message:', payload);
      const { title, body } = payload.notification || {};

      // Pwede kang mag-show ng custom toast/notification dito
      showToastNotification(title, body);
    });

  } catch (err) {
    console.error('[FCM] Init error:', err);
  }
}

// Simple toast notification para sa foreground messages
function showToastNotification(title, body) {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed; top: 20px; right: 20px; z-index: 9999;
    background: #1e293b; color: white; padding: 16px 20px;
    border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    max-width: 320px; animation: slideIn 0.3s ease;
    border-left: 4px solid #f97316;
  `;
  toast.innerHTML = `
    <div style="font-weight:600; margin-bottom:4px;">${title || 'Noble Home'}</div>
    <div style="font-size:0.875rem; opacity:0.85;">${body || ''}</div>
  `;
  document.body.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.5s'; setTimeout(() => toast.remove(), 500); }, 4000);
}

// I-export para magamit sa ibang scripts
window.initFCM = initFCM;