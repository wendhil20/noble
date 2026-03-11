// firebase-messaging-sw.js
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey:            "AIzaSyDyp9nm7eBKjIPIk2EUoGGkRiF3WL6DvNk",
  authDomain:        "noblehome-c6783.firebaseapp.com",
  projectId:         "noblehome-c6783",
  storageBucket:     "noblehome-c6783.firebasestorage.app",
  messagingSenderId: "348681411383",
  appId:             "1:348681411383:web:32d7e1c577f20ead0318b2"
});

const messaging = firebase.messaging();

// ✅ Lalabas kahit naka-minimize o naka-lock ang browser
messaging.onBackgroundMessage((payload) => {
  console.log('Background message received:', payload);

  self.registration.showNotification(payload.notification.title, {
    body:  payload.notification.body,
    icon:  '/assets/img/logo.png',
    badge: '/assets/img/logo.png',
    data:  payload.data,
    vibrate: [200, 100, 200]
  });
});

// Click handler — mag-open ng link pag na-click ang notif
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const link = event.notification.data?.link || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url === link && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow(link);
    })
  );
});