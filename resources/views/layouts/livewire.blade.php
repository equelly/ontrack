<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dispatch System' }}</title>

    {{-- VITE (ОБЯЗАТЕЛЬНО!) --}}
    @vite([
        'resources/js/app.js',
        'resources/css/app.css',
    ])

    {{-- LIVEWIRE --}}
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100">

    {{-- Модалка входа при истечении сессии (419 Page Expired) --}}
    @include('includes.session-guard-modal')

    {{ $slot }}

    @livewireScripts
</body>
</html>
