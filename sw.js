// Service worker — alleen aanwezig voor PWA-installeerbaarheid
// Geen caching: altijd verse data van de server

self.addEventListener('install', function() {
    self.skipWaiting();
});

self.addEventListener('activate', function() {
    self.clients.claim();
});

// Fetch-handler vereist voor installeerbaarheid
// Doet niets: browser handelt requests normaal af
self.addEventListener('fetch', function() {});
