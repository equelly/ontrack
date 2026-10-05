<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SiMqa')</title>
    
    <!-- CSRF-токен для Livewire и fetch -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Стили -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.11.2/css/all.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Chart.js для графиков в Аналитике (подключаем в layout, чтобы не реинжектить при Livewire-обновлениях) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    {{-- Livewire Styles в HEAD --}}
    @livewireStyles
</head>
<body class="font-roboto">
    {{-- Глобальный контейнер для toast-уведомлений --}}
    <div id="global-toast-container" class="fixed top-4 right-4 p-4 z-[100000] flex flex-col items-end gap-2 pointer-events-none"></div>

    {{-- Офлайн-индикатор (показывается когда нет связи с сервером) --}}
    {{-- Использует события window.online/offline + Livewire:disconnect/connect --}}
    {{-- Логика вынесена в Alpine.data('offlineIndicator', ...) в нижнем блоке <script> --}}
    <div x-data="offlineIndicator"
         x-show="show"
         x-cloak
         x-transition
         class="fixed top-0 left-0 right-0 z-[100001] bg-red-600 text-white text-center py-2 text-sm font-semibold shadow-lg">
        <div class="flex items-center justify-center gap-2">
            <i class="fas fa-wifi text-lg animate-pulse"></i>
            <span>Нет связи с сервером. Ждём восстановления Wi-Fi...</span>
            <span x-text="lastSeen ? '(посл. онлайн: ' + lastSeen.toLocaleTimeString() + ')' : ''"></span>
            <span x-show="pendingCount > 0"
                  x-text="'• В очереди: ' + pendingCount + ' действ.'"
                  class="ml-2 px-2 py-0.5 bg-white/20 rounded"></span>
        </div>
    </div>

    {{-- Индикатор очереди офлайн-действий (показывается когда есть pending actions) --}}
    <div x-data="offlineQueueIndicator"
         x-show="count > 0"
         x-cloak
         x-transition
         class="fixed bottom-4 right-4 z-[100000] bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 cursor-pointer hover:bg-blue-700"
         @click="flush()"
         title="Клик — отправить сейчас">
        <i class="fas fa-cloud-upload-alt text-lg" :class="flushing ? 'animate-pulse' : ''"></i>
        <span x-text="flushing ? 'Отправляем...' : ('В очереди: ' + count)"></span>
    </div>

    {{-- Модалка входа при истечении сессии (419 Page Expired) --}}
    @include('includes.session-guard-modal')

    {{ $slot }}

    @livewireScripts
  
    @livewireScriptConfig

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (data) => {
                const event = Array.isArray(data) ? data[0] : data;
                if (!event || !event.message) return;

                // Сначала пробуем локальный контейнер компонента, потом глобальный
                let container = document.getElementById('excavator-toast-container');
                if (!container) container = document.getElementById('driver-toast-container');
                if (!container) container = document.getElementById('global-toast-container');
                if (!container) return;

                const toast = document.createElement('div');

                const bgClass = event.type === 'success' ? 'bg-emerald-500' :
                               event.type === 'error'   ? 'bg-red-600'     :
                               event.type === 'warning' ? 'bg-amber-500'   :
                               'bg-blue-500';

                const icon = event.type === 'success' ? 'fa-check-circle'       :
                             event.type === 'error'   ? 'fa-exclamation-circle' :
                             event.type === 'warning' ? 'fa-exclamation-triangle' :
                             'fa-info-circle';

                // PERSISTENT: для error/warning — НЕ исчезает автоматически.
                // Только ручное закрытие по кнопке ×.
                // Эти типы = критические алерты (зона переполнена, поломка, etc.)
                // — пользователь должен явно закрыть или подтвердить.
                // Для info/success — авто-закрытие через 5 секунд (мягкие уведомления).
                const isSticky = event.type === 'error' || event.type === 'warning';

                // Если sticky — добавляем тонкую полоску-индикатор слева, чтобы
                // визуально отличать от исчезающих toast'ов
                toast.className = `${bgClass} text-white px-4 py-3 rounded-md shadow-lg flex items-center gap-2 text-sm ${isSticky ? 'max-w-md border-l-4 border-white/30' : 'max-w-xs'} pointer-events-auto`;
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'opacity 0.3s, transform 0.3s';

                // Кнопка закрытия с разными стилями для sticky vs обычных
                const closeBtnHtml = isSticky
                    ? `<button onclick="this.parentElement.remove()" class="ml-2 px-2 py-0.5 text-xs bg-white/20 hover:bg-white/30 rounded font-semibold uppercase">Закрыть</button>`
                    : `<button onclick="this.parentElement.remove()" class="ml-2 text-xl leading-none hover:opacity-70">&times;</button>`;

                toast.innerHTML = `
                    <i class="fas ${icon} text-lg flex-shrink-0"></i>
                    <span class="flex-1">${event.message}</span>
                    ${closeBtnHtml}
                `;
                container.appendChild(toast);

                // Анимация появления
                requestAnimationFrame(() => {
                    toast.style.opacity = '1';
                    toast.style.transform = 'translateX(0)';
                });

                // Авто-удаление ТОЛЬКО для не-sticky (info/success) — 5 секунд
                if (!isSticky) {
                    setTimeout(() => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(100%)';
                        setTimeout(() => toast.remove(), 300);
                    }, 5000);
                }
                // Sticky toast — НЕ удаляем автоматически.
                // Пользователь закроет вручную через кнопку «Закрыть».
                // AiAlert в БД остаётся в статусе 'new' до явного acknowledge
                // (через выпадающий список алертов у диспетчера).
            });
        });

        // ==========================================
        // Alpine-компонент: офлайн-индикатор
        // ==========================================
        // Отслеживает navigator.onLine и Livewire responses.
        // При offline — показывает красный баннер вверху страницы.
        // При возврате online — переподключает Echo + dispatch refresh-* в Livewire.
        //
        // ВАЖНО: нельзя использовать x-init с операторами (if, setInterval, etc.)
        // — Alpine падает с "Unexpected token". Поэтому логика в Alpine.data().
        document.addEventListener('alpine:init', () => {
            Alpine.data('offlineIndicator', () => ({
                show: false,
                online: navigator.onLine,
                livewireConnected: true,
                lastSeen: navigator.onLine ? new Date() : null,
                pendingCount: 0, // счётчик действий в OfflineQueue
                heartbeatInterval: null,

                init() {
                    // Слушаем window online — связь восстановлена
                    window.addEventListener('online', () => {
                        this.online = true;
                        this.lastSeen = new Date();
                        // Скрываем баннер с задержкой 500мс — Livewire должен успеть переподключиться
                        setTimeout(() => {
                            this.show = false;
                            this.onReconnect();
                        }, 500);
                    });

                    // Слушаем window offline — связь пропала
                    window.addEventListener('offline', () => {
                        this.online = false;
                        this.show = true;
                    });

                    // Слушаем Livewire responses — если идёт трафик, значит онлайн
                    Livewire.on('response', () => {
                        this.livewireConnected = true;
                        if (this.online) this.show = false;
                    });

                    // Heartbeat каждые 10 секунд: если Livewire не отвечает, считаем offline
                    this.heartbeatInterval = setInterval(() => {
                        if (!navigator.onLine || !this.livewireConnected) {
                            this.show = !navigator.onLine;
                        }
                        this.livewireConnected = false;
                        this.refreshPendingCount();
                    }, 5000);

                    // Слушаем изменения очереди офлайн-действий
                    window.addEventListener('offline-queue-changed', () => {
                        this.refreshPendingCount();
                    });

                    // Стартовая инициализация счётчика
                    this.refreshPendingCount();
                },

                // Обновить счётчик pending действий из OfflineQueue
                async refreshPendingCount() {
                    if (window.OfflineQueue) {
                        try {
                            this.pendingCount = await window.OfflineQueue.count();
                        } catch (e) {
                            console.warn('Failed to get offline queue count:', e);
                        }
                    }
                },

                // Действия при восстановлении связи
                onReconnect() {
                    // 1. Триггерим обновление данных в открытых панелях
                    Livewire.dispatch('refresh-master-data');
                    Livewire.dispatch('refresh-miner-data');
                    Livewire.dispatch('reload-truck-data');

                    // 2. Toast для пользователя
                    Livewire.dispatch('notify', [{
                        type: 'success',
                        message: 'Связь восстановлена — данные обновлены',
                    }]);

                    // 3. Переподключаем Echo (WebSocket), если он отвалился
                    if (window.Echo && window.Echo.connector) {
                        try {
                            window.Echo.connector.connect();
                        } catch (e) {
                            console.warn('Echo reconnect failed:', e);
                        }
                    }

                    // 4. Отправляем очередь офлайн-действий.
                    //    НЕ вызываем напрямую — OfflineQueue сам слушает window.online
                    //    и вызывает flush(). Если тут тоже вызвать — будет дублирование
                    //    flush'ей (видно по "[OfflineQueue] Already flushing, skip").
                    //    Просто ждём — OfflineQueue.flush() сам запустится.
                },
            }));

            // ==========================================
            // Alpine-компонент: индикатор очереди офлайн-действий
            // ==========================================
            // Синий кружок внизу справа — показывает сколько действий
            // накопилось в очереди. Клик — отправить вручную.
            Alpine.data('offlineQueueIndicator', () => ({
                count: 0,
                flushing: false,

                async init() {
                    await this.refresh();
                    // Слушаем изменения очереди
                    window.addEventListener('offline-queue-changed', () => this.refresh());
                    // Обновляем каждые 5 секунд
                    setInterval(() => this.refresh(), 5000);
                },

                async refresh() {
                    if (window.OfflineQueue) {
                        try {
                            this.count = await window.OfflineQueue.count();
                            this.flushing = window.OfflineQueue.isFlushing;
                        } catch (e) {
                            console.warn('Failed to refresh offline queue:', e);
                        }
                    }
                },

                async flush() {
                    if (window.OfflineQueue && navigator.onLine) {
                        this.flushing = true;
                        await window.OfflineQueue.flush();
                        this.flushing = false;
                        await this.refresh();
                    } else if (!navigator.onLine) {
                        Livewire.dispatch('notify', [{
                            type: 'warning',
                            message: 'Нет связи — действия останутся в очереди',
                        }]);
                    }
                },
            }));
        });

        // ==========================================
        // Echo-подписка экскаваторщика — ВЫНЕСЕНА в excavator-panel.blade.php
        // ==========================================
        // Раньше подписка была здесь в layout, но не работала из-за того что
        // window.subscribeToMinerChannel вызывалась ДО того как Livewire инициализировал
        // событие 'miner-selected'. Теперь код полностью внутри excavator-panel.blade.php,
        // внутри одного <script> + document.addEventListener('livewire:init', ...).
        // Это работает так же как DriverPanel (driver-panel.blade.php — у которого всё работает).
    </script>
</body>
</html>