const CACHE_NAME = 'vicia-static-v2';
const STATIC_ASSETS = [
  '/assets/css/variables.css',
  '/assets/css/reset.css',
  '/assets/css/layout.css',
  '/assets/css/components.css',
  '/assets/css/dashboard.css',
  '/assets/css/dark-mode.css',
  '/assets/css/pwa.css',
  '/assets/js/ajax.js',
  '/assets/js/app.js',
  '/assets/js/charts.js',
  '/assets/js/realtime.js',
  '/assets/js/pwa.js',
  '/assets/favicon.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/realtime/')) return;

  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      }))
    );
  }
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (error) {
    payload = { body: event.data ? event.data.text() : 'Nouvelle alerte Vicia Home.' };
  }

  const data = payload.data || {};
  const options = {
    body: payload.body || 'Nouvelle alerte Vicia Home.',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-192.png',
    tag: payload.tag || 'vicia-alert',
    renotify: true,
    requireInteraction: payload.requireInteraction === true,
    data
  };

  event.waitUntil(self.registration.showNotification(payload.title || 'Alerte Vicia Home', options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = event.notification.data?.url || '/alerts';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      const existing = clients.find((client) => 'focus' in client);
      if (existing) {
        return existing.navigate(target).then((client) => client.focus());
      }
      return self.clients.openWindow(target);
    })
  );
});

self.addEventListener('notificationclose', () => {});
