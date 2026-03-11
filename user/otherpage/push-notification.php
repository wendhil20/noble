<script type="module">
  import { initializeApp }
    from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
  import { getMessaging, getToken, onMessage }
    from "https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging.js";

  // ── CONFIG ───────────────────────────────────────────
  const firebaseConfig = {
    apiKey:            "AIzaSyDyp9nm7eBKjIPIk2EUoGGkRiF3WL6DvNk",
    authDomain:        "noblehome-c6783.firebaseapp.com",
    projectId:         "noblehome-c6783",
    storageBucket:     "noblehome-c6783.firebasestorage.app",
    messagingSenderId: "348681411383",
    appId:             "1:348681411383:web:32d7e1c577f20ead0318b2"
  };

  const VAPID_KEY    = "kX-61MMvdb9bM0dJgAykkgGvbN7cXFl1Q2ECq1Ct8CQ";
  const EXPRESS_URL  = "https://support.noblehomedepot.com";
  const userId       = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;

  // Wag mag-proceed kung hindi naka-login
  if (!userId) {
    console.log('No user session, skipping push registration');
  } else {
    const app       = initializeApp(firebaseConfig);
    const messaging = getMessaging(app);

    // ── REGISTER PUSH ──────────────────────────────────
    async function registerPush() {
      try {
        // Check if browser supports notifications
        if (!('Notification' in window)) {
          console.log('Browser does not support notifications');
          return;
        }

        // Register service worker
        const swReg = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
        console.log('✅ Service worker registered');

        // Humingi ng permission
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
          console.log('❌ Notification permission denied');
          return;
        }

        // Kumuha ng FCM token
        const token = await getToken(messaging, {
          vapidKey:        VAPID_KEY,
          serviceWorkerRegistration: swReg
        });

        if (!token) {
          console.log('No FCM token received');
          return;
        }

        // I-save sa Express server
        const res = await fetch(`${EXPRESS_URL}/api/save-fcm-token`, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ userId, token })
        });

        const data = await res.json();
        if (data.success) {
          console.log('✅ Push notifications active para sa user', userId);
        }

      } catch (err) {
        console.error('Push registration error:', err);
      }
    }

    registerPush();

    // ── FOREGROUND NOTIFICATION ────────────────────────
    // Lalabas kahit bukas ang browser
    onMessage(messaging, (payload) => {
      console.log('Foreground message:', payload);

      const { title, body } = payload.notification;
      const link = payload.data?.link || '/';

      // Show browser notification
      if (Notification.permission === 'granted') {
        const notif = new Notification(title, {
          body:  body,
          icon:  '/assets/img/logo.png',
          badge: '/assets/img/logo.png',
        });

        notif.onclick = () => {
          window.focus();
          window.location.href = link;
          notif.close();
        };
      }
    });
  }
</script>