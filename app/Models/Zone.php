<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
}
