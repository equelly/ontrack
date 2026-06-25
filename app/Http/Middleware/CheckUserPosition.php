<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserPosition
{
    public function handle(Request $request, Closure $next, string $position): Response
    {
        $user = $request->user();
        
        logger()->info('User position check', [
            'user_id' => $user->id,
            'user_position' => $user->position,
            'user_position_raw' => var_export($user->position, true),
            'required_position' => $position,
            'user_data' => $user->toArray()
        ]);
        
        // Allow users with no position set
        if (empty($user->position)) {
            return $next($request);
        }
        
        $userPosition = strtolower(trim($user->position));
        $requiredPosition = strtolower(trim($position));
        
        if ($userPosition !== $requiredPosition) {
            logger()->error('Position mismatch', [
                'user_position' => $userPosition, 
                'required_position' => $requiredPosition,
                'user_data' => $user->toArray()
            ]);
            abort(403, 'Position mismatch');
        }

        return $next($request);
    }
}