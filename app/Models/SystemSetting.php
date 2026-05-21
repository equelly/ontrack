<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * Получить значение настройки по ключу
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        
        return $setting ? $setting->value : $default;
    }

    /**
     * Установить значение настройки
     */
    public static function set(string $key, mixed $value, ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
            ]
        );
    }

    /**
     * Режим активации маршрутов: auto или manual
     */
    public static function getRouteActivationMode(): string
    {
        return static::get('route_activation_mode', 'auto');
    }

    /**
     * Установить режим активации маршрутов
     */
    public static function setRouteActivationMode(string $mode): void
    {
        static::set('route_activation_mode', $mode);
    }

    /**
     * Автоматический режим?
     */
    public static function isAutoMode(): bool
    {
        return static::getRouteActivationMode() === 'auto';
    }

    /**
     * Ручной режим?
     */
    public static function isManualMode(): bool
    {
        return static::getRouteActivationMode() === 'manual';
    }

    // ==========================================
    // ПОРОГИ ПЕРЕГРУЖЕННОСТИ
    // ==========================================

    /**
     * Порог ожидания на забое (сколько самосвалов в waiting_loading = перегрузка)
     */
    public static function getMinerOverloadThreshold(): int
    {
        return (int) static::get('miner_overload_threshold', 3);
    }

    /**
     * Установить порог ожидания на забое
     */
    public static function setMinerOverloadThreshold(int $threshold): void
    {
        static::set('miner_overload_threshold', $threshold, 'Порог ожидания на забое (самосвалов)');
    }

    /**
     * Порог ожидания на зоне разгрузки (сколько самосвалов в waiting_unloading = перегрузка)
     */
    public static function getZoneOverloadThreshold(): int
    {
        return (int) static::get('zone_overload_threshold', 3);
    }

    /**
     * Установить порог ожидания на зоне разгрузки
     */
    public static function setZoneOverloadThreshold(int $threshold): void
    {
        static::set('zone_overload_threshold', $threshold, 'Порог ожидания на зоне разгрузки (самосвалов)');
    }

    /**
     * Получить все настройки порогов
     */
    public static function getOverloadThresholds(): array
    {
        return [
            'miner_threshold' => static::getMinerOverloadThreshold(),
            'zone_threshold' => static::getZoneOverloadThreshold(),
        ];
    }

    /**
     * Установить все пороги разом
     */
    public static function setOverloadThresholds(int $minerThreshold, int $zoneThreshold): void
    {
        static::setMinerOverloadThreshold($minerThreshold);
        static::setZoneOverloadThreshold($zoneThreshold);
    }    
}

