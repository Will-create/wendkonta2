/* Public assets only. Operational pages, uploads, AJAX and mutations are never cached. */
'use strict';
const build = new URL(self.location.href).searchParams.get('build') || 'initial';
const cacheName = 'salepro-public-' + build;
const assets = [
    '/offline.html', '/icons/icon-192x192.png', '/icons/icon-512x512.png',
    '/css/salepro-mobile.css', '/js/salepro-mobile.js', '/js/salepro-pwa.js'
];
self.addEventListener('install', event => {
    event.waitUntil(caches.open(cacheName).then(cache => cache.addAll(assets.map(asset =>
        new Request(new URL(asset, self.location.origin).href, {cache: 'reload', credentials: 'omit'})))));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys
        .filter(key => key.startsWith('salepro-public-') && key !== cacheName)
        .map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'ACTIVATE_UPDATE') self.skipWaiting();
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => {
            const fallback = await (await caches.open(cacheName)).match('/offline.html');
            return new Response(fallback ? await fallback.text() : 'Connect to continue.', {
                status: 200, headers: {'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store', 'X-SalePro-Offline': 'true'}
            });
        }));
    } else if (assets.includes(url.pathname)) {
        event.respondWith(caches.open(cacheName).then(async cache => {
            const stored = await cache.match(url.pathname);
            if (stored) return stored;
            const response = await fetch(request);
            if (response.ok && response.type === 'basic') await cache.put(url.pathname, response.clone());
            return response;
        }));
    }
});
