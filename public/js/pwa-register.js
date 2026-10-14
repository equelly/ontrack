/*
 * Регистрация Service Worker для PWA SiMqa.
 *
 * Только в production — в dev-режиме SW мешает разработке (кэширует всё).
 * Регистрируем после загрузки страницы, чтобы не блокировать первый рендер.
 */

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Проверяем, что мы не на localhost с hot-reload
        // (Vite HMR ломается при включённом SW)
        const isLocalhost = Boolean(
            window.location.hostname === 'localhost' ||
            window.location.hostname === '127.0.0.1' ||
            // [::1] — IPv6 localhost
            window.location.hostname === '[::1]'
        );

        // На localhost подключаем SW только если явно указан ?sw=1
        // В production — всегда подключаем
        if (isLocalhost && !window.location.search.includes('sw=1')) {
            console.log('[PWA] Service Worker отключён на localhost (dev-режим). Добавьте ?sw=1 для теста.');
            return;
        }

        const swUrl = '/service-worker.js';

        navigator.serviceWorker
            .register(swUrl, { scope: '/' })
            .then((registration) => {
                console.log('[PWA] Service Worker зарегистрирован. Scope:', registration.scope);

                // Проверяем обновления каждый час (в фоне)
                setInterval(() => {
                    registration.update().catch(() => {
                        // Игнорируем ошибки сети — оффлайн нормальная ситуация
                    });
                }, 60 * 60 * 1000);

                // Обработка обновления — пользователь увидит кнопку «Обновить»
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (!newWorker) return;

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed') {
                            if (navigator.serviceWorker.controller) {
                                // Новая версия скачана и ждёт активации
                                console.log('[PWA] Доступна новая версия. Перезагрузите страницу.');
                                // Можно показать toast с кнопкой «Обновить»
                                if (window.Livewire) {
                                    window.Livewire.dispatch('notify', {
                                        type: 'info',
                                        message: 'Доступна новая версия SiMqa. Обновите страницу.',
                                    });
                                }
                            } else {
                                // Первая установка — кэш готов
                                console.log('[PWA] Кэш готов к офлайн-режиму.');
                            }
                        }
                    });
                });
            })
            .catch((error) => {
                console.warn('[PWA] Не удалось зарегистрировать Service Worker:', error);
            });

        // Когда SW заявляет о новой версии — можно слушать controllerchange
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) return;
            refreshing = true;
            console.log('[PWA] Новый Service Worker активирован, перезагружаем...');
            window.location.reload();
        });
    });
}
