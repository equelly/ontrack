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
    <div id="global-toast-container" class="fixed top-4 right-4 p-4 z-[9999] flex flex-col items-end gap-2 pointer-events-none"></div>

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

                const container = document.getElementById('global-toast-container');
                if (!container) return;

                const toast = document.createElement('div');

                const bgClass = event.type === 'success' ? 'bg-emerald-500' :
                               event.type === 'error'   ? 'bg-red-500'    :
                               event.type === 'warning' ? 'bg-amber-500'  :
                               'bg-blue-500';

                const icon = event.type === 'success' ? 'fa-check-circle'       :
                             event.type === 'error'   ? 'fa-exclamation-circle' :
                             event.type === 'warning' ? 'fa-exclamation-triangle' :
                             'fa-info-circle';

                toast.className = `${bgClass} text-white px-4 py-3 rounded-md shadow-lg flex items-center gap-2 text-sm max-w-xs pointer-events-auto`;
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'opacity 0.3s, transform 0.3s';
                toast.innerHTML = `
                    <i class="fas ${icon} text-lg flex-shrink-0"></i>
                    <span class="flex-1">${event.message}</span>
                    <button onclick="this.parentElement.remove()" class="ml-2 text-xl leading-none hover:opacity-70">&times;</button>
                `;
                container.appendChild(toast);

                // Анимация появления
                requestAnimationFrame(() => {
                    toast.style.opacity = '1';
                    toast.style.transform = 'translateX(0)';
                });

                // Авто-удаление через 5 секунд
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>