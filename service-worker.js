// Budgetapp is een dynamische, sessie-gebonden app: paginadata wordt nooit
// gecached (dat zou verouderde saldi/CSRF-tokens kunnen serveren). De service
// worker cachet alleen de statische "shell" (CSS, iconen) en toont een
// offline-pagina als een navigatie zonder netwerk niet gehaald kan worden.
const CACHE_NAME = 'budgetapp-shell-v1';
const OFFLINE_URL = 'offline.html';
const PRECACHE_URLS = [
    'assets/css/app.css',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
    OFFLINE_URL,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
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
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    const url = new URL(request.url);
    if (url.pathname.includes('/assets/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                return response;
            }))
        );
    }
});
