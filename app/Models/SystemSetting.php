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

    // ==========================================
    // НАСТРОЙКИ СЕРВИСНЫХ ПОСТОВ
    // ==========================================

    /**
     * Количество постов заправки
     */
    public static function getFuelingPostsCount(): int
    {
        return (int) static::get('fueling_posts_count', 2);
    }

    public static function setFuelingPostsCount(int $count): void
    {
        static::set('fueling_posts_count', $count, 'Количество постов заправки');
    }

    /**
     * Количество постов ТО
     */
    public static function getMaintenancePostsCount(): int
    {
        return (int) static::get('maintenance_posts_count', 2);
    }

    public static function setMaintenancePostsCount(int $count): void
    {
        static::set('maintenance_posts_count', $count, 'Количество постов техобслуживания');
    }

    /**
     * Количество постов шиномонтажа
     */
    public static function getTireServicePostsCount(): int
    {
        return (int) static::get('tire_service_posts_count', 3);
    }

    public static function setTireServicePostsCount(int $count): void
    {
        static::set('tire_service_posts_count', $count, 'Количество постов шиномонтажа');
    }

    /**
     * Интервал ТО-1 (в мото-часах)
     */
    public static function getTO1Interval(): int
    {
        return (int) static::get('to1_interval_hours', 250);
    }

    public static function setTO1Interval(int $hours): void
    {
        static::set('to1_interval_hours', $hours, 'Интервал ТО-1 (мото-часы)');
    }

    /**
     * Интервал ТО-2 (в мото-часах)
     */
    public static function getTO2Interval(): int
    {
        return (int) static::get('to2_interval_hours', 500);
    }

    public static function setTO2Interval(int $hours): void
    {
        static::set('to2_interval_hours', $hours, 'Интервал ТО-2 (мото-часы)');
    }

    /**
     * Коэффициент для пустых пробегов
     */
    public static function getEmptyRunCoefficient(): float
    {
        return (float) static::get('empty_run_coefficient', 0.5);
    }

    public static function setEmptyRunCoefficient(float $coefficient): void
    {
        static::set('empty_run_coefficient', $coefficient, 'Коэффициент для расчёта пустых пробегов');
    }

    /**
     * Получить все настройки сервисных постов
     */
    public static function getServicePostsSettings(): array
    {
        return [
            'fueling_posts_count' => static::getFuelingPostsCount(),
            'maintenance_posts_count' => static::getMaintenancePostsCount(),
            'tire_service_posts_count' => static::getTireServicePostsCount(),
            'to1_interval_hours' => static::getTO1Interval(),
            'to2_interval_hours' => static::getTO2Interval(),
            'empty_run_coefficient' => static::getEmptyRunCoefficient(),
        ];
    }

    /**
     * Установить все настройки сервисных постов
     */
    public static function setServicePostsSettings(array $settings): void
    {
        if (isset($settings['fueling_posts_count'])) {
            static::setFuelingPostsCount($settings['fueling_posts_count']);
        }
        if (isset($settings['maintenance_posts_count'])) {
            static::setMaintenancePostsCount($settings['maintenance_posts_count']);
        }
        if (isset($settings['tire_service_posts_count'])) {
            static::setTireServicePostsCount($settings['tire_service_posts_count']);
        }
        if (isset($settings['to1_interval_hours'])) {
            static::setTO1Interval($settings['to1_interval_hours']);
        }
        if (isset($settings['to2_interval_hours'])) {
            static::setTO2Interval($settings['to2_interval_hours']);
        }
        if (isset($settings['empty_run_coefficient'])) {
            static::setEmptyRunCoefficient($settings['empty_run_coefficient']);
        }
    }

    // ==========================================
    // БУФЕРЫ ОБСЛУЖИВАНИЯ
    // ==========================================

    /**
     * Буфер ТО (моточасы до наступления срока для отправки)
     */
    public static function getServiceToBufferHours(): int
    {
        return (int) static::get('service_to_buffer_hours', 20);
    }

    public static function setServiceToBufferHours(int $hours): void
    {
        static::set('service_to_buffer_hours', $hours, 'Буфер ТО (моточасы до наступления срока)');
    }

    /**
     * Буфер заправки (% остатка топлива для отправки)
     */
    public static function getServiceFuelingBufferPercent(): int
    {
        return (int) static::get('service_fueling_buffer_percent', 15);
    }

    public static function setServiceFuelingBufferPercent(int $percent): void
    {
        static::set('service_fueling_buffer_percent', $percent, 'Буфер заправки (% остатка топлива)');
    }

    /**
     * Получить все настройки буферов
     */
    public static function getServiceBuffers(): array
    {
        return [
            'to_buffer_hours' => static::getServiceToBufferHours(),
            'fueling_buffer_percent' => static::getServiceFuelingBufferPercent(),
        ];
    }

    /**
     * Установить все настройки буферов
     */
    public static function setServiceBuffers(int $toBufferHours, int $fuelingBufferPercent): void
    {
        static::setServiceToBufferHours($toBufferHours);
        static::setServiceFuelingBufferPercent($fuelingBufferPercent);
    }
}
