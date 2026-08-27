const CACHE_NAME = 'marcos-home-v5';
const BASE = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const MANIFEST = self.location.hostname.startsWith('fire.') ? `${BASE}/fire-manifest.webmanifest` : `${BASE}/manifest.webmanifest`;
const APP_SHELL = [`${BASE}/`, MANIFEST, `${BASE}/marcos-home-logo.jpg`];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  if (event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request, { cache: 'no-store' }).catch(() => caches.match(`${BASE}/`)));
    return;
  }

  const url = new URL(event.request.url);
  const isAppAsset = url.origin === self.location.origin && /\.(?:js|css|html)$/.test(url.pathname);

  if (isAppAsset) {
    event.respondWith(
      fetch(event.request, { cache: 'no-store' })
        .then(response => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
          }
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
      if (response.ok && url.origin === self.location.origin) {
        const copy = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
      }
      return response;
    }))
  );
});
