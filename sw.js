/**
 * Service Worker para FitTracker App (PWA) - Corregido para evitar errores de red
 */
const CACHE_NAME = 'fittracker-cache-v4';
const ASSETS_TO_CACHE = [
  './',
  './index.html',
  './manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Usar addAll dinámico para que si falta un archivo no rompa el service worker
      return Promise.allSettled(
        ASSETS_TO_CACHE.map(url => cache.add(url))
      );
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Estrategia Network First: Intenta red primero, si no hay internet usa la caché
self.addEventListener('fetch', (event) => {
  // Ignorar peticiones que no sean GET o que apunten a endpoints dinámicos PHP
  if (event.request.method !== 'GET' || event.request.url.includes('.php')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        // Si no hay red, busca en la caché
        return caches.match(event.request);
      })
  );
});