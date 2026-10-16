/*
 * Service Worker для SiMqa — минимальная базовая стилизация.
 *
 * Стратегия: cache-first для статики, network-first для навигации,
 *  без кэширования AJAX/Livewire запросов.
 *
 * ВАЖНО: Livewire делает POST-запросы на /livewire/* — они НЕ кэшируются,
 *  иначе UI зависнет на старых данных.
 *
 * ВАЖНО: .ico НЕ кэшируется — браузер управляет favicon через собственный
 *  кэш. SW-кэширование favicon приводит к залипанию старой иконки.
 */

const CACHE_NAME = 'simqa-v3';
const STATIC_ASSETS = [];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS).catch(() => {}))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) =>
            Promise.all(cacheNames.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.pathname.startsWith('/livewire/') || url.pathname.startsWith('/api/') || url.pathname.startsWith('/broadcasting/')) return;

    if (request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html')) {
        event.respondWith(
            fetch(request).then((response) => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                return response;
            }).catch(() => caches.match(request).then((c) => c || caches.match('/')))
        );
        return;
    }

    // Статика (JS, CSS, изображения, шрифты) — cache-first
    // ВАЖНО: .ico и favicon.ico НЕ кэшируем
    if (url.origin === location.origin &&
        /\.(js|css|png|jpg|jpeg|svg|gif|webp|woff2?|ttf|eot)$/.test(url.pathname) &&
        !url.pathname.endsWith('favicon.ico')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    fetch(request).then((response) => {
                        if (response && response.status === 200) {
                            const clone = response.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                        }
                    }).catch(() => {});
                    return cached;
                }
                return fetch(request).then((response) => {
                    if (!response || response.status !== 200 || response.type !== 'basic') return response;
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    return response;
                });
            })
        );
    }
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
});