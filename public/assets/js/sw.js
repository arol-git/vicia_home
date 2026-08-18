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
  console.log('[SW] 🔧 INSTALLATION du Service Worker commençante...');
  event.waitUntil(
    caches.open(CACHE_STATIC)
      .then(cache => {
        console.log('[SW] → Précaching ' + STATIC_ASSETS.length + ' ressources statiques...');
        return cache.addAll(STATIC_ASSETS).catch(err => {
          console.warn('[SW] ⚠️  Certaines ressources n\'ont pas pu être cachées:', err);
        });
      })
      .then(() => {
        console.log('[SW] ✅ Installation complète, skip waiting...');
        self.skipWaiting();
      })
  );
});

// ============================================================================
// Activation du Service Worker
// ============================================================================
self.addEventListener('activate', event => {
  console.log('[SW] 🚀 ACTIVATION du Service Worker commençante...');
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        const obsolete = cacheNames.filter(name => name.startsWith('vicia-') && name !== CACHE_STATIC && name !== CACHE_DYNAMIC && name !== CACHE_API);
        console.log('[SW] → Caches obsolètes trouvés:', obsolete);
        return Promise.all(
          obsolete.map(name => {
            console.log('[SW] → Suppression du cache:', name);
            return caches.delete(name);
          })
        );
      })
      .then(() => {
        console.log('[SW] ✅ Activation complète, claim clients...');
        return self.clients.claim();
      })
  );
});

// ============================================================================
// Gestion des requêtes
// ============================================================================
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  console.log('[SW] FETCH:', request.method, url.pathname);

  // Ignorer les requêtes non-GET
  if (request.method !== 'GET') {
    console.log('[SW] → Non-GET, ignoré');
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
  console.log('[SW] === PUSH EVENT RECU ===');
  console.log('[SW] Timestamp:', new Date().toISOString());

  if (!event.data) {
    console.error('[SW] ERREUR: Push event sans donnees!');
    return;
  }

  console.log('[SW] Donnees brutes disponibles');
  let data = {};
  try {
    data = event.data.json();
    console.log('[SW] SUCCES: Donnees JSON parsees:', data);
  } catch (e) {
    console.warn('[SW] ATTENTION: JSON parsing echoue, utilisation du texte brut:', e);
    data = { title: 'Vicia Home', body: event.data.text() };
  }

  const options = {
    body: data.body || 'Vicia Home notification',
    badge: '/assets/img/favicon.png',
    icon: '/assets/img/favicon.png',
    tag: data.tag || 'vicia-notification',
    requireInteraction: data.requireInteraction !== false,
    data: data.data || {}
  };

  console.log('[SW] Options de notification:', options);
  console.log('[SW] Affichage notification: "' + (data.title || 'Vicia Home') + '" / "' + options.body + '"');
  event.waitUntil(
    self.registration.showNotification(data.title || 'Vicia Home', options)
      .then(() => {
        console.log('[SW] SUCCES: Notification affichee');
      })
      .catch(err => {
        console.error('[SW] ERREUR affichage notification:', err);
      })
  );
});

self.addEventListener('notificationclick', event => {
  console.log('[SW] === NOTIFICATION CLICK ===');
  console.log('[SW] Notification data:', event.notification.data);
  event.notification.close();
  const notificationData = event.notification.data || {};
  const houseId = notificationData.house_id;
  const targetUrl = houseId ? `/dashboard?house=${houseId}` : '/dashboard';
  console.log('[SW] Navigation vers:', targetUrl);

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        console.log('[SW] Fenetre(s) trouvee(s):', clientList.length);
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if ('focus' in client) {
            console.log('[SW] Focus sur fenetre existante');
            return client.focus();
          }
        }
        if (clients.openWindow) {
          console.log('[SW] Ouverture nouvelle fenetre');
          return clients.openWindow(targetUrl);
        }
      })
      .catch(err => {
        console.error('[SW] ERREUR lors du click notification:', err);
      })
  );
});

console.log('[SW] Service Worker loaded');
