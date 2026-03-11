// firebase-messaging-sw.js
// I-lagay ito sa ROOT ng public folder mo (e.g. /public/firebase-messaging-sw.js)
// IMPORTANTENG DAPAT NASA ROOT — hindi pwede sa subdirectory!

importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');

// I-configure ang Firebase — PALITAN ng actual values mo
firebase.initializeApp({
  apiKey:            "AIzaSyDyp9nm7eBKjIPIk2EUoGGkRiF3WL6DvNk",
  authDomain:        "noblehome-c6783.firebaseapp.com",
  projectId:         "noblehome-c6783",
  storageBucket:     "noblehome-c6783.firebasestorage.app",
  messagingSenderId: "348681411383",
  appId:             "1:348681411383:web:32d7e1c577f20ead0318b2"
});

const messaging = firebase.messaging();

// Handler kapag may notification na natanggap kahit sarado ang browser
messaging.onBackgroundMessage(function(payload) {
  console.log('[SW] Background message received:', payload);

  const { title, body } = payload.notification || {};
  const data = payload.data || {};

  self.registration.showNotification(title || 'Noble Home', {
    body:    body || 'You have a new notification.',
    icon:    '/assets/img/logo.png',   // palitan ng logo mo
    badge:   '/assets/img/badge.png',  // optional
    vibrate: [200, 100, 200],
    data:    { link: data.link || '/' },
    actions: [
      { action: 'view', title: 'View' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  });
});

// Kapag nag-click ang user sa notification
self.addEventListener('notificationclick', function(event) {
  event.notification.close();

  if (event.action === 'dismiss') return;

  const link = event.notification.data?.link || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      // Kung may bukas na tab, i-focus na lang
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.focus();
          return;
        }
      }
      // Wala pang bukas na tab, magbukas ng bago
      if (clients.openWindow) {
        return clients.openWindow(link);
      }
    })
  );
});