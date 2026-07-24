<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Services\MiningOrderSyncService;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'dump_id',
        'name_zone',
        'volume',
        'capacity',
        'ship',
        'delivery'
    ];

    protected $casts = [
        'volume' => 'decimal:2',
        'capacity' => 'decimal:2',
        'ship' => 'boolean',
        'delivery' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();

        // Обновляем Dump при изменении Zone
        static::saved(function ($zone) {
            if ($zone->dump && Auth::check()) {
                $zone->dump->update([
                    'last_updated_at' => now(),
                    'last_updated_by' => Auth::id()
                ]);
            }

            // Вызываем сервис для синхронизации статуса MiningOrder
            $service = app(\App\Services\MiningOrderSyncService::class);
            $service->syncActiveStatusForZone($zone->id);
        });

        static::deleted(function ($zone) {
            // Вызываем сервис для синхронизации статуса MiningOrder при удалении зоны
            $service = app(MiningOrderSyncService::class);
            $service->syncActiveStatusForZone($zone->id);
        });
    }

    public function dump()
    {
        return $this->belongsTo(Dump::class);
    }

    public function routes()
    {
        return $this->hasMany(Route::class);
    }

    public function rocks()
    {
        return $this->belongsToMany(Rock::class, 'rock_zone');
    }

    public function orders()
    {
        return $this->hasMany(MiningOrder::class, 'zone_id');
    }

    public function truckTrips()
    {
        return $this->hasMany(TruckTrip::class, 'zone_id');
    }

    public function getFillPercentageAttribute()
    {
        return $this->capacity > 0 ? ($this->volume / $this->capacity) * 100 : 0;
    }

    /**
     * Проверка доступности зоны для разгрузки
     */
    public function isAvailable(): bool
    {
        return $this->delivery && $this->volume < $this->capacity;
    }

    public function incrementVolume($volume)
    {
        $this->increment('volume', $volume);
    }

    // ==========================================
    // СТАТИСТИКА НАГРУЗКИ
    // ==========================================

    /**
     * Количество грузовиков сейчас у зоны (на разгрузке или ожидающих)
     */
    public function getCurrentTrucksCount(): int
    {
        return Truck::whereIn('status', [
            Truck::STATUS_TRANSPORTING,
            Truck::STATUS_UNLOADING,
            Truck::STATUS_WAITING_UNLOADING,
        ])
            ->whereHas('trips', function ($q) {
                $q->where('zone_id', $this->id)
                    ->whereNull('completed_at');
            })
            ->count();
    }

    /**
     * Количество грузовиков, ожидающих разгрузки
     */
    public function getWaitingTrucksCount(): int
    {
        return Truck::where('status', Truck::STATUS_WAITING_UNLOADING)
            ->whereHas('trips', function ($q) {
                $q->where('zone_id', $this->id)
                    ->whereNull('completed_at');
            })
            ->count();
    }

    /**
     * Количество грузовиков на разгрузке
     */
    public function getUnloadingTrucksCount(): int
    {
        return Truck::where('status', Truck::STATUS_UNLOADING)
            ->whereHas('trips', function ($q) {
                $q->where('zone_id', $this->id)
                    ->whereNull('completed_at');
            })
            ->count();
    }

    /**
     * Количество грузовиков в пути к зоне
     */
    public function getTransportingTrucksCount(): int
    {
        return Truck::where('status', Truck::STATUS_TRANSPORTING)
            ->whereHas('trips', function ($q) {
                $q->where('zone_id', $this->id)
                    ->whereNull('completed_at');
            })
            ->count();
    }

    /**
     * Порог перегрузки зоны (количество ожидающих)
     */
    const OVERLOAD_THRESHOLD = 3;

    /**
     * Перегружена ли зона
     */
    public function isOverloaded(): bool
    {
        return $this->getWaitingTrucksCount() >= self::OVERLOAD_THRESHOLD;
    }

    /**
     * Получить статус нагрузки зоны
     */
    public function getLoadStatus(): string
    {
        $waiting = $this->getWaitingTrucksCount();
        $fillPercentage = $this->fill_percentage;

        if ($fillPercentage >= 95) {
            return 'full';
        }
        if ($waiting >= self::OVERLOAD_THRESHOLD) {
            return 'overloaded';
        }
        if ($waiting > 0) {
            return 'busy';
        }
        return 'available';
    }

    /**
     * Полная статистика нагрузки зоны
     */
    public function getLoadStats(): array
    {
        return [
            'zone_id' => $this->id,
            'zone_name' => $this->name_zone,
            'dump_name' => $this->dump?->name_dump,
            'total_trucks' => $this->getCurrentTrucksCount(),
            'waiting_count' => $this->getWaitingTrucksCount(),
            'unloading_count' => $this->getUnloadingTrucksCount(),
            'transporting_count' => $this->getTransportingTrucksCount(),
            'fill_percentage' => round($this->fill_percentage, 1),
            'is_available' => $this->isAvailable(),
            'is_overloaded' => $this->isOverloaded(),
            'status' => $this->getLoadStatus(),
            'accepted_rocks' => $this->rocks->pluck('name_rock')->toArray(),
        ];
    }

    /**
     * Получить активные маршруты на эту зону
     */
    public function getActiveRoutes()
    {
        return MiningOrder::where('zone_id', $this->id)
            ->where('active', true)
            ->with(['miner', 'rock'])
            ->get();
    }
}
