const CACHE_VERSION = 'samen-shell-v2';

const scopeUrl = new URL(self.registration.scope);
const appUrl = (path) => new URL(path.replace(/^\//, ''), scopeUrl).toString();
const offlineUrl = appUrl('public/offline.html');
const shellAssets = [
  offlineUrl,
  appUrl('public/assets/css/app.css'),
  appUrl('public/assets/js/app.js'),
  appUrl('pwa-icon/app-192'),
  appUrl('pwa-icon/app-512'),
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(shellAssets)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(offlineUrl)));
    return;
  }

  if (url.pathname.includes('/public/assets/')) {
    event.respondWith(
      fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
        }
        return response;
      }).catch(() => caches.match(request)),
    );
  }
});
