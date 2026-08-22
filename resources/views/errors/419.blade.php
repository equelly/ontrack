{{--
    Страница 419 Page Expired — fallback для обычных (не-AJAX) запросов.
    Покажет модалку логина поверх минимального каркаса.
--}}
@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center">
        <div class="text-6xl mb-4">⌛</div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Сессия истекла</h1>
        <p class="text-gray-600 mb-6">
            В целях безопасности ваша сессия была завершена. <br>
            Войдите снова, чтобы продолжить работу — вы вернётесь на эту же страницу.
        </p>
        <a href="{{ route('login') }}" class="inline-block px-6 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-md font-medium transition">
            Перейти к входу
        </a>
    </div>
</div>

{{-- Модалка логина (откроется автоматически по событию session-expired) --}}
@include('includes.session-guard-modal')

<script>
    // Сразу открываем модалку — пользователь уже видел 419
    window.addEventListener('DOMContentLoaded', () => {
        window.dispatchEvent(new CustomEvent('session-expired'));
    });
</script>
@endsection