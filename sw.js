const CACHE = 'connect4it-v1';

// Bij installatie: sla de offline fallback op
self.addEventListener('install', function(e) {
    self.skipWaiting();
});

self.addEventListener('activate', function(e) {
    self.clients.claim();
});

// Network-first strategie: altijd verse data van de server
self.addEventListener('fetch', function(e) {
    if (e.request.method !== 'GET') return;
    e.respondWith(
        fetch(e.request).catch(function() {
            return caches.match(e.request);
        })
    );
});
