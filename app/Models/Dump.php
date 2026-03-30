<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dump extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_dump',
        'delivered_volume',
        'trips_count',
        'last_updated_by',
        'last_updated_at',
        'loader_zone_id'
    ];

    protected $casts = [
        'delivered_volume' => 'decimal:2',
        'trips_count' => 'integer',
        'last_updated_at' => 'datetime'
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
}
