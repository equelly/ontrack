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
        'zone_id',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
    // 
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
}

