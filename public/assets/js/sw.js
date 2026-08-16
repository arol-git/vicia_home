/**
 * public/assets/js/sw.js
 *
 * Service Worker pour PWA Vicia Home
 * Gère : caching de ressources, synchronisation en arrière-plan,
 * notifications push, et fonctionnalité offline
 */

const CACHE_VERSION = 'vicia-v1';
const CACHE_STATIC = `${CACHE_VERSION}-static`;
const CACHE_DYNAMIC = `${CACHE_VERSION}-dynamic`;
const CACHE_API = `${CACHE_VERSION}-api`;

const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/assets/css/variables.css',
  '/assets/css/reset.css',
  '/assets/css/layout.css',
  '/assets/css/components.css',
  '/assets/css/dashboard.css',
  '/assets/css/pwa.css',
  '/assets/js/ajax.js',
  '/assets/js/app.js',
  '/assets/js/charts.js',
  '/assets/js/voice.js',
  '/assets/img/favicon.png'
];

// ============================================================================
// Installation du Service Worker
// ============================================================================
self.addEventListener('install', event => {
  console.log('[SW] Installing service worker...');
  event.waitUntil(
    caches.open(CACHE_STATIC)
      .then(cache => {
        console.log('[SW] Precaching static assets');
        return cache.addAll(STATIC_ASSETS).catch(err => {
          console.warn('[SW] Some static assets failed to cache:', err);
        });
      })
      .then(() => self.skipWaiting())
  );
});

// ============================================================================
// Activation du Service Worker
// ============================================================================
self.addEventListener('activate', event => {
  console.log('[SW] Activating service worker...');
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(name => name.startsWith('vicia-') && name !== CACHE_STATIC && name !== CACHE_DYNAMIC && name !== CACHE_API)
            .map(name => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

// ============================================================================
// Gestion des requêtes
// ============================================================================
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorer les requêtes non-GET
  if (request.method !== 'GET') {
    return;
  }

  // Ignorer les requêtes externes non-HTTPS
  if (url.origin !== location.origin && !url.protocol.startsWith('https')) {
    return;
  }

  // API: network first, puis cache
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirstApi(request));
    return;
  }

  // Assets statiques: cache first, puis network
  if (isStaticAsset(url.pathname)) {
    event.respondWith(cacheFirstStatic(request));
    return;
  }

  // Pages HTML: network first, puis cache (avec offline fallback)
  if (request.mode === 'navigate' || url.pathname === '/') {
    event.respondWith(networkFirstPage(request));
    return;
  }

  // Par défaut: network first avec fallback cache
  event.respondWith(networkFirst(request));
});

// ============================================================================
// Stratégies de cache
// ============================================================================

/**
 * Cache First (Static Assets): utilise le cache d'abord, puis tente network
 */
function cacheFirstStatic(request) {
  return caches.match(request)
    .then(response => {
      if (response) {
        return response;
      }
      return fetch(request).then(response => {
        if (!response || response.status !== 200 || response.type === 'error') {
          return response;
        }
        const cache = caches.open(CACHE_STATIC);
        cache.then(c => c.put(request, response.clone()));
        return response;
      });
    })
    .catch(() => {
      // Fallback pour assets manquants
      return caches.match('/assets/img/favicon.png');
    });
}

/**
 * Network First (API): tente network d'abord, puis utilise le cache
 */
function networkFirstApi(request) {
  return fetch(request)
    .then(response => {
      if (!response || response.status !== 200) {
        throw new Error('Network response was not ok');
      }
      const cache = caches.open(CACHE_API);
      cache.then(c => c.put(request, response.clone()));
      return response;
    })
    .catch(() => {
      return caches.match(request)
        .then(response => response || new Response('API not available offline', { status: 503 }));
    });
}

/**
 * Network First (Pages): tente network, puis cache, avec offline fallback
 */
function networkFirstPage(request) {
  return fetch(request)
    .then(response => {
      if (!response || response.status !== 200) {
        throw new Error('Network response was not ok');
      }
      const cache = caches.open(CACHE_DYNAMIC);
      cache.then(c => c.put(request, response.clone()));
      return response;
    })
    .catch(() => {
      return caches.match(request)
        .then(response => response || cacheNotFound());
    });
}

/**
 * Network First (Par défaut)
 */
function networkFirst(request) {
  return fetch(request)
    .then(response => {
      if (!response || response.status !== 200) {
        throw new Error('Network response was not ok');
      }
      const cache = caches.open(CACHE_DYNAMIC);
      cache.then(c => c.put(request, response.clone()));
      return response;
    })
    .catch(() => {
      return caches.match(request)
        .then(response => response || cacheNotFound());
    });
}

/**
 * Réponse par défaut quand rien n'est trouvé en cache/network
 */
function cacheNotFound() {
  return new Response('Offline - Resource not available', {
    status: 503,
    statusText: 'Service Unavailable',
    headers: new Headers({
      'Content-Type': 'text/plain'
    })
  });
}

// ============================================================================
// Utilitaires
// ============================================================================

/**
 * Déterminer si une URL est un asset statique
 */
function isStaticAsset(pathname) {
  return /\.(js|css|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/i.test(pathname) ||
         pathname.startsWith('/assets/');
}

// ============================================================================
// Messages depuis le client
// ============================================================================

self.addEventListener('message', event => {
  const { type, payload } = event.data;

  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;
    case 'CLEAR_CACHE':
      clearCaches();
      break;
    default:
      console.log('[SW] Unknown message type:', type);
  }
});

/**
 * Effacer tous les caches (logout)
 */
function clearCaches() {
  caches.keys().then(cacheNames => {
    Promise.all(
      cacheNames
        .filter(name => name.startsWith('vicia-'))
        .map(name => caches.delete(name))
    );
  });
}

// ============================================================================
// Synchronisation en arrière-plan (Background Sync)
// ============================================================================

self.addEventListener('sync', event => {
  if (event.tag === 'sync-telemetry') {
    event.waitUntil(syncTelemetry());
  }
});

async function syncTelemetry() {
  try {
    // Implémenter la synchronisation des données de télémétrie
    console.log('[SW] Syncing telemetry...');
  } catch (error) {
    console.error('[SW] Sync failed:', error);
  }
}

// ============================================================================
// Notifications Push
// ============================================================================

self.addEventListener('push', event => {
  if (!event.data) {
    return;
  }

  const data = event.data.json();
  const options = {
    body: data.body || 'Vicia Home notification',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-96.png',
    tag: data.tag || 'vicia-notification',
    requireInteraction: data.requireInteraction || false,
    data: data.data || {}
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Vicia Home', options)
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if (client.url === '/' && 'focus' in client) {
            return client.focus();
          }
        }
        if (clients.openWindow) {
          return clients.openWindow('/');
        }
      })
  );
});

console.log('[SW] Service Worker loaded');
