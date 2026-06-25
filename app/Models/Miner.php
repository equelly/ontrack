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
 * - face_dismantling: Разбор забоя (грузовики доезжают, новые не назначаются)
 * - access_setup: Устройство подъезда (грузовики доезжают, новые не назначаются)
 * - relocation: Переезд (грузовики доезжают, новые не назначаются)
 */
class Miner extends Model
{
    use HasFactory;

    // Статусы
    const STATUS_ACTIVE = 'active';
    const STATUS_BREAKDOWN = 'breakdown';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_FACE_DISMANTLING = 'face_dismantling';
    const STATUS_ACCESS_SETUP = 'access_setup';
    const STATUS_RELOCATION = 'relocation';

    // Группы статусов
    const STATUSES_WORKING = [self::STATUS_ACTIVE];
    const STATUSES_DELAYED = [
        self::STATUS_BREAKDOWN,
        self::STATUS_MAINTENANCE,
        self::STATUS_FACE_DISMANTLING,
        self::STATUS_ACCESS_SETUP,
        self::STATUS_RELOCATION,
    ];
    const STATUSES_PLANNED_DELAY = [
        self::STATUS_MAINTENANCE,
        self::STATUS_FACE_DISMANTLING,
        self::STATUS_ACCESS_SETUP,
        self::STATUS_RELOCATION,
    ];

    protected $fillable = [
        'name_miner',
        'capacity_per_trip',
        'active',
        'description',
        'current_rock_id',
        'target_load_time',
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
        return \App\Domain\MinerStatus::label($this->status);
    }

    /**
     * Получить CSS класс для статуса
     */
    public function getStatusClass(): string
    {
        return \App\Domain\MinerStatus::color($this->status);
    }

    /**
     * Все возможные статусы
     */
    public static function getAllStatuses(): array
    {
        $statuses = [];
        foreach (\App\Domain\MinerStatus::all() as $status) {
            $statuses[$status] = \App\Domain\MinerStatus::label($status);
        }
        return $statuses;
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

    // ==========================================
    // ПРОИЗВОДИТЕЛЬНОСТЬ
    // ==========================================

    /**
     * Получить последние N завершённых рейсов для статистики
     */
    public function getRecentTrips(int $count = 5)
    {
        return $this->truckTrips()
            ->whereNotNull('completed_at')
            ->orderBy('completed_at', 'desc')
            ->limit($count)
            ->get();
    }

    /**
     * Среднее время погрузки за последние N рейсов (в минутах)
     */
    public function getAvgLoadTime(int $sampleCount = 5): ?float
    {
        $trips = $this->getRecentTrips($sampleCount);

        $loadTimes = $trips->filter(function ($trip) {
            return $trip->load_start && $trip->loaded_at;
        })->map(function ($trip) {
            return $trip->load_start->diffInSeconds($trip->loaded_at) / 60;
        });

        return $loadTimes->isNotEmpty() ? round($loadTimes->avg(), 1) : null;
    }

    /**
     * Среднее время ожидания погрузки за последние N рейсов (в минутах)
     */
    public function getAvgWaitTime(int $sampleCount = 5): ?float
    {
        $trips = $this->getRecentTrips($sampleCount);

        $waitTimes = $trips->filter(function ($trip) {
            return $trip->wait_start && $trip->load_start;
        })->map(function ($trip) {
            return $trip->wait_start->diffInSeconds($trip->load_start) / 60;
        });

        return $waitTimes->isNotEmpty() ? round($waitTimes->avg(), 1) : null;
    }

    /**
     * Количество самосвалов сейчас у забоя (на погрузке или ожидающих)
     */
    public function getCurrentTrucksCount(): int
    {
        return Truck::whereIn('status', ['to_miner', 'waiting_loading', 'loading'])
            ->whereHas('trips', function ($q) {
                $q->where('miner_id', $this->id)
                  ->whereNull('completed_at');
            })
            ->count();
    }

    /**
     * Количество самосвалов ожидающих погрузки
     */
    public function getWaitingTrucksCount(): int
    {
        return Truck::where('status', 'waiting_loading')
            ->whereHas('trips', function ($q) {
                $q->where('miner_id', $this->id)
                  ->whereNull('completed_at');
            })
            ->count();
    }

    /**
     * Количество самосвалов на погрузке
     */
    public function getLoadingTrucksCount(): int
    {
        return Truck::where('status', 'loading')
            ->whereHas('trips', function ($q) {
                $q->where('miner_id', $this->id)
                  ->whereNull('completed_at');
            })
            ->count();
    }

    /**
     * Рассчитать рекомендуемое количество самосвалов
     * Формула: T_рейса / T_погрузки
     */
    public function getRecommendedTruckCount(): ?array
    {
        // Получаем среднее время погрузки (фактическое или целевое)
        $avgLoadTime = $this->getAvgLoadTime();
        // target_load_time хранится в секундах, конвертируем в минуты
        $targetLoadTime = $this->target_load_time ? $this->target_load_time / 60 : null;

        // Используем фактическое время если есть, иначе целевое
        $loadTime = $avgLoadTime ?? $targetLoadTime;

        if (!$loadTime) {
            return null;
        }

        // Получаем среднее время ожидания
        $avgWaitTime = $this->getAvgWaitTime() ?? 0;

        // Получаем среднее время рейса (упрощённо: время погрузки + время в пути)
        // Для точного расчёта нужно знать расстояние до отвала
        // Берём среднее время рейса из последних завершённых
        $trips = $this->getRecentTrips(5);
        $avgTripTime = null;

        $tripTimes = $trips->filter(function ($trip) {
            return $trip->started_at && $trip->completed_at;
        })->map(function ($trip) {
            return $trip->started_at->diffInSeconds($trip->completed_at) / 60;
        });

        if ($tripTimes->isNotEmpty()) {
            $avgTripTime = $tripTimes->avg();
        }

        // Если нет данных о рейсах, используем упрощённую формулу
        // N_opt = 2 * (T_погрузки + T_ожидания) / T_погрузки = 2 + 2*T_ожидания/T_погрузки
        // Но лучше использовать данные о рейсах

        if ($avgTripTime && $avgTripTime > 0) {
            $recommended = round($avgTripTime / $loadTime);
        } else {
            // Упрощённый расчёт: 2 рейса = 1 машина загружена, 1 в пути
            $recommended = max(2, round(2 + ($avgWaitTime / $loadTime)));
        }

        return [
            'recommended' => $recommended,
            'current' => $this->getCurrentTrucksCount(),
            'waiting' => $this->getWaitingTrucksCount(),
            'loading' => $this->getLoadingTrucksCount(),
            'avg_load_time' => $avgLoadTime,
            'target_load_time' => $targetLoadTime,
            'avg_wait_time' => $avgWaitTime,
            'avg_trip_time' => $avgTripTime ? round($avgTripTime, 1) : null,
            'balance' => $this->getBalanceStatus($recommended, $this->getCurrentTrucksCount()),
        ];
    }

    /**
     * Определить статус баланса
     */
    public function getBalanceStatus(int $recommended, int $current): string
    {
        if ($current < $recommended - 1) {
            return 'underloaded'; // Недогружен
        } elseif ($current > $recommended + 1) {
            return 'overloaded'; // Перегружен
        }
        return 'balanced'; // Сбалансирован
    }
}
