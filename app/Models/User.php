<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User — модель пользователя (персонал карьера).
 *
 * Двухуровневая система прав:
 *   - role      — категория персонала (operational / maintenance / admin)
 *   - position  — конкретная должность (driver / master / mechanic / etc.)
 *
 * Связь role ↔ position:
 *   operational  → driver, master, dispatcher, excavator_operator
 *   maintenance  → mechanic, electric, maintenance_dispatcher
 *   admin        → admin (есть доступ ко всему)
 *
 * Регистрация:
 *   Пользователь выбирает только position → role вычисляется автоматически.
 *
 * Middleware:
 *   - CheckUserRoleAndPosition — проверяет и role, и position.
 *     Например 'roles:driver' пропустит пользователя с position='driver'
 *     ИЛИ role='driver' (для обратной совместимости со старым кодом).
 *   - RoleMiddleware — удалён (не использовался корректно).
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';
    protected $guarded = []; // снимаем защиту для записи атрибутов в БД

    // ==========================================
    // КОНСТАНТЫ — КАТЕГОРИИ (role)
    // ==========================================
    const ROLE_OPERATIONAL = 'operational';   // эксплуатационный персонал
    const ROLE_MAINTENANCE = 'maintenance';  // обслуживающий персонал
    const ROLE_ADMIN       = 'admin';         // администратор

    // ==========================================
    // КОНСТАНТЫ — ДОЛЖНОСТИ (position)
    // ==========================================
    // Эксплуатационный персонал
    const POSITION_DRIVER              = 'driver';
    const POSITION_EXCAVATOR_OPERATOR  = 'excavator_operator';
    const POSITION_DISPATCHER          = 'dispatcher';
    const POSITION_MASTER              = 'master';
    // Обслуживающий персонал
    const POSITION_MECHANIC              = 'mechanic';
    const POSITION_ELECTRIC              = 'electric';
    const POSITION_MAINTENANCE_DISPATCHER = 'maintenance_dispatcher'; // диспетчер ТОиР
    // Особая должность — администратор
    const POSITION_ADMIN = 'admin';

    /**
     * Карта: position → role. Используется при регистрации и валидации.
     */
    public const POSITION_ROLE_MAP = [
        self::POSITION_DRIVER               => self::ROLE_OPERATIONAL,
        self::POSITION_EXCAVATOR_OPERATOR   => self::ROLE_OPERATIONAL,
        self::POSITION_DISPATCHER           => self::ROLE_OPERATIONAL,
        self::POSITION_MASTER               => self::ROLE_OPERATIONAL,
        self::POSITION_MECHANIC             => self::ROLE_MAINTENANCE,
        self::POSITION_ELECTRIC             => self::ROLE_MAINTENANCE,
        self::POSITION_MAINTENANCE_DISPATCHER => self::ROLE_MAINTENANCE,
        self::POSITION_ADMIN                => self::ROLE_ADMIN,
    ];

    /**
     * Человекочитаемые названия должностей — для UI (выпадающие списки, бейджи).
     */
    public const POSITION_LABELS = [
        self::POSITION_DRIVER               => 'Водитель самосвала',
        self::POSITION_EXCAVATOR_OPERATOR   => 'Машинист экскаватора',
        self::POSITION_DISPATCHER          => 'Диспетчер',
        self::POSITION_MASTER               => 'Мастер',
        self::POSITION_MECHANIC             => 'Механик',
        self::POSITION_ELECTRIC             => 'Электрик',
        self::POSITION_MAINTENANCE_DISPATCHER => 'Диспетчер ТОиР',
        self::POSITION_ADMIN                => 'Администратор',
    ];

    /**
     * Человекочитаемые названия категорий.
     */
    public const ROLE_LABELS = [
        self::ROLE_OPERATIONAL => 'Эксплуатационный персонал',
        self::ROLE_MAINTENANCE => 'Обслуживающий персонал',
        self::ROLE_ADMIN       => 'Администратор',
    ];

    /**
     * Должности, доступные для регистрации на странице /register.
     * admin исключён — админы создаются только через админ-панель.
     */
    public const REGISTERABLE_POSITIONS = [
        self::POSITION_DRIVER,
        self::POSITION_EXCAVATOR_OPERATOR,
        self::POSITION_DISPATCHER,
        self::POSITION_MASTER,
        self::POSITION_MECHANIC,
        self::POSITION_ELECTRIC,
        self::POSITION_MAINTENANCE_DISPATCHER,
    ];

    /**
     * Получить role для переданного position.
     * Возвращает null, если position неизвестен.
     */
    public static function roleForPosition(?string $position): ?string
    {
        if (!$position) {
            return null;
        }
        return self::POSITION_ROLE_MAP[$position] ?? null;
    }

    // ==========================================
    // ОТНОШЕНИЯ
    // ==========================================
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function sets()
    {
        return $this->belongsToMany(Set::class, 'set_users', 'user_id', 'set_id');
    }

    public function trucks(): HasMany
    {
        return $this->hasMany(Truck::class, 'driver_id');
    }

    public function miner(): BelongsTo
    {
        return $this->belongsTo(Miner::class);
    }

    // ==========================================
    // MASS-ASSIGNABLE
    // ==========================================
    protected $fillable = [
        'name',
        'role',
        'position',
        'email',
        'password',
        'miner_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ==========================================
    // ПРОВЕРКИ КАТЕГОРИЙ (role)
    // ==========================================
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOperational(): bool
    {
        return $this->role === self::ROLE_OPERATIONAL;
    }

    public function isMaintenance(): bool
    {
        return $this->role === self::ROLE_MAINTENANCE;
    }

    // ==========================================
    // ПРОВЕРКИ ДОЛЖНОСТЕЙ (position)
    // ==========================================
    public function isDriver(): bool
    {
        return $this->position === self::POSITION_DRIVER;
    }

    public function isExcavatorOperator(): bool
    {
        return $this->position === self::POSITION_EXCAVATOR_OPERATOR;
    }

    public function isDispatcher(): bool
    {
        return $this->position === self::POSITION_DISPATCHER;
    }

    public function isMaster(): bool
    {
        return $this->position === self::POSITION_MASTER;
    }

    public function isMechanic(): bool
    {
        return $this->position === self::POSITION_MECHANIC;
    }

    public function isElectric(): bool
    {
        return $this->position === self::POSITION_ELECTRIC;
    }

    public function isMaintenanceDispatcher(): bool
    {
        return $this->position === self::POSITION_MAINTENANCE_DISPATCHER;
    }

    /**
     * Может ли пользователь управлять заявками других исполнителей
     * (переназначать, закрывать чужие). Только maintenance_dispatcher + admin.
     */
    public function canManageMaintenanceOrders(): bool
    {
        return $this->isAdmin()
            || $this->isMaintenanceDispatcher();
    }

    /**
     * Человекочитаемая подпись должности.
     */
    public function positionLabel(): string
    {
        return self::POSITION_LABELS[$this->position] ?? $this->position;
    }

    /**
     * Человекочитаемая подпись категории.
     */
    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }
}
