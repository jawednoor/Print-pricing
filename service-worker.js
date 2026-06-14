const CACHE_NAME = 'basmahart-v11';

const STATIC_ASSETS = [
  './',
  './index.html',
  './categories.html',
  './books-magazines.html',
  './brochures-stickers.html',
  './folder.html',
  './letters-memos.html',
  './envelopes.html',
  './invoices.html',
  './manifest.json',
  './img/logo-app_1.png',
  './img/logo-albasmahart.png',
  './img/logo-albasmahart2.png',
  './img/bg.gif'
];

const NEVER_CACHE = [
  '/server.php',
  '/user-data.json',
  '/failed-logins.json',
  '/papers.json',
  '/settings.json',
  '/values.json'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      await Promise.allSettled(STATIC_ASSETS.map((asset) => cache.add(asset)));
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (NEVER_CACHE.some((path) => url.pathname.endsWith(path))) {
    event.respondWith(fetch(request, { cache: 'no-store' }));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match('./index.html')))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const network = fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      });
      return cached || network;
    })
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
