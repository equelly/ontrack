<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Order — заявка на ремонт/обслуживание оборудования.
 *
 * Жизненный цикл (поле status):
 *   new         — только что создана, ждёт исполнителя
 *   in_progress — исполнитель взял в работу
 *   paused      — пауза (ждём запчасти / разрешение / погоду)
 *   completed   — выполнена (в архиве)
 *   cancelled   — отменена автором или админом
 *
 * Тип работ (work_type) — определяет, кто из обслуживающего персонала может взять заявку:
 *   mechanic    — механик (canTakeBy: mechanic, maintenance_dispatcher)
 *   electric    — электрик (canTakeBy: electric, maintenance_dispatcher)
 *   hydraulic   — гидравлика (canTakeBy: mechanic, maintenance_dispatcher)
 *   to1         — ТО-1 (плановое)
 *   to2         — ТО-2 (плановое)
 *   tire_service — шиномонтаж (подкачка, обтяжка колёс)
 *   berm        — обваловка зоны
 *   other       — прочее (canTakeBy: any maintenance)
 *
 * Приоритет (priority):
 *   low      — низкий (не критично)
 *   medium   — средний (по умолчанию)
 *   high     — высокий (нужно в течение смены)
 *   critical — критический (оборудование простаивает, срочно)
 */
class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'orders';
    protected $guarded = [];

    // ==========================================
    // СТАТУСЫ
    // ==========================================
    const STATUS_NEW         = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_PAUSED      = 'paused';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_NEW         => 'Новая',
        self::STATUS_IN_PROGRESS => 'В работе',
        self::STATUS_PAUSED      => 'Пауза',
        self::STATUS_COMPLETED   => 'Выполнена',
        self::STATUS_CANCELLED   => 'Отменена',
    ];

    public const STATUS_COLORS = [
        self::STATUS_NEW         => 'amber',
        self::STATUS_IN_PROGRESS => 'blue',
        self::STATUS_PAUSED      => 'orange',
        self::STATUS_COMPLETED   => 'emerald',
        self::STATUS_CANCELLED   => 'red',
    ];

    // ==========================================
    // ТИПЫ РАБОТ
    // ==========================================
    const WORK_MECHANIC     = 'mechanic';
    const WORK_ELECTRIC     = 'electric';
    const WORK_TO1          = 'to1';
    const WORK_TO2          = 'to2';
    const WORK_TIRE_SERVICE = 'tire_service';
    const WORK_OTHER        = 'other';

    public const WORK_TYPE_LABELS = [
        self::WORK_MECHANIC     => 'Механика',
        self::WORK_ELECTRIC     => 'Электрика',
        self::WORK_TO1          => 'ТО-1 (плановое)',
        self::WORK_TO2          => 'ТО-2 (плановое)',
        self::WORK_TIRE_SERVICE => 'Шиномонтаж',
        self::WORK_OTHER        => 'Прочее',
    ];

    /**
     * Кто может взять заявку данного типа работ.
     * Возвращает массив должностей (position).
     * 'maintenance_dispatcher' может взять любую заявку (он — руководитель).
     */
    public const WORK_TYPE_ALLOWED_POSITIONS = [
        self::WORK_MECHANIC     => [User::POSITION_MECHANIC],
        self::WORK_ELECTRIC     => [User::POSITION_ELECTRIC],
        self::WORK_TO1          => [User::POSITION_MECHANIC, User::POSITION_ELECTRIC],
        self::WORK_TO2          => [User::POSITION_MECHANIC, User::POSITION_ELECTRIC],
        self::WORK_TIRE_SERVICE => [User::POSITION_MECHANIC],
        self::WORK_OTHER        => [User::POSITION_MECHANIC, User::POSITION_ELECTRIC],
    ];

    // ==========================================
    // ПРИОРИТЕТЫ
    // ==========================================
    const PRIORITY_LOW      = 'low';
    const PRIORITY_MEDIUM   = 'medium';
    const PRIORITY_HIGH     = 'high';
    const PRIORITY_CRITICAL = 'critical';

    public const PRIORITY_LABELS = [
        self::PRIORITY_LOW      => 'Низкий',
        self::PRIORITY_MEDIUM   => 'Средний',
        self::PRIORITY_HIGH     => 'Высокий',
        self::PRIORITY_CRITICAL => 'Критический',
    ];

    public const PRIORITY_COLORS = [
        self::PRIORITY_LOW      => 'slate',
        self::PRIORITY_MEDIUM   => 'blue',
        self::PRIORITY_HIGH     => 'amber',
        self::PRIORITY_CRITICAL => 'red',
    ];

    public const PRIORITY_WEIGHTS = [
        self::PRIORITY_LOW      => 1,
        self::PRIORITY_MEDIUM   => 2,
        self::PRIORITY_HIGH     => 3,
        self::PRIORITY_CRITICAL => 4,
    ];

    // ==========================================
    // ОТНОШЕНИЯ
    // ==========================================
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function mashine()
    {
        return $this->belongsTo(Mashine::class, 'mashine_id', 'id');
    }

    /**
     * Автор заявки (кто создал).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id_req', 'id');
    }

    /**
     * Исполнитель (старое поле user_exec — оставлено для обратной совместимости).
     * Используется как alias для assigned_to в старых вызовах.
     */
    public function userExec()
    {
        return $this->belongsTo(User::class, 'user_exec', 'id');
    }

    /**
     * Исполнитель (новое поле assigned_to — кто взял заявку в работу).
     * Для новых заявок — кто взял в работу. Для legacy user_exec — тот же человек.
     */
    public function assignee(): BelongsTo
    {
        // Если assigned_to пустой, fallback на старое поле user_exec
        return $this->belongsTo(User::class, 'assigned_to', 'id')
            ->withDefault(fn() => $this->userExec()->getResults());
    }

    // ==========================================
    // ДАТЫ
    // ==========================================
    public function getDateAsCarbonAttribute()
    {
        return Carbon::parse($this->created_at);
    }

    public function getDateExecAsCarbonAttribute()
    {
        return Carbon::parse($this->updated_at);
    }

    // ==========================================
    // ХЕЛПЕРЫ
    // ==========================================

    /**
     * Может ли пользователь взять заявку в работу?
     * Зависит от типа работ (work_type) и должности пользователя.
     */
    public function canBeTakenBy(User $user): bool
    {
        // Админ и диспетчер ТОиР могут взять любую заявку
        if ($user->isAdmin() || $user->isMaintenanceDispatcher()) {
            return true;
        }

        // Для заявки без work_type — может взять любой maintenance
        if (!$this->work_type) {
            return $user->isMaintenance();
        }

        $allowed = self::WORK_TYPE_ALLOWED_POSITIONS[$this->work_type] ?? [];
        return in_array($user->position, $allowed, true);
    }

    /**
     * Можно ли взять заявку в работу (она в статусе new/paused).
     */
    public function isAvailableForAssignment(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_PAUSED], true);
    }

    /**
     * Активна ли заявка (не завершена и не отменена).
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PAUSED,
        ], true);
    }

    /**
     * Человекочитаемый статус.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Цвет статуса (Tailwind color name).
     */
    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'slate';
    }

    /**
     * Человекочитаемый тип работ.
     */
    public function workTypeLabel(): string
    {
        return self::WORK_TYPE_LABELS[$this->work_type] ?? '—';
    }

    /**
     * Человекочитаемый приоритет.
     */
    public function priorityLabel(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? self::PRIORITY_LABELS[self::PRIORITY_MEDIUM];
    }

    public function priorityColor(): string
    {
        return self::PRIORITY_COLORS[$this->priority] ?? 'slate';
    }

    public function priorityWeight(): int
    {
        return self::PRIORITY_WEIGHTS[$this->priority] ?? 2;
    }
}
