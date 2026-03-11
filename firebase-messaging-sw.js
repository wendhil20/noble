// firebase-messaging-sw.js
// I-upload sa ROOT ng website: public_html/firebase-messaging-sw.js

importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey:            "AIzaSyDyp9nm7eBKjIPIk2EUoGGkRiF3WL6DvNk",
  authDomain:        "noblehome-c6783.firebaseapp.com",
  projectId:         "noblehome-c6783",
  storageBucket:     "noblehome-c6783.firebasestorage.app",
  messagingSenderId: "348681411383",
  appId:             "1:348681411383:web:32d7e1c577f20ead0318b2"
});

const messaging = firebase.messaging();

// Background message handler
messaging.onBackgroundMessage(function(payload) {
  console.log('[SW] Background message received:', payload);

  const { title, body } = payload.notification || {};
  const data = payload.data || {};

  self.registration.showNotification(title || 'Noble Home', {
    body:    body || 'You have a new notification.',
    icon:    '/user/img/logo.png',
    badge:   '/user/img/logo.png',
    vibrate: [200, 100, 200],
    data:    { link: data.link || '/' },
    actions: [
      { action: 'view',    title: 'View' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  });
});

// Click handler
self.addEventListener('notificationclick', function(event) {
  event.notification.close();

  if (event.action === 'dismiss') return;

  const link = event.notification.data?.link || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.focus();
          return;
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(link);
      }
    })
  );
});