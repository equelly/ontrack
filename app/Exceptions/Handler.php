<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Список ошибок, которые мы хотим отображать кастомно.
     * 419 (TokenMismatchException / HttpException 419) —> отдаём JSON для AJAX-запросов,
     * чтобы фронтенд мог показать модалку логина без изменения URL.
     */
    public function render($request, Throwable $e)
    {
        // 419 — устаревший CSRF-токен / сессия
        if ($e instanceof TokenMismatchException
            || ($e instanceof HttpException && $e->getStatusCode() === 419)
        ) {
            // Для AJAX / Livewire / fetch — отдаём структурированный JSON
            if ($request->expectsJson() || $this->isLivewireRequest($request)) {
                return response()->json([
                    'message' => 'Сессия истекла. Пожалуйста, войдите снова.',
                    'type'    => 'session_expired',
                    'status'  => 419,
                ], 419);
            }

            // Для обычных браузерных запросов — отдаём страницу 419,
            // которая через JS покажет модалку логина без изменения URL.
            return response()->view('errors.419', [
                'message' => 'Сессия истекла. Пожалуйста, войдите снова.',
            ], 419);
        }

        return parent::render($request, $e);
    }

    /**
     * Определяем, что запрос пришёл от Livewire (по хедерам Livewire 3).
     */
    private function isLivewireRequest($request): bool
    {
        return $request->hasHeader('X-Livewire')
            || $request->is('livewire/*');
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}