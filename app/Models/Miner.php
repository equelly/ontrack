<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Miner - забой (экскаватор)
 *
 * Статусы:
 * - active: В работе (маршруты активны)
 * - breakdown: Поломка (грузовики перенаправляются, маршруты деактивируются)
 * - maintenance: Обслуживание (грузовики доезжают, новые не назначаются)
 * - dismantling: Разбор забоя (грузовики доезжают, новые не назначаются)
 * - access_setup: Устройство подъезда (грузовики доезжают, новые не назначаются)
 */
class Miner extends Model
{
    use HasFactory;

    // Статусы
    const STATUS_ACTIVE = 'active';
    const STATUS_BREAKDOWN = 'breakdown';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_DISMANTLING = 'dismantling';
    const STATUS_ACCESS_SETUP = 'access_setup';

    // Группы статусов
    const STATUSES_WORKING = [self::STATUS_ACTIVE];
    const STATUSES_DELAYED = [
        self::STATUS_BREAKDOWN,
        self::STATUS_MAINTENANCE,
        self::STATUS_DISMANTLING,
        self::STATUS_ACCESS_SETUP,
    ];
    const STATUSES_PLANNED_DELAY = [
        self::STATUS_MAINTENANCE,
        self::STATUS_DISMANTLING,
        self::STATUS_ACCESS_SETUP,
    ];

    protected $fillable = [
        'name_miner',
        'capacity_per_trip',
        'active',
        'description',
        'current_rock_id',
        'status',
        'status_changed_at',
        'status_changed_by',
        'last_updated_at',
        'last_updated_by'
    ];

    protected $casts = [
        'active' => 'boolean',
        'capacity_per_trip' => 'decimal:2',
        'last_updated_at' => 'datetime',
        'status_changed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'active' => true,
    ];

    // ==========================================
    // СВЯЗИ
    // ==========================================

    public function rocks()
    {
        return $this->belongsToMany(Rock::class, 'miner_rock', 'miner_id', 'rock_id');
    }

    /**
     * Текущая добываемая порода (выбирает экскаваторщик)
     */
    public function currentRock()
    {
        return $this->belongsTo(Rock::class, 'current_rock_id');
    }

    public function orders()
    {
        return $this->hasMany(MiningOrder::class, 'miner_id');
    }

    public function activeOrders()
    {
        return $this->orders()->where('active', true);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function distances()
    {
        return $this->hasMany(MinerDumpDistance::class, 'miner_id');
    }

    public function truckTrips()
    {
        return $this->hasMany(TruckTrip::class, 'miner_id');
    }

    public function statusChanger()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    // ==========================================
    // СТАТУСЫ
    // ==========================================

    /**
     * Работает ли забой
     */
    public function isWorking(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Находится ли в задержке
     */
    public function isDelayed(): bool
    {
        return in_array($this->status, self::STATUSES_DELAYED);
    }

    /**
     * Поломка (требуется перенаправление грузовиков)
     */
    public function isBreakdown(): bool
    {
        return $this->status === self::STATUS_BREAKDOWN;
    }

    /**
     * Плановая остановка (грузовики доезжают)
     */
    public function isPlannedDelay(): bool
    {
        return in_array($this->status, self::STATUSES_PLANNED_DELAY);
    }

    /**
     * Сколько времени в текущем статусе (в минутах)
     */
    public function getStatusDurationMinutes(): int
    {
        if (!$this->status_changed_at) {
            return 0;
        }

        return (int) $this->status_changed_at->diffInMinutes(now());
    }

    /**
     * Получить название статуса на русском
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'В работе',
            self::STATUS_BREAKDOWN => 'Поломка',
            self::STATUS_MAINTENANCE => 'Обслуживание',
            self::STATUS_DISMANTLING => 'Разбор забоя',
            self::STATUS_ACCESS_SETUP => 'Устройство подъезда',
            default => $this->status,
        };
    }

    /**
     * Получить CSS класс для статуса
     */
    public function getStatusClass(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_BREAKDOWN => 'danger',
            self::STATUS_MAINTENANCE => 'warning',
            self::STATUS_DISMANTLING => 'info',
            self::STATUS_ACCESS_SETUP => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Все возможные статусы
     */
    public static function getAllStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'В работе',
            self::STATUS_BREAKDOWN => 'Поломка',
            self::STATUS_MAINTENANCE => 'Обслуживание',
            self::STATUS_DISMANTLING => 'Разбор забоя',
            self::STATUS_ACCESS_SETUP => 'Устройство подъезда',
        ];
    }

    // ==========================================
    // СКОПЫ
    // ==========================================

    public function scopeWorking($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDelayed($query)
    {
        return $query->whereIn('status', self::STATUSES_DELAYED);
    }

    public function scopeBreakdown($query)
    {
        return $query->where('status', self::STATUS_BREAKDOWN);
    }
}
