<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


class Dump extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_dump',
        'delivered_volume',
        'trips_count',
        'last_updated_by',
        'last_updated_at',
        'loader_zone_id',
        'is_service_point',
    ];

    protected $casts = [
        'delivered_volume' => 'decimal:2',
        'trips_count' => 'integer',
        'last_updated_at' => 'datetime',
        'is_service_point' => 'boolean',
    ];

    public function zones()
    {
        return $this->hasMany(Zone::class, 'dump_id');
    }

    public function activeZones()
    {
        return $this->zones()->where('delivery', true);
    }

    public function orders()
    {
        return $this->hasMany(MiningOrder::class, 'dump_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function loaderZone()
    {
        return $this->belongsTo(Zone::class, 'loader_zone_id');
    }

    public function incrementVolume($volume)
    {
        $this->increment('delivered_volume', $volume);
        $this->increment('trips_count');
        $this->last_updated_at = now();
        $this->save();
    }

    // ==========================================
    // СЕРВИСНАЯ ТОЧКА (отстой / обслуживание / заправка)
    // ==========================================

    /**
     * Получить ID сервисной точки.
     *
     * Сервисная точка — это обычный Dump с флагом is_service_point=true.
     * Используется как fallback в Truck::getCurrentLocationDumpId()
     * когда у самосвала нет истории рейсов (новый, после долгого простоя,
     * после обслуживания). Считается что самосвал находится в сервисной
     * точке, если не знаем где он.
     *
     * ВАЖНО: должна быть запись в miner_dump_distances для пары
     * (сервисная точка, забой) для каждого рабочего забоя — иначе
     * пустой пробег будет null (см. calculateEmptyRun).
     *
     * Кэшируется на 1 минуту чтобы не дёргать БД каждый раз при расчёте WRR.
     */
    public static function getServicePointId(): ?int
    {
        return Cache::remember('dump.service_point_id', now()->addMinute(), function () {
            $dump = self::where('is_service_point', true)->first();
            return $dump?->id;
        });
    }

    /**
     * Получить модель сервисной точки.
     */
    public static function getServicePoint(): ?self
    {
        $id = self::getServicePointId();
        return $id ? self::find($id) : null;
    }

    /**
     * Установить dump как сервисную точку (снимает флаг с других).
     * Вызывается из админки при переключении.
     */
    public function markAsServicePoint(): void
    {
        DB::transaction(function () {
            // Снимаем флаг с других
            self::where('is_service_point', true)->update(['is_service_point' => false]);
            // Ставим на текущий
            $this->is_service_point = true;
            $this->save();
        });
        Cache::forget('dump.service_point_id');
    }
}