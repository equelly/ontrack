<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'dump_id',        // Связь с dump
        'name_zone',      // Название зоны
        'volume',         // Объём
        'ship',           // Отгрузка (boolean)
        'delivery',       //завозка (boolean)
    ];

    protected $casts = [
        'ship' => 'boolean',
        'delivery' => 'boolean',
        'volume' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

        protected static function boot()
    {
        parent::boot();

        // ← НОВОЕ: обновляем Dump при изменении Zone
        static::saved(function ($zone) {
            if ($zone->dump && Auth::check()) {
                $zone->dump->update([
                    'last_updated_at' => now(),
                    'last_updated_by' => Auth::id()
                ]);
            }
        });
    }

    public function dump() {
        return $this->belongsTo(Dump::class);
    }
    public function routes() {
        return $this->hasMany(Route::class);
    }
    public function rocks() {
        return $this->belongsToMany(Rock::class, 'rock_zone');
    }
}
