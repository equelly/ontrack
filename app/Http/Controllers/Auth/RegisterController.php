<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Регистрация нового пользователя.
 *
 * Пользователь выбирает только position (должность).
 * role (категория) вычисляется автоматически через User::roleForPosition().
 *
 * admin НЕ доступен для регистрации — админы создаются через админ-панель.
 */
class RegisterController extends Controller
{
    use RegistersUsers;

    /**
     * Куда перенаправить после успешной регистрации.
     * На основе должности (position).
     */
    protected function redirectTo()
    {
        $user = auth()->user();

        // Админ — в админ-панель
        if ($user->isAdmin()) {
            return '/admin';
        }

        return match ($user->position) {
            'driver'               => '/driver',
            'dispatcher'           => '/',
            'excavator_operator'  => '/excavator',
            'master'              => '/master',
            'mechanic',
            'electric',
            'maintenance_dispatcher' => '/maintenance',
            default                => '/home',
        };
    }

    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Валидация данных регистрации.
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'position' => ['required', 'string', 'in:' . implode(',', User::REGISTERABLE_POSITIONS)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /**
     * Создание нового пользователя.
     * role вычисляется автоматически из position.
     */
    protected function create(array $data)
    {
        $position = $data['position'];
        $role = User::roleForPosition($position);

        if (!$role) {
            // Этого не должно случиться — валидатор отсечёт некорректные position.
            // Но на всякий случай — fallback.
            $role = User::ROLE_OPERATIONAL;
        }

        return User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'role'     => $role,
            'position' => $position,
            'password' => Hash::make($data['password']),
        ]);
    }

    /**
     * Переопределяем showRegistrationForm, чтобы передать в шаблон
     * список доступных должностей с человекочитаемыми подписями.
     */
    public function showRegistrationForm()
    {
        $positions = [];
        foreach (User::REGISTERABLE_POSITIONS as $position) {
            $positions[$position] = User::POSITION_LABELS[$position] ?? $position;
        }

        // Группировка по категориям для удобства UI
        $groupedPositions = [
            'operational' => [
                'label' => User::ROLE_LABELS[User::ROLE_OPERATIONAL],
                'items' => array_filter($positions, fn($label, $key) =>
                    User::POSITION_ROLE_MAP[$key] === User::ROLE_OPERATIONAL,
                    ARRAY_FILTER_USE_BOTH),
            ],
            'maintenance' => [
                'label' => User::ROLE_LABELS[User::ROLE_MAINTENANCE],
                'items' => array_filter($positions, fn($label, $key) =>
                    User::POSITION_ROLE_MAP[$key] === User::ROLE_MAINTENANCE,
                    ARRAY_FILTER_USE_BOTH),
            ],
        ];

        return view('auth.register', [
            'groupedPositions' => $groupedPositions,
        ]);
    }
}
