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
 * - wrr_cursor: курсор для WRR алгоритма
 * - last_assigned_at: время последнего назначения (для распределения по времени)
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
        'wrr_cursor',
        'last_assigned_at',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'active' => 'boolean',
        'weight' => 'integer',
        'wrr_cursor' => 'decimal:2',
        'last_assigned_at' => 'datetime',
    ];

    protected $attributes = [
        'active' => false,
        'weight' => 100,
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
}
