/*
 * Service Worker для SiMqa — минимальная базовая стилизация.
 *
 * Стратегия: cache-first для статики, network-first для навигации,
 *  без кэширования AJAX/Livewire запросов.
 *
 * ВАЖНО: Livewire делает POST-запросы на /livewire/* — они НЕ кэшируются,
 *  иначе UI зависнет на старых данных.
 */

const CACHE_NAME = 'simqa-v1';
const STATIC_ASSETS = [
    // Базовая навигация (html-страница — не кэшируем, всегда из сети)
    // Кэшируем только статику, чтобы PWA запускалось быстрее
];

// Установка service worker + кэширование базовой статики
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Кэшируем базовую статику');
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                // Игнорируем ошибки кэширования — не критично
                console.warn('[SW] Не удалось закэшировать часть статики:', err);
            });
        })
    );
    // Активируем сразу, не дожидаясь выгрузки старого SW
    self.skipWaiting();
});

// Активация — чистим старые кэши
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => {
                        console.log('[SW] Удаляем старый кэш:', name);
                        return caches.delete(name);
                    })
            );
        })
    );
    // Берём контроль сразу на всех вкладках
    self.clients.claim();
});

// Стратегия обработки запросов
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // 1. Игнорируем не-GET запросы (POST/PUT/DELETE — всегда сеть)
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // 2. Игнорируем Livewire AJAX-запросы (POST к /livewire/update)
    //    и любые API-эндпоинты
    if (url.pathname.startsWith('/livewire/') ||
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/broadcasting/')) {
        return;
    }

    // 3. HTML-документы (навигация) — network-first, fallback на кэш
    if (request.mode === 'navigate' ||
        (request.headers.get('accept') || '').includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Кэшируем свежий HTML для офлайн-режима
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, clone);
                    });
                    return response;
                })
                .catch(() => {
                    // Нет сети — отдаём из кэша
                    return caches.match(request).then((cached) => {
                        return cached || caches.match('/');
                    });
                })
        );
        return;
    }

    // 4. Статика (JS, CSS, изображения, шрифты) — cache-first, fallback на сеть
    if (url.origin === location.origin &&
        /\.(js|css|png|jpg|jpeg|svg|gif|webp|ico|woff2?|ttf|eot)$/.test(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    // В фоне обновляем кэш
                    fetch(request).then((response) => {
                        if (response && response.status === 200) {
                            const clone = response.clone();
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, clone);
                            });
                        }
                    }).catch(() => {});
                    return cached;
                }
                // Нет в кэше — грузим из сети и кэшируем
                return fetch(request).then((response) => {
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, clone);
                    });
                    return response;
                });
            })
        );
        return;
    }

    // 5. Всё остальное (Vite HMR в dev, websocket, etc.) — не трогаем
    //    Пусть браузер сам разбирается
});

// Обработка сообщений от страницы (например, принудительное обновление)
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});