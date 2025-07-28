const CACHE_NAME = 'givehub-cache-v1';
const OFFLINE_URLS = [
    '/',
    '/index.html',
    '/offline.html',
    '/app.js',
    '/style.css',
    '/img/white-logo.svg'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(OFFLINE_URLS))
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/offline.html'))
        );
        return;
    }
    event.respondWith(
        caches.match(event.request).then(resp => resp || fetch(event.request))
    );
});
