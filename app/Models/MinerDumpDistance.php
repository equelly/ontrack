<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinerDumpDistance extends Model
{
    use HasFactory;

    protected $table = 'miner_dump_distances'; // имя таблицы

    protected $fillable = [
        'miner_id',
        'dump_id', 
        'distance_km',
        'travel_time_hours'
    ];

    // Связи с моделями
    public function miner()
    {
        return $this->belongsTo(Miner::class);
    }

    public function dump()
    {
        return $this->belongsTo(Dump::class);
    }
}

