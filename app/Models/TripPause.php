<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripPause extends Model
{
    // Типы пауз
    const TYPE_BREAKDOWN = 'breakdown';
    const TYPE_ROAD_WORKS = 'road_works';
    const TYPE_WAITING_LOADING = 'waiting_loading';
    const TYPE_WAITING_UNLOADING = 'waiting_unloading';
    const TYPE_WEATHER = 'weather';
    const TYPE_TRAFFIC = 'traffic';
    const TYPE_OTHER = 'other';

    public static function types(): array
    {
        return [
            self::TYPE_BREAKDOWN => 'Поломка',
            self::TYPE_ROAD_WORKS => 'Дорожные работы',
            self::TYPE_WAITING_LOADING => 'Ожидание погрузки',
            self::TYPE_WAITING_UNLOADING => 'Ожидание назначения',
            self::TYPE_WEATHER => 'Погодные условия',
            self::TYPE_TRAFFIC => 'Пробки',
            self::TYPE_OTHER => 'Другое',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        if ($type === null) {
            return 'Не указано';
        }
        return self::types()[$type] ?? $type;
    }

    protected $fillable = [
        'truck_trip_id',
        'truck_id',
        'type',
        'reason',
        'notes',
        'started_at',
        'ended_at',
        'duration_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(TruckTrip::class, 'truck_trip_id');
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    /**
     * Активная (незавершённая) пауза?
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Завершить паузу
     */
    public function end(): void
    {
        if ($this->ended_at) {
            return;
        }

        $now = now();
        $duration = (int) $now->diffInSeconds($this->started_at);

        $this->update([
            'ended_at' => $now,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Текущая длительность (если активна) или сохранённая
     */
    public function getCurrentDuration(): int
    {
        if ($this->ended_at) {
            return $this->duration_seconds ?? 0;
        }

        return (int) now()->diffInSeconds($this->started_at);
    }

    /**
     * Алиас для getCurrentDuration
     */
    public function getDurationSeconds(): int
    {
        return $this->getCurrentDuration();
    }

    /**
     * Форматированная длительность
     */
    public function getFormattedDuration(): string
    {
        $seconds = $this->getCurrentDuration();
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d ч %d мин %d сек', $hours, $minutes, $secs);
        }
        return sprintf('%d мин %d сек', $minutes, $secs);
    }
}
