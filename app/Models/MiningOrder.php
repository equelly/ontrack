<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * MiningOrder - маршрут забой → отвал
 *
 * Основные поля:
 * - miner_id: забой
 * - dump_id: отвал
 * - active: выбран ли маршрут алгоритмом (0=резерв, 1=активен)
 * - weight: пропорция распределения (по умолчанию 100)
 * - weight_adjustment: временная корректировка веса при перегрузке зоны
 * - wrr_cursor: курсор для WRR алгоритма
 * - last_assigned_at: время последнего назначения (для распределения по времени)
 *
 * Метрики ожидания разгрузки:
 * - avg_wait_time: среднее время ожидания разгрузки (секунды)
 * - total_wait_time: суммарное время ожидания за период
 * - metrics_updated_at: время последнего расчёта метрик
 *
 * Дополнительные поля (могут быть null):
 * - rock_id: порода (кэшируется из miner.current_rock для истории)
 * - zone_id: конкретная зона (назначается диспетчером вручную)
 * - distance_km: расстояние (кэшируется для быстрого доступа)
 *
 * НЕ хранится:
 * - score: рассчитывается на лету
 */
class MiningOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'miner_id',
        'dump_id',
        'rock_id',
        'zone_id',
        'distance_km',
        'active',
        'weight',
        'weight_adjustment',
        'avg_wait_time',
        'total_wait_time',
        'metrics_updated_at',
        'wrr_cursor',
        'last_assigned_at',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'active' => 'boolean',
        'weight' => 'integer',
        'weight_adjustment' => 'integer',
        'avg_wait_time' => 'integer',
        'total_wait_time' => 'integer',
        'wrr_cursor' => 'decimal:2',
        'last_assigned_at' => 'datetime',
        'metrics_updated_at' => 'datetime',
    ];

    protected $attributes = [
        'active' => false,
        'weight' => 100,
        'weight_adjustment' => 0,
        'avg_wait_time' => 0,
        'total_wait_time' => 0,
        'wrr_cursor' => 0,
    ];

    // ==========================================
    // СВЯЗИ
    // ==========================================

    public function miner()
    {
        return $this->belongsTo(Miner::class, 'miner_id');
    }

    public function dump()
    {
        return $this->belongsTo(Dump::class, 'dump_id');
    }

    public function rock()
    {
        return $this->belongsTo(Rock::class, 'rock_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function trips()
    {
        return $this->hasMany(TruckTrip::class, 'mining_order_id');
    }

    // ==========================================
    // ДИНАМИЧЕСКИЕ АТРИБУТЫ
    // ==========================================

    /**
     * Расстояние до отвала (из miner_dump_distances)
     */
    public function getDistanceAttribute(): ?float
    {
        return MinerDumpDistance::where('miner_id', $this->miner_id)
            ->where('dump_id', $this->dump_id)
            ->value('distance_km');
    }

    /**
     * Время в пути (из miner_dump_distances)
     */
    public function getTravelTimeAttribute(): ?float
    {
        return MinerDumpDistance::where('miner_id', $this->miner_id)
            ->where('dump_id', $this->dump_id)
            ->value('travel_time_hours');
    }

    /**
     * Текущая порода забоя
     */
    public function getCurrentRockAttribute(): ?Rock
    {
        return $this->miner?->currentRock;
    }

    /**
     * Доступные зоны для этого маршрута
     */
    public function getAvailableZonesAttribute()
    {
        $rock = $this->current_rock;
        
        if (!$rock) {
            return collect();
        }

        return Zone::where('dump_id', $this->dump_id)
            ->where('delivery', true)
            ->whereHas('rocks', fn($q) => $q->where('rocks.id', $rock->id))
            ->whereRaw('volume < capacity')
            ->orderBy('volume', 'asc')
            ->get();
    }

    /**
     * Рассчитать score маршрута
     * Меньше score = лучше
     * 
     * Формула: score = расстояние * объём_в_зонах
     * - Чем меньше расстояние - тем лучше
     * - Чем меньше объём в зонах - тем лучше (равномерное заполнение)
     */
    public function calculateScore(): float
    {
        $distance = $this->distance ?? 999;
        $availableZones = $this->available_zones;
        
        if ($availableZones->isEmpty()) {
            return 999999; // Нет доступных зон = очень плохо
        }
        
        // Объём в зонах (чем меньше, тем выше приоритет - для равномерного заполнения)
        $volumeInZones = $availableZones->sum('volume');
        
        // Score = расстояние * объём
        // Чем меньше расстояние И меньше объём - тем меньше score (лучше)
        $score = ($distance * 10) * max($volumeInZones / 1000, 0.1);
        
        return round($score, 2);
    }

    // ==========================================
    // СКОПЫ
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    public function scopeForMiner($query, int $minerId)
    {
        return $query->where('miner_id', $minerId);
    }

    public function scopeForDump($query, int $dumpId)
    {
        return $query->where('dump_id', $dumpId);
    }

    public function scopeWithAvailableZones($query)
    {
        return $query->whereHas('dump.zones', function ($q) {
            $q->where('delivery', true)
              ->whereRaw('volume < capacity');
        });
    }

    // ==========================================
    // УПРАВЛЕНИЕ ВЕСОМ
    // ==========================================

    /**
     * Получить эффективный вес (с учётом корректировки)
     */
    public function getEffectiveWeight(): int
    {
        return max(1, $this->weight + $this->weight_adjustment);
    }

    /**
     * Уменьшить вес маршрута при перегрузке зоны
     * Временная мера для снижения потока грузовиков
     */
    public function reduceWeight(int $reduction = 50): void
    {
        $this->update([
            'weight_adjustment' => $this->weight_adjustment - $reduction,
        ]);

        \Illuminate\Support\Facades\Log::info("MiningOrder {$this->id} weight reduced", [
            'weight' => $this->weight,
            'adjustment' => $this->weight_adjustment,
            'effective_weight' => $this->getEffectiveWeight(),
        ]);
    }

    /**
     * Восстановить вес маршрута при нормализации зоны
     */
    public function restoreWeight(): void
    {
        if ($this->weight_adjustment !== 0) {
            $this->update([
                'weight_adjustment' => 0,
            ]);

            \Illuminate\Support\Facades\Log::info("MiningOrder {$this->id} weight restored", [
                'weight' => $this->weight,
            ]);
        }
    }

    /**
     * Проверить, уменьшен ли вес маршрута
     */
    public function isWeightReduced(): bool
    {
        return $this->weight_adjustment < 0;
    }

    // ==========================================
    // МЕТРИКИ ВРЕМЕНИ ОЖИДАНИЯ
    // ==========================================

    /**
     * Обновить метрики времени ожидания разгрузки
     * Вызывается при завершении рейса или по расписанию
     */
    public function updateWaitTimeMetrics(): void
    {
        // Берём последние N завершённых рейсов
        $recentTrips = $this->trips()
            ->whereNotNull('completed_at')
            ->orderBy('completed_at', 'desc')
            ->limit(50)
            ->with('pauses')
            ->get();

        if ($recentTrips->isEmpty()) {
            return;
        }

        // Суммируем время ожидания разгрузки из пауз
        $totalWaitSeconds = 0;
        $tripsWithWait = 0;

        foreach ($recentTrips as $trip) {
            $tripWaitTime = $trip->pauses
                ->where('type', \App\Models\TripPause::TYPE_WAITING_UNLOADING)
                ->sum('duration_seconds');

            if ($tripWaitTime > 0) {
                $totalWaitSeconds += $tripWaitTime;
                $tripsWithWait++;
            }
        }

        $avgWaitTime = $tripsWithWait > 0 
            ? (int) ($totalWaitSeconds / $tripsWithWait) 
            : 0;

        $this->update([
            'avg_wait_time' => $avgWaitTime,
            'total_wait_time' => $totalWaitSeconds,
            'metrics_updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\Log::info("MiningOrder {$this->id} wait time metrics updated", [
            'avg_wait_seconds' => $avgWaitTime,
            'avg_wait_minutes' => round($avgWaitTime / 60, 1),
            'total_wait_seconds' => $totalWaitSeconds,
            'trips_count' => $tripsWithWait,
        ]);
    }

    /**
     * Среднее время ожидания в минутах
     */
    public function getAvgWaitMinutes(): float
    {
        return round($this->avg_wait_time / 60, 1);
    }

    /**
     * Суммарное время ожидания в часах
     */
    public function getTotalWaitHours(): float
    {
        return round($this->total_wait_time / 3600, 1);
    }

    /**
     * Форматированное среднее время ожидания
     */
    public function getFormattedAvgWaitTime(): string
    {
        $minutes = $this->avg_wait_time / 60;
        $hours = floor($minutes / 60);
        $mins = floor($minutes % 60);

        if ($hours > 0) {
            return sprintf('%d ч %d мин', $hours, $mins);
        }
        return sprintf('%d мин', $mins);
    }
}
