<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application
    | and redirecting them to their home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     */
    protected function redirectTo()
    {
        $user = auth()->user();

        return match($user->position) {
            'driver'             => '/driver',
            'dispatcher'         => '/dispatcher',
            'excavator_operator' => '/excavator',
            'master'             => '/master',
            default              => '/home'
        };
    }

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * AJAX-логин для модалки 419.
     *
     * Принимает JSON {email, password}, возвращает JSON с новым CSRF-токеном.
     * Не меняет URL — вызывающий JS сам решит, что делать дальше
     * (например, перезагрузить Livewire).
     *
     * @throws ValidationException
     */
    public function ajaxLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return response()->json([
                'success'    => true,
                'message'    => 'Вход выполнен успешно.',
                'csrf_token'  => csrf_token(),
                'user'        => [
                    'id'       => Auth::id(),
                    'name'     => Auth::user()->name,
                    'position' => Auth::user()->position,
                ],
                // Куда перенаправил бы обычный логин — фронтенд может этим воспользоваться
                'redirect_to' => $this->redirectTo(),
            ]);
        }

        // Неверные данные — 422 с описанием ошибок
        throw ValidationException::withMessages([
            'email' => [trans('auth.failed')],
        ]);
    }
}