/* Minimal service worker for installability/offline shell */
const CACHE_NAME = 'stair-shell-v2';
const URLS_TO_CACHE = [
  './',
  './favicon.svg',
  './images/stair-logo.svg',
  './manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(URLS_TO_CACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) => (k === CACHE_NAME ? Promise.resolve() : caches.delete(k))))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  event.respondWith(
    fetch(req)
      .then((res) => {
        const url = new URL(req.url);
        // Never cache arbitrary HTML navigations: stale pages embed stale CSRF tokens → 419 on POST.
        // Only cache the public shell (/) and static assets (e.g. svg).
        const isRootShell =
          req.mode === 'navigate' && (url.pathname === '/' || url.pathname === '');
        const isSvg = url.pathname.endsWith('.svg');
        if (url.origin === self.location.origin && (isRootShell || isSvg)) {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, copy)).catch(() => {});
        }
        return res;
      })
      .catch(() => caches.match(req).then((cached) => cached || caches.match('./')))
  );
});

