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
    
    {{-- Livewire Styles в HEAD --}}
    @livewireStyles
</head>
<body class="font-roboto">
    {{-- Глобальный контейнер для toast-уведомлений --}}
    <div id="global-toast-container" class="fixed top-4 right-4 p-4 z-[100000] flex flex-col items-end gap-2 pointer-events-none"></div>

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
    </script>
</body>
</html>