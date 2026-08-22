<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * ВАЖНО: /ajax-login исключён намеренно — этот эндпофт используется
     * для логина при истёкшей сессии (419 Page Expired), когда
     * CSRF-токен в форме уже невалиден. Безопасность обеспечивается
     * проверкой email+password через Auth::attempt().
     *
     * @var array<int, string>
     */
    protected $except = [
        'ajax-login',
    ];
}