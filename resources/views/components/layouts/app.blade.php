<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Page TITLE' }}</title>
    
    <!-- 🔥 CSRF ПЕРВЫМ ДЕЛОМ! -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Стили -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.11.2/css/all.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap">
    @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/css/app.css'])
    
    {{-- 🔥 Livewire Styles В HEAD --}}
    @livewireStyles
</head>
<body class="font-roboto">
    {{ $slot }}
    
    {{-- 🔥 Livewire Scripts ПОСЛЕ контента --}}
    @livewireScripts
</body>
</html>
