/* Network-first service worker — Install App + Web Push system notifications. */
const CACHE_NAME = 'stair-shell-v6';
const URLS_TO_CACHE = [
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/manifest.webmanifest',
  '/favicon.svg'
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
  if (req.method !== 'GET') {
    return;
  }

  // Always hit network for navigations / HTML — avoids stale CSRF / offline shell issues.
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(fetch(req));
    return;
  }

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  const isStaticIcon =
    url.pathname.startsWith('/icons/') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.webmanifest');

  if (!isStaticIcon) {
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req)
        .then((res) => {
          if (res && res.ok) {
            const copy = res.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy)).catch(() => {});
          }
          return res;
        })
        .catch(() => cached);

      return cached || network;
    })
  );
});

/** System tray / lock-screen alerts when app is closed (Web Push). */
self.addEventListener('push', (event) => {
  let data = {
    title: 'Stair',
    body: '',
    url: '/',
    tag: 'stair',
    icon: '/icons/icon-192.png',
    badge: '/icons/icon-192.png',
  };

  try {
    if (event.data) {
      const parsed = event.data.json();
      if (parsed && typeof parsed === 'object') {
        data = Object.assign(data, parsed);
        if (parsed.message && !parsed.body) data.body = parsed.message;
      } else {
        data.body = event.data.text();
      }
    }
  } catch (_) {
    try {
      data.body = event.data ? event.data.text() : '';
    } catch (__) { /* ignore */ }
  }

  event.waitUntil(
    self.registration.showNotification(data.title || 'Stair', {
      body: data.body || '',
      icon: data.icon || '/icons/icon-192.png',
      badge: data.badge || '/icons/icon-192.png',
      tag: data.tag || 'stair',
      renotify: true,
      data: { url: data.url || '/' },
      vibrate: [120, 60, 120],
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';
  const abs = new URL(target, self.location.origin).href;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if ('focus' in client) {
          if (client.url.startsWith(self.location.origin)) {
            client.focus();
            if ('navigate' in client) {
              try { client.navigate(abs); } catch (_) { /* ignore */ }
            }
            return;
          }
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(abs);
      }
    })
  );
});
