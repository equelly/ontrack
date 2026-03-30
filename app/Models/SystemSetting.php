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
}

