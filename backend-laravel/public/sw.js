/**
 * PLAUD Companion — Progressive Web App Service Worker
 *
 * Privacy rule: pages and API responses are NEVER cached. They contain
 * transcripts and account data that must not survive a logout or be readable
 * by the next person using a shared device. Only public static files are cached.
 * Paths are resolved against the registration scope so sub-folder installs work.
 */

const CACHE_NAME = 'plaud-companion-v2';
const SCOPE = self.registration.scope;
const asset = (path) => new URL(path, SCOPE).href;
const OFFLINE_URL = asset('offline.html');
const STATIC_ASSETS = [
  OFFLINE_URL,
  asset('manifest.json'),
  asset('icons/icon-192.png'),
  asset('icons/icon-512.png'),
];
const STATIC_PREFIXES = [asset('icons/'), asset('js/')];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .catch((err) => console.warn('[SW] Pre-cache failed:', err))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  // Deleting old caches also removes pages cached by v1, which stored signed-in pages.
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data === 'clear-cache') {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k)))));
  }
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET' || !req.url.startsWith(SCOPE)) {
    return;
  }

  // Pages: always from the network; show the offline page when there is no connection.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Public static files: cache first, refreshed from the network.
  const isStatic = STATIC_ASSETS.includes(req.url) || STATIC_PREFIXES.some((p) => req.url.startsWith(p));
  if (!isStatic) {
    return; // API calls, downloads and exports go straight to the network.
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req).then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
        }
        return res;
      }).catch(() => cached);
      return cached || network;
    })
  );
});
