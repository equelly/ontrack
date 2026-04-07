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
        'completed_at',
        'zone_id',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
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
     * Форматированное время рейса (без секунд)
     */
    public function getFormattedTripDuration(): string
    {
        $seconds = $this->getNetTripSeconds();

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return sprintf('%d:%02d ч', $hours, $minutes);
        }

        return sprintf('%d мин', $minutes);
    }
}