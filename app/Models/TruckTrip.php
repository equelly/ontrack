<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TruckTrip extends Model
{
    protected $fillable = [
        'truck_id',
        'driver_id',
        'miner_id',
        'dump_id',
        'mining_order_id',
        'load_volume',
        'rock_id',
        'started_at',
        'wait_start',
        'load_start',
        'loaded_at',
        'completed_at',
        'zone_id',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'wait_start'   => 'datetime',
        'load_start'   => 'datetime',
        'loaded_at'    => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function miner()
    {
        return $this->belongsTo(Miner::class);
    }

    public function dump()
    {
        return $this->belongsTo(Dump::class);
    }

    public function miningOrder()
    {
        return $this->belongsTo(MiningOrder::class);
    }

    public function rock()
    {
        return $this->belongsTo(Rock::class);
    }

    /**
     * Все паузы рейса
     */
    public function pauses()
    {
        return $this->hasMany(TripPause::class, 'truck_trip_id');
    }

    /**
     * Активная (незавершённая) пауза
     */
    public function activePause()
    {
        return $this->hasOne(TripPause::class, 'truck_trip_id')->whereNull('ended_at');
    }

    /**
     * Есть ли активная пауза?
     */
    public function isPaused(): bool
    {
        return $this->activePause()->exists();
    }

    /**
     * Общее время всех пауз (для таймера)
     */
    public function getTotalPauseSeconds(): int
    {
        $total = 0;

        // Используем загруженную коллекцию если есть
        $pauses = $this->relationLoaded('pauses')
            ? $this->pauses
            : $this->pauses()->get();

        foreach ($pauses as $pause) {
            if ($pause->ended_at) {
                $total += $pause->duration_seconds;
            } else {
                // Активная пауза - считаем текущую длительность
                $total += $pause->getCurrentDuration();
            }
        }

        return (int) $total;
    }

    /**
     * Чистое время рейса (без пауз) в секундах
     */
    public function getNetTripSeconds(): int
    {
        if (!$this->started_at) {
            return 0;
        }

        $endTime = $this->completed_at ?? now();
        $totalSeconds = $this->started_at->diffInSeconds($endTime);
        $pauseSeconds = $this->getTotalPauseSeconds();

        return max(0, $totalSeconds - $pauseSeconds);
    }

    /**
     * Форматированное время рейса
     */
    public function getFormattedTripDuration(): string
    {
        $seconds = $this->getNetTripSeconds();

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    // ==========================================
    // ВРЕМЯ ОЖИДАНИЯ И ПОГРУЗКИ
    // ==========================================

    /**
     * Время ожидания погрузки в секундах
     */
    public function getWaitSeconds(): int
    {
        if (!$this->wait_start) {
            return 0;
        }

        $endTime = $this->load_start ?? now();
        return (int) $this->wait_start->diffInSeconds($endTime);
    }

    /**
     * Время погрузки в секундах
     */
    public function getLoadSeconds(): int
    {
        if (!$this->load_start) {
            return 0;
        }

        $endTime = $this->loaded_at ?? now();
        return (int) $this->load_start->diffInSeconds($endTime);
    }

    /**
     * Форматированное время ожидания
     */
    public function getFormattedWaitTime(): string
    {
        $seconds = $this->getWaitSeconds();
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%d мин %d сек', $minutes, $secs);
        }
        return sprintf('%d сек', $secs);
    }

    /**
     * Форматированное время погрузки
     */
    public function getFormattedLoadTime(): string
    {
        $seconds = $this->getLoadSeconds();
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%d мин %d сек', $minutes, $secs);
        }
        return sprintf('%d сек', $secs);
    }

    /**
     * Форматированное время ожидания (только минуты)
     */
    public function getFormattedWaitTimeShort(): string
    {
        $seconds = $this->getWaitSeconds();
        $minutes = floor($seconds / 60);

        return sprintf('%d мин', $minutes);
    }

    /**
     * Форматированное время погрузки (только минуты)
     */
    public function getFormattedLoadTimeShort(): string
    {
        $seconds = $this->getLoadSeconds();
        $minutes = floor($seconds / 60);

        return sprintf('%d мин', $minutes);
    }
}
