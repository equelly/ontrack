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
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];
}

