<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Панель водителя</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Передаём truckId в глобальную переменную -->
    <script>
        window.truckId = {{ $truck->id ?? 'null' }};
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    @livewireStyles
    
    <div class="container mx-auto py-8">
        @if($truck)
            <livewire:driver-panel :truck="$truck" :key="$truck->id" />
        @else
            <div class="max-w-md mx-auto p-6 bg-yellow-50 rounded-lg text-center">
                <p class="text-yellow-700">За вами не закреплён самосвал</p>
            </div>
        @endif
    </div>
    
    @livewireScripts
</body>
</html>