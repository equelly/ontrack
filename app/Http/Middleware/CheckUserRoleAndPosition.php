<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRoleAndPosition
{
    public function handle($request, Closure $next, ...$requirements)
    {
        $user = $request->user();
        
        if (!$user) {
            abort(403, 'Доступ запрещен: пользователь не авторизован.');
        }

        // Получаем массив разрешенных ролей/должностей из роута (например: ['driver', 'admin'])
        $allowed = is_array($requirements) ? $requirements : explode(',', $requirements);
        
        // Приводим всё к нижнему регистру для надежности
        $userRole = strtolower(trim($user->role ?? ''));
        $userPosition = strtolower(trim($user->position ?? ''));

        foreach ($allowed as $req) {
            $req = strtolower(trim($req));
            
            // Если требуемая роль совпадает с ролью ИЛИ должностью пользователя - пускаем
            if ($userRole === $req || $userPosition === $req) {
                return $next($request);
            }
        }

        abort(403, 'У вас нет прав доступа к этому разделу.');
    }
}