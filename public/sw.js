const CACHE_NAME = 'primatrack-v1';

// Install Service Worker
self.addEventListener('install', event => {
    self.skipWaiting();
});

// Activate Service Worker
self.addEventListener('activate', event => {
    event.waitUntil(clients.claim());
});

// Fetch (Bypass cache agar data GPS selalu real-time)
self.addEventListener('fetch', event => {
    event.respondWith(fetch(event.request));
});